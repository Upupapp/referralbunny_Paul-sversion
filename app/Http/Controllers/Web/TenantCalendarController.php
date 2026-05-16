<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantMembership;
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

    public function events(Request $request, string $tenantId): JsonResponse
    {
        // Validate date params — Carbon::parse throws on garbage input
        try {
            $tz   = config('app.timezone', 'UTC');
            $from = Carbon::parse($request->query('from', now($tz)->startOfMonth()->toDateString()), $tz)->startOfDay();
            $to   = Carbon::parse($request->query('to',   now($tz)->endOfMonth()->toDateString()), $tz)->endOfDay();
        } catch (\Throwable) {
            return response()->json(['error' => 'Invalid date range.'], 400);
        }

        // Clamp range to max 3 months to prevent abuse
        if ($to->diffInDays($from) > 92) {
            return response()->json(['error' => 'Date range too large.'], 400);
        }

        [$isAdmin, $actorType, $actorId] = $this->resolveRole($tenantId);

        $events = collect();

        // ── Tasks with due dates ──────────────────────────────────────────────
        $taskRows = DB::table('tasks')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->whereNotNull('due_at')
            ->whereBetween('due_at', [$from, $to])
            ->whereNotIn('status', ['cancelled', 'archived'])
            ->when(!$isAdmin, fn($q) => $q->where('assigned_to_type', $actorType)->where('assigned_to_id', $actorId))
            ->select('id', 'title', 'status', 'priority', 'category', 'due_at')
            ->orderBy('due_at')
            ->get();

        foreach ($taskRows as $task) {
            $done   = $task->status === 'completed';
            $events->push([
                'id'       => 'task-' . $task->id,
                'entity_id'=> $task->id,
                'type'     => 'task',
                'label'    => $task->category === 'request_form' ? 'Request' : 'Task',
                'title'    => $task->title,
                'date'     => Carbon::parse($task->due_at, $tz)->toDateString(),
                'status'   => $task->status,
                'priority' => $task->priority,
                'done'     => $done,
                'color'    => $this->taskColor($task->priority, $done),
                'url'      => "/tenant/{$tenantId}/tasks/{$task->id}",
            ]);
        }

        // ── Deal expiry dates (admins only) ───────────────────────────────────
        if ($isAdmin) {
            $today = Carbon::today($tz);

            $dealRows = DB::table('leads')
                ->where('tenant_id', $tenantId)
                ->whereIn('status', ['active', 'expiring'])
                ->whereNotNull('days_left')
                ->where('days_left', '>=', 0)
                ->whereNull('deleted_at')
                ->select('id', 'name', 'days_left', 'stage', 'status', 'reseller_name')
                ->orderBy('days_left')
                ->get();

            foreach ($dealRows as $deal) {
                $expiryDate = $today->copy()->addDays((int) $deal->days_left);
                if (!$expiryDate->between($from, $to)) continue;

                $daysLeft = (int) $deal->days_left;
                $events->push([
                    'id'        => 'deal-' . $deal->id,
                    'entity_id' => $deal->id,
                    'type'      => 'deal',
                    'label'     => 'Deal Expiry',
                    'title'     => $deal->name,
                    'date'      => $expiryDate->toDateString(),
                    'status'    => $deal->status,
                    'priority'  => $daysLeft <= 3 ? 'urgent' : ($daysLeft <= 7 ? 'high' : 'medium'),
                    'done'      => false,
                    'color'     => $daysLeft <= 3 ? 'red' : ($daysLeft <= 7 ? 'orange' : 'yellow'),
                    'days_left' => $daysLeft,
                    'referrer'  => $deal->reseller_name,
                    'stage'     => ucwords(str_replace('_', ' ', $deal->stage ?? '')),
                    'url'       => "/tenant/{$tenantId}/deals/{$deal->id}",
                ]);
            }
        }

        $sorted  = $events->sortBy('date')->values();
        $grouped = $sorted->groupBy('date')->map(fn($items) => $items->values())->all();

        return response()->json(['events' => $sorted, 'grouped' => $grouped]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function resolveRole(string $tenantId): array
    {
        if (Auth::guard('web')->check()) {
            return [true, 'super_admin', (string) Auth::guard('web')->id()];
        }

        $user = Auth::guard('tenant')->user();
        if (!$user) return [false, 'guest', ''];

        // Read from request attributes set by EnsureTenantAccess — avoids an extra DB query per calendar load
        $role = request()->attributes->get('_tenant_role')
            ?? TenantMembership::where('tenant_user_id', $user->id)
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->value('role');

        $isAdmin = in_array($role, ['owner', 'admin', 'manager']);
        return [$isAdmin, 'tenant_user', (string) $user->id];
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
