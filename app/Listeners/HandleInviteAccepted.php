<?php

namespace App\Listeners;

use App\Events\InviteAcceptedEvent;
use App\Mail\InviterActivationMail;
use App\Models\ActivityLog;
use App\Services\CriticalActionService;
use App\Services\EmailLogger;
use App\Services\NotificationDispatchService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Handles all post-acceptance work for every invite type.
 *
 * Responsibilities:
 *   1. ActivityLog — records acceptance event for the tenant activity feed
 *   2. AuditLog    — security-level record (stored as ActivityLog with action='invite.accepted')
 *   3. Inviter notification — notifies the specific user who sent the invite
 *   4. Admin notification  — notifies tenant admins (skipped for reseller; HandleResellerJoined already does it)
 *
 * Deduplication: dedup key = "invite_accepted:{tenantId}:{acceptedUserId}" prevents
 * duplicate notifications if the listener is retried or the event fires twice.
 *
 * Tenant isolation: all recipient lookups are scoped to event.tenantId — never trusts
 * any frontend-supplied value.
 */
class HandleInviteAccepted implements ShouldQueue
{
    public int $tries   = 3;
    public int $backoff = 10;

    public function handle(InviteAcceptedEvent $event): void
    {
        $dedupBase = "invite_accepted:{$event->tenantId}:{$event->acceptedUserId}";

        // ── 1. ActivityLog ──────────────────────────────────────────────────
        $this->logActivity($event);

        // ── 2. AuditLog (security-level, stored via ActivityLog) ────────────
        $this->logAudit($event);

        // ── 3. Notify inviter ───────────────────────────────────────────────
        if ($event->invitedById) {
            $this->notifyInviter($event, $dedupBase);
        }

        // ── 4. Notify tenant admins ─────────────────────────────────────────
        // Skip reseller type: HandleResellerJoined already dispatched admin notification.
        if ($event->inviteType !== 'reseller') {
            $this->notifyAdmins($event, $dedupBase);
        }

        // ── 5. Cache bust — refresh admin CA badge + notification bell ───────
        // Only when admins were notified (not reseller type — HandleResellerJoined handles that).
        if ($event->inviteType !== 'reseller') {
            try {
                $adminIds = app(CriticalActionService::class)->invalidateAllAdminBadges($event->tenantId);
                foreach ($adminIds as $uid) {
                    Cache::forget("notif_unread_tenant_admin_{$uid}");
                }
            } catch (\Throwable) {}
        }
    }

    // ── Activity Log ─────────────────────────────────────────────────────────

    private function logActivity(InviteAcceptedEvent $event): void
    {
        try {
            [$entityType, $actionLabel] = match ($event->inviteType) {
                'reseller' => ['referrer',     'Referrer activated'],
                'partner'  => ['partner',      'Partner activated'],
                default    => ['tenant_user',  'Invite accepted'],
            };

            $description = match ($event->inviteType) {
                'reseller' => "{$event->acceptedUserName} accepted the invite and activated their Referrer account.",
                'partner'  => $event->relatedDealName
                    ? "{$event->acceptedUserName} accepted the invite and joined deal: {$event->relatedDealName}."
                    : "{$event->acceptedUserName} accepted the invite and activated their Partner account.",
                default    => "{$event->acceptedUserName} accepted the invite and joined as " . ucfirst($event->acceptedRole) . ".",
            };

            // Idempotency: skip on listener retry if the row already exists
            if (ActivityLog::where('tenant_id', $event->tenantId)
                ->where('action', 'invite_accepted')
                ->where('entity_id', $event->acceptedUserId)
                ->exists()) return;

            ActivityLog::create([
                'id'        => (string) Str::uuid(),
                'tenant_id' => $event->tenantId,
                'user_id'   => $event->invitedById, // actor = the person who sent the invite
                'action'    => 'invite_accepted',
                'entity'    => $entityType,
                'entity_id' => $event->acceptedUserId,
                'metadata'  => [
                    'action_label'       => $actionLabel,
                    'description'        => $description,
                    'accepted_user_name' => $event->acceptedUserName,
                    'accepted_user_email'=> $event->acceptedUserEmail,
                    'accepted_role'      => $event->acceptedRole,
                    'invite_type'        => $event->inviteType,
                    'invite_id'          => $event->inviteId,
                    'invited_by_id'      => $event->invitedById,
                    'related_deal_id'    => $event->relatedDealId,
                    'related_deal_name'  => $event->relatedDealName,
                    'accepted_at'        => $event->acceptedAt,
                ],
            ]);
        } catch (\Throwable $e) {
            \Log::warning('HandleInviteAccepted: ActivityLog failed: ' . $e->getMessage());
        }
    }

    // ── Audit Log ─────────────────────────────────────────────────────────────

    private function logAudit(InviteAcceptedEvent $event): void
    {
        try {
            // Idempotency: skip on listener retry if the audit row already exists
            $auditEntityId = $event->inviteId ?? $event->acceptedUserId;
            if (ActivityLog::where('tenant_id', $event->tenantId)
                ->where('action', 'invite.accepted')
                ->where('entity_id', $auditEntityId)
                ->exists()) return;

            ActivityLog::create([
                'id'        => (string) Str::uuid(),
                'tenant_id' => $event->tenantId,
                'user_id'   => $event->invitedById,
                'action'    => 'invite.accepted',
                'entity'    => 'invite',
                'entity_id' => $auditEntityId,
                'metadata'  => [
                    'audit_event'         => true,
                    'tenant_id'           => $event->tenantId,
                    'invite_id'           => $event->inviteId,
                    'accepted_user_id'    => $event->acceptedUserId,
                    'accepted_user_email' => $event->acceptedUserEmail,
                    'accepted_role'       => $event->acceptedRole,
                    'invite_type'         => $event->inviteType,
                    'invited_by_id'       => $event->invitedById,
                    'related_deal_id'     => $event->relatedDealId,
                    'accepted_at'         => $event->acceptedAt,
                ],
            ]);
        } catch (\Throwable $e) {
            \Log::warning('HandleInviteAccepted: AuditLog failed: ' . $e->getMessage());
        }
    }

    // ── Notify Inviter ────────────────────────────────────────────────────────

    private function notifyInviter(InviteAcceptedEvent $event, string $dedupBase): void
    {
        try {
            // Verify inviter is active in the same tenant (security: same-tenant only).
            // Include name for the email subject/body.
            $inviter = DB::table('tenant_memberships as tm')
                ->join('tenant_users as u', 'u.id', '=', 'tm.tenant_user_id')
                ->where('tm.tenant_id', $event->tenantId)
                ->where('tm.tenant_user_id', $event->invitedById)
                ->where('tm.status', 'active')
                ->selectRaw("u.id, u.email, TRIM(CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,''))) as name")
                ->first();

            if (! $inviter) {
                return; // inviter no longer active in this tenant — skip safely
            }

            [$title, $body, $actionUrl, $actionLabel] = $this->buildNotificationContent(
                $event, forInviter: true
            );

            // In-app notification to inviter
            app(NotificationDispatchService::class)->dispatch(
                category:         $this->category($event),
                priority:         'normal',
                title:            $title,
                body:             $body,
                notifiableType:   'tenant_admin',
                notifiableId:     (string) $inviter->id,
                tenantId:         $event->tenantId,
                actionUrl:        $actionUrl,
                actionLabel:      $actionLabel,
                deduplicationKey: "{$dedupBase}:inviter:{$inviter->id}",
            );

            // Email to inviter — only for reseller/partner activations.
            // tenant_user invitations already send TenantInvitationAcceptedMail
            // from TenantInvitationController; sending here would duplicate that email.
            if ($event->inviteType !== 'tenant_user' && $inviter->email) {
                $tenantName      = DB::table('tenants')->where('id', $event->tenantId)->value('name') ?? $event->tenantId;
                $acceptedName    = $event->acceptedUserName ?: $event->acceptedUserEmail;
                $roleLabel       = $this->roleLabel($event);

                EmailLogger::send(
                    mailable:       new InviterActivationMail(
                        inviterName:       trim($inviter->name),
                        inviterEmail:      $inviter->email,
                        acceptedUserName:  $acceptedName,
                        acceptedUserEmail: $event->acceptedUserEmail,
                        roleLabel:         $roleLabel,
                        tenantName:        $tenantName,
                        actionUrl:         url($actionUrl),
                        actionLabel:       $actionLabel,
                    ),
                    recipientEmail: $inviter->email,
                    recipientType:  'tenant_admin',
                    emailKey:       "inviter_activation.{$event->acceptedUserId}.{$inviter->id}",
                    subject:        "{$acceptedName} has activated their {$roleLabel} account on {$tenantName}",
                    tenantId:       $event->tenantId,
                );
            }
        } catch (\Throwable $e) {
            \Log::warning('HandleInviteAccepted: inviter notification failed: ' . $e->getMessage());
        }
    }

    // ── Notify Tenant Admins ──────────────────────────────────────────────────

    private function notifyAdmins(InviteAcceptedEvent $event, string $dedupBase): void
    {
        try {
            [$title, $body, $actionUrl, $actionLabel] = $this->buildNotificationContent(
                $event, forInviter: false
            );

            app(NotificationDispatchService::class)->dispatchToTenantAdmins(
                tenantId:     $event->tenantId,
                category:     $this->category($event),
                priority:     'normal',
                title:        $title,
                body:         $body,
                actionUrl:    $actionUrl,
                actionLabel:  $actionLabel,
                dedupeSuffix: "{$dedupBase}:admins",
                metadata:     [
                    'accepted_user_id'   => $event->acceptedUserId,
                    'accepted_role'      => $event->acceptedRole,
                    'invite_type'        => $event->inviteType,
                    'related_deal_id'    => $event->relatedDealId,
                ],
            );
        } catch (\Throwable $e) {
            \Log::warning('HandleInviteAccepted: admin notification failed: ' . $e->getMessage());
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Build notification title, body, CTA URL, and CTA label based on invite type.
     */
    private function buildNotificationContent(InviteAcceptedEvent $event, bool $forInviter): array
    {
        $name = $event->acceptedUserName ?: $event->acceptedUserEmail;

        return match ($event->inviteType) {
            'reseller' => [
                'Referrer accepted invite',
                "{$name} accepted the invite and activated their Referrer account.",
                "/tenant/{$event->tenantId}/referrers",
                'View Referrer',
            ],
            'partner' => $event->relatedDealId ? [
                'Partner joined deal',
                "{$name} accepted the invite and joined deal: " . ($event->relatedDealName ?? 'a deal') . ".",
                "/tenant/{$event->tenantId}/deals/{$event->relatedDealId}",
                'View Deal',
            ] : [
                'Partner accepted invite',
                "{$name} accepted the invite and activated their Partner account.",
                "/tenant/{$event->tenantId}/partners",
                'View Partner',
            ],
            default => [ // tenant_user (Admin/Manager/Member)
                'New ' . ucfirst($event->acceptedRole) . ' joined',
                "{$name} accepted the invite and joined as " . ucfirst($event->acceptedRole) . ".",
                "/tenant/{$event->tenantId}/users",
                'View User',
            ],
        };
    }

    /**
     * Map invite type to valid notification category (must be in CHECK constraint).
     */
    private function category(InviteAcceptedEvent $event): string
    {
        return match ($event->inviteType) {
            'reseller' => 'reseller_referrer',
            'partner'  => 'deal_pipeline',
            default    => 'tenant_workspace',
        };
    }

    private function roleLabel(InviteAcceptedEvent $event): string
    {
        return match ($event->inviteType) {
            'reseller' => 'Referrer',
            'partner'  => 'Partner',
            default    => ucfirst($event->acceptedRole),
        };
    }

    public function failed(InviteAcceptedEvent $event, \Throwable $exception): void
    {
        Log::error('[HandleInviteAccepted] Failed after all retries', [
            'tenant_id'        => $event->tenantId,
            'accepted_user_id' => $event->acceptedUserId,
            'invite_type'      => $event->inviteType,
            'error'            => $exception->getMessage(),
        ]);
    }
}
