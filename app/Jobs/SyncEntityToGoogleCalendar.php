<?php

namespace App\Jobs;

use App\Models\GoogleCalendarIntegration;
use App\Models\Lead;
use App\Models\Task;
use App\Services\GoogleCalendarService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncEntityToGoogleCalendar implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 30;

    public function __construct(
        public readonly string $entityType, // 'task' | 'deal'
        public readonly string $entityId,
    ) {}

    public function handle(GoogleCalendarService $svc): void
    {
        match ($this->entityType) {
            'task' => $this->syncTask($svc),
            'deal' => $this->syncDeal($svc),
            default => null,
        };
    }

    private function syncTask(GoogleCalendarService $svc): void
    {
        $task = Task::whereNull('deleted_at')->find($this->entityId);
        if (!$task || !$task->due_at) return;

        // Find the integration for the assigned tenant user
        if ($task->assigned_to_type !== 'tenant_user' || !$task->assigned_to_id) return;

        $integration = GoogleCalendarIntegration::where('tenant_user_id', $task->assigned_to_id)
            ->where('is_active', true)
            ->first();

        if (!$integration) return;

        $svc->syncTask($task, $integration);
        $integration->update(['last_synced_at' => now()]);
    }

    private function syncDeal(GoogleCalendarService $svc): void
    {
        $deal = DB::table('leads')->where('id', $this->entityId)->first();
        if (!$deal || !$deal->tenant_id) return;

        // Sync to all active admin integrations for this tenant
        $adminIds = DB::table('tenant_memberships as tm')
            ->join('tenant_users as u', 'u.id', '=', 'tm.tenant_user_id')
            ->where('tm.tenant_id', $deal->tenant_id)
            ->where('tm.status', 'active')
            ->whereIn('tm.role', ['owner', 'admin', 'manager'])
            ->pluck('tm.tenant_user_id')
            ->toArray();

        if (empty($adminIds)) return;

        $integrations = GoogleCalendarIntegration::whereIn('tenant_user_id', $adminIds)
            ->where('is_active', true)
            ->get();

        foreach ($integrations as $integration) {
            try {
                $svc->syncDeal($deal, $integration);
                $integration->update(['last_synced_at' => now()]);
            } catch (\Throwable $e) {
                Log::warning('[SyncEntityToGoogleCalendar] deal sync failed', ['deal' => $deal->id, 'integration' => $integration->id, 'error' => $e->getMessage()]);
            }
        }
    }
}
