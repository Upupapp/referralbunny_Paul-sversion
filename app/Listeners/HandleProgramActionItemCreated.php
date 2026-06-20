<?php

namespace App\Listeners;

use App\Events\ProgramActionItemCreated;
use App\Models\MemberActionItem;
use App\Models\PartnerProgramMembership;
use App\Models\ReferrerProgramMembership;
use App\Services\NotificationDispatchService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * Queued so notification I/O never blocks the action-item-creation request.
 * Re-fetches the model fresh since this may run well after the original
 * request (queue worker), rather than trusting any in-request state.
 */
class HandleProgramActionItemCreated implements ShouldQueue
{
    public int $tries = 3;
    public int $backoff = 10;

    public function handle(ProgramActionItemCreated $event): void
    {
        $item = MemberActionItem::find($event->actionItemId);
        if (!$item) {
            return;
        }

        $memberName = $this->resolveMemberName($item);
        $actionLabel = ucfirst(str_replace('_', ' ', $item->action_type));

        try {
            app(NotificationDispatchService::class)->dispatchToTenantAdmins(
                tenantId:     $item->tenant_id,
                category:     'program_action_item',
                priority:     'normal',
                title:        "Action needed: {$actionLabel} for {$memberName}",
                body:         "An action item was created for {$memberName} in your program.",
                actionUrl:    route('tenant.programs.workspace', [$item->tenant_id, $item->program_id]) . '?tab=action-items',
                actionLabel:  'View Action Items',
                dedupeSuffix: $item->id,
                metadata:     ['program_id' => $item->program_id, 'action_item_id' => $item->id],
            );

            $item->update(['notification_status' => 'sent']);
        } catch (\Throwable $e) {
            $item->update(['notification_status' => 'failed']);
            throw $e;
        }
    }

    public function failed(ProgramActionItemCreated $event, \Throwable $exception): void
    {
        Log::error('[HandleProgramActionItemCreated] Failed after all retries', [
            'action_item_id' => $event->actionItemId,
            'error'           => $exception->getMessage(),
        ]);
    }

    private function resolveMemberName(MemberActionItem $item): string
    {
        if ($item->membership_type === 'referrer') {
            $membership = ReferrerProgramMembership::with('reseller:id,name')->find($item->membership_id);
            return $membership?->reseller?->name ?? 'a referrer';
        }

        $membership = PartnerProgramMembership::with('partner:id,first_name,last_name')->find($item->membership_id);
        $partner    = $membership?->partner;
        return $partner ? (trim("{$partner->first_name} {$partner->last_name}") ?: 'a partner') : 'a partner';
    }
}
