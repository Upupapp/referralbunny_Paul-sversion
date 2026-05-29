<?php

namespace App\Services;

use App\Mail\ResellerInvitation;
use App\Models\ActivityLog;
use App\Models\Notification;
use App\Models\Reseller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Enforces Referrer invitation deduplication rules:
 *
 * - One email → one pending Reseller record per tenant
 * - One invitation email per tenant/email/role within 24 hours
 * - Multiple deals assigned to same email → summarized in one email
 * - Active Referrers never receive account setup invites
 * - Admin/Manager Referrers (linked_tenant_user_id set) never receive setup invites
 *
 * Deduplication key: tenant_id + normalized email + Referrer role
 */
class ReferrerInvitationDeduplicationService
{
    const THROTTLE_HOURS = 24;

    /**
     * Central entry point for all Referrer assignment scenarios.
     *
     * Handles: manual deal creation, bulk import, contact role assignment,
     * Users & Roles invite, Referrers tab invite.
     *
     * @return array{
     *   reseller: Reseller,
     *   action: 'created'|'updated_pending'|'assigned_to_active'|'admin_manager_referrer',
     *   email_sent: bool,
     *   email_suppressed: bool,
     *   suppression_reason: string|null,
     *   message: string
     * }
     */
    public function handleReferrerAssignment(
        string  $tenantId,
        string  $email,
        string  $name,
        ?string $dealId     = null,
        string  $source     = 'manual',
        string  $tenantName = 'ReferralBunny',
        string  $actorId    = 'system',
        string  $actorRole  = 'system'
    ): array {
        $normalized = strtolower(trim($email));

        // 1. Find any existing reseller for this tenant+email
        $reseller = Reseller::where('tenant_id', $tenantId)
            ->whereRaw('LOWER(email) = ?', [$normalized])
            ->orderByRaw("CASE status WHEN 'active' THEN 0 WHEN 'nda_signed' THEN 1 WHEN 'invited' THEN 2 ELSE 3 END")
            ->first();

        // 2. Admin/Manager linked reseller — never send setup email
        if ($reseller && $reseller->linked_tenant_user_id) {
            if ($dealId) {
                $this->addDealToSummary($reseller, $dealId);
            }
            $this->audit($tenantId, $normalized, 'active_referrer_assigned_to_deal', $actorId, $actorRole, [$dealId], $source);
            return [
                'reseller'          => $reseller->fresh(),
                'action'            => 'admin_manager_referrer',
                'email_sent'        => false,
                'email_suppressed'  => true,
                'suppression_reason'=> 'linked_tenant_user_no_setup_email_needed',
                'message'           => 'Deal assigned to existing Admin/Manager Referrer. No setup email sent.',
            ];
        }

        // 3. Active Referrer — assign deal, never send setup email
        if ($reseller && in_array($reseller->status, ['active', 'nda_signed'])) {
            if ($dealId) {
                $this->addDealToSummary($reseller, $dealId);
            }
            $this->audit($tenantId, $normalized, 'active_referrer_assigned_to_deal', $actorId, $actorRole, [$dealId], $source);
            return [
                'reseller'          => $reseller->fresh(),
                'action'            => 'assigned_to_active',
                'email_sent'        => false,
                'email_suppressed'  => true,
                'suppression_reason'=> 'already_active_referrer',
                'message'           => 'Deal assigned to active Referrer. No invite email sent.',
            ];
        }

        // 4. Pending invitation exists — update summary, throttled resend
        if ($reseller && $reseller->status === 'invited') {
            if ($dealId) {
                $this->addDealToSummary($reseller, $dealId);
            }

            $shouldSend = $this->shouldSendEmail($reseller);
            if ($shouldSend) {
                $dealNames = $this->fetchDealNames($reseller);
                $setupUrl  = url('/reseller/setup?token=' . $reseller->setup_token);
                $emailSent = $this->sendSummarizedInvite($reseller, $tenantName, $setupUrl, $dealNames, $actorId, $actorRole);
            } else {
                $emailSent = false;
                $this->audit($tenantId, $normalized, 'referrer_invitation_email_suppressed_duplicate', $actorId, $actorRole, [$dealId], $source, [
                    'suppressed_count' => 1,
                    'reason'           => 'within_24h_throttle_window',
                ]);
            }

            $this->audit($tenantId, $normalized, 'referrer_invitation_summary_updated', $actorId, $actorRole, [$dealId], $source);
            return [
                'reseller'          => $reseller->fresh(),
                'action'            => 'updated_pending',
                'email_sent'        => $emailSent,
                'email_suppressed'  => !$emailSent,
                'suppression_reason'=> $emailSent ? null : 'within_24h_throttle_window',
                'message'           => $emailSent
                    ? 'Pending invitation updated and summarized email resent.'
                    : 'Pending invitation updated. Email not resent — sent within last 24 hours.',
            ];
        }

        // 5. No reseller record — create new and send invite
        $setupToken = Str::random(64);
        $reseller   = Reseller::create([
            'tenant_id'         => $tenantId,
            'name'              => $name,
            'email'             => $normalized,
            'status'            => 'invited',
            'setup_token'       => $setupToken,
            'invite_deal_ids'   => $dealId ? [$dealId] : [],
            'invite_deal_count' => $dealId ? 1 : 0,
            'is_anonymous'      => false,
            'assigned_leads'    => 0,
            'closed_value'      => 0,
            'performance_score' => 0,
            'joined_date'       => now()->toDateString(),
        ]);

        $dealNames = $dealId ? $this->fetchDealNames($reseller) : [];
        $setupUrl  = url('/reseller/setup?token=' . $setupToken);
        $emailSent = $this->sendSummarizedInvite($reseller, $tenantName, $setupUrl, $dealNames, $actorId, $actorRole);

        $this->audit($tenantId, $normalized, 'referrer_invitation_created', $actorId, $actorRole, [$dealId], $source);

        return [
            'reseller'          => $reseller->fresh(),
            'action'            => 'created',
            'email_sent'        => $emailSent,
            'email_suppressed'  => false,
            'suppression_reason'=> null,
            'message'           => $emailSent
                ? 'Referrer invitation created and email sent.'
                : 'Referrer invitation created but email delivery failed.',
        ];
    }

    /**
     * Add a deal to the invitation summary (idempotent).
     */
    public function addDealToSummary(Reseller $reseller, string $dealId): void
    {
        $current = $reseller->invite_deal_ids ?? [];
        if (!in_array($dealId, $current)) {
            $current[] = $dealId;
            $reseller->update([
                'invite_deal_ids'   => $current,
                'invite_deal_count' => count($current),
            ]);
        }
    }

    /**
     * Send or resend a summarized invitation email.
     * Updates invite_sent_at on success.
     */
    public function sendSummarizedInvite(
        Reseller $reseller,
        string   $tenantName,
        string   $setupUrl,
        array    $dealNames  = [],
        string   $actorId    = 'system',
        string   $actorRole  = 'system'
    ): bool {
        $dealCount  = $reseller->invite_deal_count ?? 0;
        $subject    = $dealCount > 1
            ? "You're invited as a Referrer for {$tenantName} — {$dealCount} deal(s) assigned"
            : "You've been invited as a referrer for {$tenantName}";
        $emailKey   = 'reseller_invite.' . $reseller->id;

        $sent = EmailLogger::send(
            mailable:      new ResellerInvitation(
                resellerName:  $reseller->name,
                resellerEmail: $reseller->email,
                tenantName:    $tenantName,
                setupUrl:      $setupUrl,
                dealCount:     $dealCount,
                dealNames:     array_slice($dealNames, 0, 3),
            ),
            recipientEmail: $reseller->email,
            recipientType:  'reseller',
            emailKey:       $emailKey,
            subject:        $subject,
            recipientId:    (string) $reseller->id,
            tenantId:       $reseller->tenant_id,
            dailyDedup:     true,
        );

        if ($sent) {
            $this->markEmailSent($reseller);
            $this->audit($reseller->tenant_id, $reseller->email, 'referrer_invitation_email_sent', $actorId, $actorRole, $reseller->invite_deal_ids ?? []);
            return true;
        }

        Log::warning("Referrer invite email failed to queue for {$reseller->email}", [
            'reseller_id' => $reseller->id,
            'tenant_id'   => $reseller->tenant_id,
            'email_key'   => $emailKey,
        ]);
        $this->audit($reseller->tenant_id, $reseller->email, 'referrer_invitation_email_failed', $actorId, $actorRole, $reseller->invite_deal_ids ?? []);
        return false;
    }

    /**
     * Decide whether to send another automatic invite email.
     * Returns true only if no email was sent in the last THROTTLE_HOURS.
     */
    public function shouldSendEmail(Reseller $reseller): bool
    {
        if ($reseller->status !== 'invited') {
            return false;
        }
        if (!$reseller->invite_sent_at) {
            return true;
        }
        return $reseller->invite_sent_at->lt(now()->subHours(self::THROTTLE_HOURS));
    }

    /**
     * Record the time an invitation email was sent.
     */
    public function markEmailSent(Reseller $reseller): void
    {
        $reseller->update(['invite_sent_at' => now()]);
    }

    // ── Private helpers ───────────────────────────────────────────────────

    private function fetchDealNames(Reseller $reseller): array
    {
        $dealIds = $reseller->invite_deal_ids ?? [];
        if (empty($dealIds)) {
            return [];
        }
        return DB::table('leads')
            ->whereIn('id', $dealIds)
            ->pluck('name')
            ->toArray();
    }

    private function audit(
        string  $tenantId,
        string  $email,
        string  $event,
        string  $actorId,
        string  $actorRole,
        array   $dealIds = [],
        string  $source  = 'system',
        array   $extra   = []
    ): void {
        try {
            ActivityLog::create([
                'id'        => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'user_id'   => $actorId,
                'action'    => $event,
                'entity'    => 'reseller_invitation',
                'entity_id' => md5($tenantId . ':' . strtolower($email)),
                'metadata'  => json_encode(array_merge([
                    'email_masked' => substr($email, 0, 3) . '***@***',
                    'role'         => 'referrer',
                    'actor_role'   => $actorRole,
                    'deal_ids'     => array_filter($dealIds),
                    'source'       => $source,
                    'timestamp'    => now()->toIso8601String(),
                ], $extra)),
            ]);
        } catch (\Throwable) {}
    }
}
