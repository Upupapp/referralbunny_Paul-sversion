<?php

namespace App\Listeners;

use App\Events\ProgramContractStatusChanged;
use App\Models\PartnerProgramMembership;
use App\Models\ProgramContract;
use App\Models\ReferrerProgramMembership;
use App\Services\NotificationDispatchService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * Queued so notification I/O never blocks the contract-transition request.
 * Re-fetches the contract fresh and re-checks its status, since this may run
 * well after the original request — a later transition could have already
 * moved the contract past the status this event was fired for.
 */
class HandleProgramContractStatusChanged implements ShouldQueue
{
    public int $tries = 3;
    public int $backoff = 10;

    private const TITLES = [
        'active'   => 'Contract accepted for :member',
        'declined' => 'Contract declined for :member',
    ];

    public function handle(ProgramContractStatusChanged $event): void
    {
        $contract = ProgramContract::find($event->contractId);
        if (!$contract || $contract->status !== $event->newStatus) {
            return;
        }

        $memberName = $this->resolveMemberName($contract);
        $title      = str_replace(':member', $memberName, self::TITLES[$event->newStatus] ?? "Contract {$event->newStatus} for :member");

        app(NotificationDispatchService::class)->dispatchToTenantAdmins(
            tenantId:     $contract->tenant_id,
            category:     'approvals',
            priority:     'normal',
            title:        $title,
            body:         "A contract status changed to \"{$event->newStatus}\" in your program.",
            actionUrl:    route('tenant.programs.workspace', [$contract->tenant_id, $contract->program_id]) . '?tab=contracts',
            actionLabel:  'View Contracts',
            dedupeSuffix: "{$contract->id}:{$event->newStatus}",
            metadata:     ['program_id' => $contract->program_id, 'contract_id' => $contract->id],
        );
    }

    public function failed(ProgramContractStatusChanged $event, \Throwable $exception): void
    {
        Log::error('[HandleProgramContractStatusChanged] Failed after all retries', [
            'contract_id' => $event->contractId,
            'error'        => $exception->getMessage(),
        ]);
    }

    private function resolveMemberName(ProgramContract $contract): string
    {
        if ($contract->membership_type === 'referrer') {
            $membership = ReferrerProgramMembership::with('reseller:id,name')->find($contract->membership_id);
            return $membership?->reseller?->name ?? 'a referrer';
        }

        $membership = PartnerProgramMembership::with('partner:id,first_name,last_name')->find($contract->membership_id);
        $partner    = $membership?->partner;
        return $partner ? (trim("{$partner->first_name} {$partner->last_name}") ?: 'a partner') : 'a partner';
    }
}
