<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\Tenant;
use App\Services\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TenantCalendarController extends Controller
{
    public function index(string $tenantId)
    {
        $tenant = Tenant::findOrFail($tenantId);
        return view('tenant.calendar.index', compact('tenant', 'tenantId'));
    }

    /**
     * JSON endpoint: return all calendar events for a date window.
     * Used by Alpine.js when navigating months.
     */
    public function events(Request $request, string $tenantId): JsonResponse
    {
        $from    = Carbon::parse($request->query('from', now()->startOfMonth()))->startOfDay();
        $to      = Carbon::parse($request->query('to',   now()->endOfMonth()))->endOfDay();
        $isAdmin = Auth::guard('web')->check()
            || in_array(TenantContext::role(), ['admin', 'owner', 'manager']);

        [$actorType, $actorId] = $this->resolveActor();

        $events = collect();

        // ── Tasks with due dates ──────────────────────────────────────────────
        $taskQuery = Task::where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->whereNotNull('due_at')
            ->whereBetween('due_at', [$from, $to])
            ->whereNotIn('status', ['cancelled', 'archived']);

        if (!$isAdmin) {
            $taskQuery->where('assigned_to_type', $actorType)
                      ->where('assigned_to_id', $actorId);
        }

        $taskQuery->select('id', 'title', 'status', 'priority', 'category', 'due_at', 'assigned_to_id', 'assigned_to_type')
            ->orderBy('due_at')
            ->each(function ($task) use ($tenantId, &$events) {
                $isComplete = $task->status === 'completed';
                $events->push([
                    'id'       => 'task-' . $task->id,
                    'entity_id'=> $task->id,
                    'type'     => 'task',
                    'label'    => $task->category === 'request_form' ? 'Request' : 'Task',
                    'title'    => $task->title,
                    'date'     => $task->due_at->toDateString(),
                    'status'   => $task->status,
                    'priority' => $task->priority,
                    'done'     => $isComplete,
                    'color'    => $this->taskColor($task->priority, $isComplete),
                    'url'      => "/tenant/{$tenantId}/tasks/{$task->id}",
                ]);
            });

        // ── Deal expiry dates (admins only) ───────────────────────────────────
        if ($isAdmin) {
            DB::table('leads')
                ->where('tenant_id', $tenantId)
                ->whereIn('status', ['active', 'expiring'])
                ->whereNotNull('days_left')
                ->where('days_left', '>', 0)
                ->whereNull('deleted_at')
                ->select('id', 'name', 'days_left', 'stage', 'status', 'reseller_name', 'deal_value')
                ->orderBy('days_left')
                ->each(function ($deal) use ($from, $to, $tenantId, &$events) {
                    $expiryDate = Carbon::today()->addDays($deal->days_left);
                    if (!$expiryDate->between($from, $to)) return;

                    $events->push([
                        'id'        => 'deal-' . $deal->id,
                        'entity_id' => $deal->id,
                        'type'      => 'deal',
                        'label'     => 'Deal Expiry',
                        'title'     => $deal->name,
                        'date'      => $expiryDate->toDateString(),
                        'status'    => $deal->status,
                        'priority'  => $deal->days_left <= 3 ? 'urgent' : ($deal->days_left <= 7 ? 'high' : 'medium'),
                        'done'      => false,
                        'color'     => $deal->days_left <= 3 ? 'red' : ($deal->days_left <= 7 ? 'orange' : 'yellow'),
                        'days_left' => $deal->days_left,
                        'referrer'  => $deal->reseller_name,
                        'stage'     => ucwords(str_replace('_', ' ', $deal->stage ?? '')),
                        'url'       => "/tenant/{$tenantId}/deals/{$deal->id}",
                    ]);
                });
        }

        // Group by date for efficient frontend rendering
        $grouped = $events->groupBy('date')->map(fn($items) => $items->values())->all();

        return response()->json([
            'events'  => $events->sortBy('date')->values(),
            'grouped' => $grouped,
        ]);
    }

    private function resolveActor(): array
    {
        if ($user = Auth::guard('tenant')->user()) {
            return ['tenant_user', (string) $user->id];
        }
        if ($user = Auth::guard('web')->user()) {
            return ['super_admin', (string) $user->id];
        }
        return ['guest', ''];
    }

    private function taskColor(string $priority, bool $done): string
    {
        if ($done) return 'gray';
        return match ($priority) {
            'urgent' => 'red',
            'high'   => 'orange',
            'medium' => 'purple',
            default  => 'blue',
        };
    }
}
