<?php

namespace App\Listeners;

use App\Events\DealReferrerAssigned;
use App\Mail\ResellerDealAssigned;
use App\Models\Reseller;
use App\Services\EmailLogger;
use App\Services\NotificationDispatchService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HandleDealReferrerAssigned implements ShouldQueue
{
    public int $tries = 3;

    public function handle(DealReferrerAssigned $event): void
    {
        $dispatcher = app(NotificationDispatchService::class);

        $tenantName = DB::table('tenants')
            ->where('id', $event->tenantId)
            ->value('name') ?? $event->tenantId;

        // ── Resolve reseller record ───────────────────────────────────
        $resellerId = $event->resellerId;
        $email      = $event->resellerEmail;

        if (!$resellerId || !$email) {
            $reseller = Reseller::where('tenant_id', $event->tenantId)
                ->where('name', $event->resellerName)
                ->first();
            $resellerId = $resellerId ?? ($reseller ? (string) $reseller->id : null);
            $email      = $email      ?? $reseller?->email;
        } else {
            $reseller = Reseller::find($resellerId);
        }

        $isPending = $reseller && $reseller->status === 'invited';

        // Stable dedup suffix: deal + reseller (not time-based, so retries don't duplicate)
        $dedupBase = $event->leadId . ':' . $event->assignmentType . ':' . ($resellerId ?? $event->resellerName);

        // ── 1. In-app notification → assigned Referrer (active only) ──
        if ($resellerId && !$isPending) {
            $titleParts = $event->assignmentType === 'reassignment'
                ? ['You were assigned to a deal', 'You have been assigned to "' . $event->leadName . '". Check your deal details and next steps.']
                : ['You were assigned to a deal', 'You have been assigned to "' . $event->leadName . '". Review the details and take your first action.'];

            $dispatcher->dispatchToReseller(
                resellerId:   $resellerId,
                tenantId:     $event->tenantId,
                category:     'deal_pipeline',
                priority:     'high',
                title:        $titleParts[0],
                body:         $titleParts[1],
                actionUrl:    url("/reseller/{$event->tenantId}/deals"),
                actionLabel:  'View Deal',
                dedupeSuffix: $dedupBase,
            );
        }

        // ── 2. Email → assigned Referrer (active only) ────────────────
        if ($email && !$isPending) {
            $assignedByName = $event->assignedByName ?? 'Your workspace admin';

            EmailLogger::send(
                mailable: new ResellerDealAssigned(
                    resellerName:   $event->resellerName,
                    resellerEmail:  $email,
                    tenantName:     $tenantName,
                    dealName:       $event->leadName,
                    stage:          $event->stage,
                    dealValue:      $event->dealValue,
                    assignedByName: $assignedByName,
                    dealUrl:        url("/reseller/{$event->tenantId}/deals"),
                    isReassignment: $event->assignmentType === 'reassignment',
                ),
                recipientEmail: $email,
                recipientType:  'reseller',
                emailKey:       'deal_assigned.' . $event->leadId . '.' . ($resellerId ?? $event->resellerName),
                subject:        'You\'ve been assigned to a deal: ' . $event->leadName,
                tenantId:       $event->tenantId,
                dailyDedup:     true,
            );
        }

        // ── 3. In-app notification → Tenant Admins ────────────────────
        $adminTitle = match($event->assignmentType) {
            'reassignment'                => 'Deal reassigned',
            'pending_referrer_assignment' => 'Referrer assigned (invite pending)',
            default                       => 'Referrer assigned to deal',
        };
        $adminBody = '"' . $event->leadName . '" has been assigned to ' . $event->resellerName . '.';

        $dispatcher->dispatchToTenantAdmins(
            tenantId:     $event->tenantId,
            category:     'deal_pipeline',
            priority:     'normal',
            title:        $adminTitle,
            body:         $adminBody,
            actionUrl:    url("/tenant/{$event->tenantId}/deals/{$event->leadId}"),
            actionLabel:  'View Deal',
            dedupeSuffix: $dedupBase . ':admin',
            metadata:     [
                'deal_id'         => $event->leadId,
                'referrer_name'   => $event->resellerName,
                'assignment_type' => $event->assignmentType,
            ],
        );

        // ── 4. Pending-referrer: notify admins to follow up ───────────
        if ($isPending) {
            $dispatcher->dispatchToTenantAdmins(
                tenantId:     $event->tenantId,
                category:     'reseller_referrer',
                priority:     'normal',
                title:        'Referrer invite pending for assigned deal',
                body:         $event->resellerName . ' has been assigned to "'
                    . $event->leadName . '" but has not yet accepted their invitation.',
                actionUrl:    url("/tenant/{$event->tenantId}/referrers"),
                actionLabel:  'View Referrers',
                dedupeSuffix: $dedupBase . ':pending_admin',
            );
        }

        // ── 5. Reassignment: notify old referrer their access changed ─
        if ($event->assignmentType === 'reassignment' && $event->oldResellerName) {
            $oldReseller = Reseller::where('tenant_id', $event->tenantId)
                ->where('name', $event->oldResellerName)
                ->where('status', '!=', 'invited')
                ->first();

            if ($oldReseller) {
                $dispatcher->dispatchToReseller(
                    resellerId:   (string) $oldReseller->id,
                    tenantId:     $event->tenantId,
                    category:     'deal_pipeline',
                    priority:     'normal',
                    title:        'You were removed from a deal',
                    body:         'You are no longer assigned to "' . $event->leadName . '".',
                    actionUrl:    url("/reseller/{$event->tenantId}/deals"),
                    actionLabel:  'View My Deals',
                    dedupeSuffix: $event->leadId . ':removed:' . (string) $oldReseller->id,
                );
            }
        }
    }

    public function failed(DealReferrerAssigned $event, \Throwable $exception): void
    {
        Log::error('[HandleDealReferrerAssigned] Failed after all retries', [
            'lead_id'       => $event->leadId,
            'tenant_id'     => $event->tenantId,
            'reseller_name' => $event->resellerName,
            'error'         => $exception->getMessage(),
            'exception'     => (string) $exception,
        ]);
    }
}
