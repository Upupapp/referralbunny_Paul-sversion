<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\Reseller;
use App\Models\Tenant;
use App\Services\CriticalActionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ResellerPortalController extends Controller
{
    private function reseller(): Reseller
    {
        $reseller = Auth::guard('reseller')->user();
        if ($reseller instanceof Reseller) {
            return $reseller;
        }
        // Super admin accessing reseller portal — not supported directly.
        // Super admins should use the tenant admin portal instead.
        abort(403, 'Reseller portal requires reseller authentication.');
    }

    public function dashboard($tenantId)
    {
        $reseller = $this->reseller();
        $tenant   = Tenant::findOrFail($tenantId);

        // Load all reseller-accessible leads safely
        try {
            $leads = Lead::where('tenant_id', $tenantId)
                ->forReseller($reseller->name)
                ->orderByDesc('created_at')
                ->select('id', 'name', 'stage', 'status', 'deal_value', 'base_cost', 'added_amount', 'commission_status', 'days_left', 'reseller_name', 'created_at')
                ->get();
        } catch (\Throwable) {
            $leads = collect();
        }

        $stats = [
            'total'      => $leads->count(),
            'active'     => $leads->whereIn('status', ['active', 'expiring'])->count(),
            'expiring'   => $leads->where('status', 'expiring')->count(),
            'paid'       => $leads->where('stage', 'paid')->count(),
            'pipeline'   => $leads->sum('deal_value'),
            'conversion' => $leads->count() > 0
                ? round($leads->where('stage', 'paid')->count() / $leads->count() * 100)
                : 0,
        ];

        // ── Per-lead commission + totals (one pass) ───────────────────────
        $commissionStats  = ['pending' => 0, 'locked' => 0, 'paid' => 0];
        $leadCommissionMap = []; // lead_id → my_commission (int)
        try {
            $leadIds = $leads->pluck('id');
            $splits  = $leadIds->isNotEmpty()
                ? DB::table('commission_splits')
                    ->whereIn('lead_id', $leadIds)
                    ->whereRaw('LOWER(reseller_name) = ?', [strtolower($reseller->name)])
                    ->get()->keyBy('lead_id')
                : collect();

            $calc = app(\App\Services\CommissionCalculationService::class);
            foreach ($leads as $lead) {
                $breakdown    = $calc->breakdownFromLead($lead);
                $pool         = $breakdown['commission_pool'] ?? 0;
                $split        = $splits->get($lead->id);
                $pct          = $split ? (float) ($split->percentage ?? 100) : 100.0;
                $myCommission = (int) $calc->referrerShare($pool, $pct);
                $leadCommissionMap[$lead->id] = $myCommission;
                $status = $lead->commission_status ?? 'pending';
                if (array_key_exists($status, $commissionStats)) {
                    $commissionStats[$status] += $myCommission;
                } else {
                    $commissionStats['pending'] += $myCommission;
                }
            }
        } catch (\Throwable) {
            $commissionStats  = ['pending' => 0, 'locked' => 0, 'paid' => 0];
            $leadCommissionMap = [];
        }

        $totalCommission = $commissionStats['pending'] + $commissionStats['locked'] + $commissionStats['paid'];

        // Attach per-lead commission to recentLeads
        $recentLeads = $leads->take(6)->map(function ($lead) use ($leadCommissionMap) {
            $lead->my_commission = $leadCommissionMap[$lead->id] ?? 0;
            return $lead;
        });

        // ── Unique partner count across assigned deals ─────────────────────
        $partnerCount = 0;
        try {
            if ($leadIds->isNotEmpty()) {
                $partnerCount = DB::table('deal_partner_splits')
                    ->whereIn('deal_id', $leadIds)
                    ->whereNull('deleted_at')
                    ->where('status', '!=', 'removed')
                    ->distinct()
                    ->count('partner_email');
            }
        } catch (\Throwable) {}

        // ── Unread messages count ──────────────────────────────────────────
        $unreadCount = 0;
        try {
            $thread      = \App\Models\MessageThread::where('tenant_id', $tenantId)
                ->where('reseller_id', $reseller->id)->first();
            $unreadCount = $thread ? (int) $thread->reseller_unread : 0;
        } catch (\Throwable) {}

        // ── Recent activity (Critical Actions for this referrer, including imports) ─
        $recentActivity = [];
        try {
            $recentActivity = app(CriticalActionService::class)
                ->forReseller($tenantId, $reseller->name, 8, $reseller->id);
        } catch (\Throwable) {}

        return view('reseller.dashboard', compact(
            'reseller', 'tenant', 'stats', 'recentLeads', 'recentActivity',
            'commissionStats', 'totalCommission', 'unreadCount', 'partnerCount'
        ));
    }

    public function deals($tenantId)
    {
        $reseller = $this->reseller();
        $tenant   = Tenant::findOrFail($tenantId);
        return view('reseller.deals.index', compact('reseller', 'tenant'));
    }

    public function commission($tenantId)
    {
        $reseller = $this->reseller();
        $tenant   = Tenant::findOrFail($tenantId);

        $leads = Lead::withTrashed()
            ->where('tenant_id', $tenantId)
            ->forReseller($reseller->name)
            ->select('id', 'name', 'stage', 'deal_value', 'base_cost', 'added_amount', 'commission_status', 'reseller_name', 'deleted_at')
            ->get();

        // Load this referrer's commission splits to get their percentage per deal
        $leadIds = $leads->pluck('id');
        $splits  = DB::table('commission_splits')
            ->whereIn('lead_id', $leadIds)
            ->whereRaw('LOWER(reseller_name) = ?', [strtolower($reseller->name)])
            ->get()
            ->keyBy('lead_id');

        // Attach computed commission amounts to each lead
        $calc  = app(\App\Services\CommissionCalculationService::class);
        $leads = $leads->map(function ($lead) use ($splits, $calc) {
            $breakdown  = $calc->breakdownFromLead($lead);
            $pool       = $breakdown['commission_pool'];
            $splitRow   = $splits->get($lead->id);
            $pct        = $splitRow ? (float) ($splitRow->percentage ?? 100) : 100.0;
            $lead->commission_pool  = $pool;
            $lead->my_commission    = $calc->referrerShare($pool, $pct);
            $lead->split_percentage = $pct;
            return $lead;
        });

        // Summary cards show referrer's actual commission share (not deal value)
        $commissionStats = [
            'pending' => $leads->where('commission_status', 'pending')->sum('my_commission'),
            'locked'  => $leads->where('commission_status', 'locked')->sum('my_commission'),
            'paid'    => $leads->where('commission_status', 'paid')->sum('my_commission'),
        ];

        // Paginate for display — stats use full $leads collection above
        $perPage  = 20;
        $page     = (int) request()->input('page', 1);
        $pagedLeads = new \Illuminate\Pagination\LengthAwarePaginator(
            $leads->forPage($page, $perPage)->values(),
            $leads->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );

        return view('reseller.commission', compact('reseller', 'tenant', 'leads', 'commissionStats', 'pagedLeads'));
    }

    public function profile($tenantId)
    {
        $reseller = $this->reseller();
        $tenant   = Tenant::findOrFail($tenantId);
        return view('reseller.profile', compact('reseller', 'tenant'));
    }

    public function requestForms($tenantId)
    {
        $reseller = $this->reseller();
        $tenant   = Tenant::findOrFail($tenantId);

        $forms = collect();
        try {
            $forms = \App\Models\RequestForm::where('tenant_id', $tenantId)
                ->where('status', 'published')
                ->whereNull('deleted_at')
                ->orderBy('title')
                ->get(['id', 'title', 'description', 'public_token', 'published_at']);
        } catch (\Throwable) {}

        return view('reseller.request-forms', compact('reseller', 'tenant', 'forms'));
    }

    public function messages($tenantId)
    {
        $reseller = $this->reseller();
        $tenant   = Tenant::findOrFail($tenantId);

        try {
            $thread = \App\Models\MessageThread::where('tenant_id', $tenantId)
                ->where('reseller_id', $reseller->id)
                ->first();

            $messages = $thread
                ? \App\Models\ThreadMessage::where('thread_id', $thread->id)
                    ->orderBy('created_at')
                    ->limit(100)
                    ->get()
                : collect();

            // Mark reseller's unread messages as read
            if ($thread && $thread->reseller_unread > 0) {
                $thread->update(['reseller_unread' => 0]);
                \App\Models\ThreadMessage::where('thread_id', $thread->id)
                    ->where('sender_type', 'admin')
                    ->where('is_read', false)
                    ->update(['is_read' => true, 'read_at' => now()]);
            }
        } catch (\Throwable) {
            $thread   = null;
            $messages = collect();
        }

        return view('reseller.messages', compact('reseller', 'tenant', 'thread', 'messages'));
    }

    public function notifications($tenantId)
    {
        $reseller = $this->reseller();
        $tenant   = Tenant::findOrFail($tenantId);
        return view('reseller.notifications', compact('reseller', 'tenant'));
    }

    public function activityLog($tenantId)
    {
        $reseller = $this->reseller();
        $tenant   = Tenant::findOrFail($tenantId);
        $filter   = request('filter', 'all'); // all | deals | commission | system
        $perPage  = 25;
        $page     = max(1, (int) request('page', 1));
        $offset   = ($page - 1) * $perPage;

        // Get all lead IDs belonging to this reseller
        $leadIds = [];
        try {
            $leadIds = Lead::withTrashed()
                ->where('tenant_id', $tenantId)
                ->forReseller($reseller->name)
                ->pluck('id')
                ->map(fn($id) => (string) $id)
                ->toArray();
        } catch (\Throwable) {}

        // Build activity from two sources then merge
        $items = collect();

        // ── Source 1: lead_history (stage changes, status updates, commission) ─
        // 'commission' filter also pulls lead_history rows with category commission/financial
        $lhCategories = match($filter) {
            'commission' => ['commission', 'financial'],
            'all', 'deals' => null, // null = no category filter
            default => null,
        };
        $lhShouldRun = in_array($filter, ['all', 'deals', 'commission']) && count($leadIds) > 0;

        if ($lhShouldRun) {
            try {
                $lhQuery = DB::table('lead_history')
                    ->leftJoin('leads', 'lead_history.lead_id', '=', 'leads.id')
                    ->where('lead_history.tenant_id', $tenantId)
                    ->whereIn('lead_history.lead_id', $leadIds)
                    ->select(
                        'lead_history.id',
                        'lead_history.lead_id',
                        'lead_history.action',
                        'lead_history.category',
                        'lead_history.actor_name',
                        'lead_history.actor_role',
                        'lead_history.old_values',
                        'lead_history.new_values',
                        'lead_history.created_at',
                        'leads.name as lead_name',
                        'leads.deal_value',
                    );

                if ($lhCategories !== null) {
                    $lhQuery->whereIn('lead_history.category', $lhCategories);
                }

                $rows = $lhQuery
                    ->orderByDesc('lead_history.created_at')
                    ->limit(300)
                    ->get();

                foreach ($rows as $r) {
                    $old = is_string($r->old_values) ? json_decode($r->old_values, true) : (array)($r->old_values ?? []);
                    $new = is_string($r->new_values) ? json_decode($r->new_values, true) : (array)($r->new_values ?? []);

                    [$title, $detail] = $this->formatLeadHistoryItem($r->action, $r->category, $old, $new, $r->actor_name, $r->actor_role);

                    $items->push([
                        'id'         => 'lh_' . $r->id,
                        'icon_type'  => $this->leadHistoryIconType($r->category, $r->action),
                        'title'      => $title,
                        'detail'     => $detail,
                        'deal_name'  => $r->lead_name ?? 'Deal',
                        'lead_id'    => $r->lead_id,
                        'created_at' => $r->created_at,
                        'category'   => $this->friendlyCategory($r->category, $r->action),
                    ]);
                }
            } catch (\Throwable) {}
        }

        // ── Source 2: activity_logs (comments, commission, extensions) ────
        $actActions = match($filter) {
            'deals'      => ['deal_comment_created','deal_comment_edited','deal_comment_deleted',
                             'deal_extension_requested','deal_extension_approved','deal_extension_rejected','deal_extension_clarification_requested'],
            'commission' => ['partner_split_created','partner_split_removed'],
            'system'     => ['invite_accepted','invite.accepted','referrer_deactivated','referrer_double_auth_failed'],
            'imports'    => ['deal_import_completed','deal_import_uploaded','contacts_import_completed','contacts_import_uploaded'],
            default      => [], // all — no action filter
        };

        try {
            $query = DB::table('activity_logs')
                ->where('tenant_id', $tenantId)
                ->where(function ($q) use ($reseller, $leadIds) {
                    $q->where(fn($q2) => $q2->where('entity', 'reseller')->where('entity_id', (string) $reseller->id));
                    if (count($leadIds) > 0) {
                        $q->orWhere(fn($q2) => $q2->where('entity', 'lead')->whereIn('entity_id', $leadIds));
                    }
                })
                ->when($actActions, fn($q) => $q->whereIn('action', $actActions))
                ->orderByDesc('created_at')
                ->limit(300)
                ->get();

            // Cache lead names for activity_logs
            $leadNames = count($leadIds) > 0
                ? DB::table('leads')->whereIn('id', $leadIds)->pluck('name', 'id')->all()
                : [];

            foreach ($query as $r) {
                $meta = is_string($r->metadata) ? json_decode($r->metadata, true) : (array)($r->metadata ?? []);
                [$title, $detail, $iconType, $cat] = $this->formatActivityLogItem($r->action, $meta, $r->entity, $r->entity_id, $leadNames, $reseller->name);

                $items->push([
                    'id'         => 'al_' . $r->id,
                    'icon_type'  => $iconType,
                    'title'      => $title,
                    'detail'     => $detail,
                    'deal_name'  => ($r->entity === 'lead' ? ($leadNames[$r->entity_id] ?? null) : null),
                    'lead_id'    => ($r->entity === 'lead' ? $r->entity_id : null),
                    'created_at' => $r->created_at,
                    'category'   => $cat,
                ]);
            }
        } catch (\Throwable) {}

        // Sort merged items by created_at desc, paginate in PHP
        $sorted = $items->sortByDesc('created_at')->values();
        $total  = $sorted->count();
        $paged  = $sorted->slice($offset, $perPage)->values();
        $pages  = (int) ceil($total / $perPage);

        // Humanise timestamps
        $paged = $paged->map(function ($item) {
            try {
                $dt = \Carbon\Carbon::parse($item['created_at']);
                $item['time_ago'] = $dt->diffForHumans();
                $item['time_fmt'] = $dt->format('M j, Y g:i A');
            } catch (\Throwable) {
                $item['time_ago'] = '—';
                $item['time_fmt'] = '';
            }
            return $item;
        });

        return view('reseller.activity', compact(
            'reseller', 'tenant', 'paged', 'total', 'page', 'pages', 'filter'
        ));
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function formatLeadHistoryItem(string $action, ?string $category, array $old, array $new, ?string $actor, ?string $role): array
    {
        $actor = $actor ?: 'System';
        $role  = $role  ? " ({$role})" : '';

        if (isset($new['stage']) && isset($old['stage']) && $new['stage'] !== $old['stage']) {
            $from = ucfirst(str_replace('_', ' ', $old['stage']));
            $to   = ucfirst(str_replace('_', ' ', $new['stage']));
            return ["Stage advanced to {$to}", "From {$from} · by {$actor}{$role}"];
        }
        if (isset($new['status']) && isset($old['status']) && $new['status'] !== $old['status']) {
            $status = ucfirst($new['status']);
            return ["Status changed to {$status}", "By {$actor}{$role}"];
        }
        if ($category === 'commission' || str_contains($action, 'commission')) {
            $status = $new['status'] ?? null;
            if ($status === 'locked') return ['Commission approved', "By {$actor}{$role}"];
            if ($status === 'paid')   return ['Commission paid',     "By {$actor}{$role}"];
            return [ucfirst(str_replace(['_', '.'], ' ', $action)), "By {$actor}{$role}"];
        }
        if ($category === 'financial' || str_contains($action, 'financial')) {
            return ['Contract value updated', "By {$actor}{$role}"];
        }
        if ($category === 'partner') {
            return [ucfirst(str_replace(['_', '.'], ' ', $action)), "By {$actor}{$role}"];
        }
        if (str_contains($action, 'comment')) {
            return ['Comment added on deal', "By {$actor}{$role}"];
        }
        if (str_contains($action, 'extension')) {
            return ['Deal extension ' . str_replace('_', ' ', $action), "By {$actor}{$role}"];
        }
        $label = ucfirst(str_replace(['_', '.'], ' ', $action));
        return [$label, "By {$actor}{$role}"];
    }

    private function leadHistoryIconType(?string $category, string $action): string
    {
        if (str_contains($action, 'stage') || str_contains((string)$category, 'stage'))       return 'stage';
        if (str_contains($action, 'status'))                                                    return 'status';
        if (str_contains($action, 'comment'))                                                   return 'comment';
        if (str_contains($action, 'extension'))                                                 return 'extension';
        if (in_array($category, ['commission', 'financial']) || str_contains($action, 'commission')) return 'commission';
        if ($category === 'partner')                                                            return 'commission';
        return 'deal';
    }

    private function friendlyCategory(?string $category, string $action): string
    {
        if ($category) return ucfirst(str_replace('_', ' ', $category));
        if (str_contains($action, 'comment'))   return 'Comment';
        if (str_contains($action, 'extension')) return 'Extension';
        if (str_contains($action, 'stage'))     return 'Stage Change';
        return 'Deal Update';
    }

    private function formatActivityLogItem(string $action, array $meta, ?string $entity, ?string $entityId, array $leadNames, string $resellerName): array
    {
        $leadName = ($entity === 'lead' && $entityId) ? ($leadNames[$entityId] ?? 'a deal') : 'a deal';

        return match(true) {
            $action === 'deal_comment_created'   => ['Comment added on "' . $leadName . '"', $meta['comment_preview'] ?? null, 'comment', 'Comment'],
            $action === 'deal_comment_edited'    => ['Comment edited on "' . $leadName . '"', null, 'comment', 'Comment'],
            $action === 'deal_comment_deleted'   => ['Comment removed on "' . $leadName . '"', null, 'comment', 'Comment'],
            $action === 'deal_extension_requested'   => ['Extension requested for "' . $leadName . '"', $meta['reason'] ?? null, 'extension', 'Extension'],
            $action === 'deal_extension_approved'    => ['Extension approved for "' . $leadName . '"', null, 'extension', 'Extension'],
            $action === 'deal_extension_rejected'    => ['Extension rejected for "' . $leadName . '"', $meta['reason'] ?? null, 'extension', 'Extension'],
            $action === 'deal_extension_clarification_requested' => ['Clarification requested for "' . $leadName . '"', null, 'extension', 'Extension'],
            $action === 'partner_split_created'  => ['Commission split recorded', ($meta['deal_name'] ?? null) ? 'On deal ' . $meta['deal_name'] : null, 'commission', 'Commission'],
            $action === 'partner_split_removed'  => ['Commission split removed', null, 'commission', 'Commission'],
            in_array($action, ['invite_accepted','invite.accepted']) => ['You joined as a referrer', 'Welcome to ' . ($meta['tenant_name'] ?? 'the workspace') . '!', 'system', 'System'],
            // ── Import events ─────────────────────────────────────────────
            $action === 'deal_import_completed' => [
                'Deal import completed',
                isset($meta['file_name'])
                    ? $meta['file_name'] . ' · ' . ($meta['created'] ?? 0) . ' created, ' . ($meta['skipped'] ?? 0) . ' skipped, ' . ($meta['failed'] ?? 0) . ' failed'
                    : null,
                'import', 'Imports',
            ],
            $action === 'deal_import_uploaded' => [
                'Deal import file uploaded',
                isset($meta['file_name']) ? $meta['file_name'] . ' — awaiting review' : null,
                'import', 'Imports',
            ],
            $action === 'contacts_import_completed' => [
                'Contacts import completed',
                isset($meta['file_name'])
                    ? $meta['file_name'] . ' · ' . ($meta['created'] ?? 0) . ' added, ' . ($meta['skipped'] ?? 0) . ' skipped, ' . ($meta['failed'] ?? 0) . ' failed'
                    : null,
                'import', 'Imports',
            ],
            $action === 'contacts_import_uploaded' => [
                'Contacts import file uploaded',
                isset($meta['file_name']) ? $meta['file_name'] . ' — awaiting review' : null,
                'import', 'Imports',
            ],
            str_contains($action, 'referrer_')   => [ucfirst(str_replace(['_','.'], ' ', $action)), null, 'system', 'System'],
            default                              => [ucfirst(str_replace(['_','.'], ' ', $action)), null, 'deal', 'Activity'],
        };
    }

    // ── Referrer Tasks ────────────────────────────────────────────────────────

    /**
     * List tasks assigned to this referrer.
     * HARD RULE: always double-scoped to assigned_to_type='reseller' + assigned_to_id=$reseller->id.
     * Referrers can NEVER see or manage tasks belonging to other users.
     */
    public function tasks(string $tenantId): \Illuminate\View\View
    {
        $reseller = $this->reseller();
        $tenant   = Tenant::findOrFail($tenantId);
        $tab      = request('tab', 'open'); // open | completed

        // HARD RULE base scope — never changes
        $base = fn() => \App\Models\Task::where('tenant_id', $tenantId)
            ->where('assigned_to_type', 'reseller')
            ->where('assigned_to_id', $reseller->id)
            ->whereNull('deleted_at');

        if ($tab === 'completed') {
            $tasks = $base()->where('status', 'completed')
                ->orderByDesc('completed_at')
                ->paginate(25)->appends(['tab' => 'completed']);
        } else {
            $tasks = $base()->whereIn('status', ['open', 'in_progress', 'waiting'])
                ->orderByRaw("CASE WHEN priority='urgent' THEN 0 WHEN priority='high' THEN 1 WHEN priority='medium' THEN 2 ELSE 3 END")
                ->orderByRaw("CASE WHEN status='in_progress' THEN 0 WHEN status='waiting' THEN 1 ELSE 2 END")
                ->orderBy('due_at')
                ->orderByDesc('created_at')
                ->paginate(25)->appends(['tab' => 'open']);
        }

        $openCount = $base()->whereIn('status', ['open', 'in_progress', 'waiting'])->count();

        return view('reseller.tasks', compact('reseller', 'tenant', 'tasks', 'tab', 'openCount'));
    }

    /**
     * Referrer creates a task for themselves.
     * Task is assigned to the referrer, visible to admins/managers + the referrer only.
     * HARD RULE: always assigned_to_type='reseller', assigned_to_id=$reseller->id.
     */
    public function taskStore(Request $request, string $tenantId): \Illuminate\Http\JsonResponse
    {
        $reseller = $this->reseller();
        $tenant   = Tenant::findOrFail($tenantId);

        $data = $request->validate([
            'title'       => 'required|string|max:200',
            'description' => 'nullable|string|max:2000',
            'priority'    => 'required|in:low,medium,high,urgent',
            'due_at'      => 'nullable|date',
        ]);

        $task = \App\Models\Task::create([
            'tenant_id'        => $tenantId,
            'title'            => $data['title'],
            'description'      => $data['description'] ?? null,
            'status'           => 'open',
            'priority'         => $data['priority'],
            'category'         => 'manual',
            'assigned_to_type' => 'reseller',
            'assigned_to_id'   => (string) $reseller->id,
            'assigned_by_type' => 'reseller',
            'assigned_by_id'   => (string) $reseller->id,
            'created_by_type'  => 'reseller',
            'created_by_id'    => (string) $reseller->id,
            'due_at'           => !empty($data['due_at']) ? $data['due_at'] : null,
            'visibility'       => 'tenant_team',
        ]);

        \App\Models\TaskActivity::create([
            'tenant_id'   => $tenantId,
            'task_id'     => $task->id,
            'actor_type'  => 'reseller',
            'actor_id'    => (string) $reseller->id,
            'actor_name'  => $reseller->name ?? $reseller->email,
            'action_type' => 'task_created_self_assigned',
            'new_values'  => ['title' => $task->title, 'priority' => $task->priority, 'self_assign' => true],
        ]);

        // Notify tenant admins that a referrer created a task
        try {
            app(\App\Services\NotificationDispatchService::class)->dispatchToTenantAdmins(
                tenantId:     $tenantId,
                category:     'task',
                priority:     'normal',
                title:        'Referrer created a task',
                body:         ($reseller->name ?? $reseller->email) . " created a task: \"{$task->title}\".",
                actionUrl:    "/tenant/{$tenantId}/tasks/{$task->id}",
                actionLabel:  'View Task',
                dedupeSuffix: "task_referrer_created_{$task->id}",
            );
        } catch (\Throwable) {}

        return response()->json([
            'message' => 'Task created.',
            'task_id' => $task->id,
        ]);
    }

    /**
     * Mark a referrer's own task as completed.
     * HARD RULE: task must be owned by this reseller in this tenant.
     */
    public function taskComplete(Request $request, string $tenantId, string $taskId): \Illuminate\Http\JsonResponse
    {
        $reseller = $this->reseller();

        $task = \App\Models\Task::where('tenant_id', $tenantId)
            ->where('assigned_to_type', 'reseller')
            ->where('assigned_to_id', $reseller->id)
            ->whereNull('deleted_at')
            ->findOrFail($taskId);

        if (!$task->isCompletable()) {
            return response()->json(['error' => 'Task is already completed.'], 422);
        }

        app(\App\Services\TaskCompletionService::class)
            ->complete($task, 'reseller', (string) $reseller->id, $reseller->name ?? $reseller->email);

        try {
            app(\App\Services\NotificationDispatchService::class)->dispatchToTenantAdmins(
                tenantId:     $tenantId,
                category:     'task',
                priority:     'normal',
                title:        'Task completed by Referrer',
                body:         ($reseller->name ?? $reseller->email) . " completed: \"{$task->title}\".",
                actionUrl:    "/tenant/{$tenantId}/tasks/{$task->id}",
                actionLabel:  'View Task',
                dedupeSuffix: "task_referrer_done_{$task->id}",
            );
        } catch (\Throwable) {}

        return response()->json(['status' => 'completed', 'message' => 'Task marked as complete.']);
    }

    /**
     * Update status of a referrer's own task (open ↔ in_progress ↔ waiting).
     * HARD RULE: task must be owned by this reseller in this tenant.
     * Completion is handled separately by taskComplete(); not allowed here.
     */
    public function taskUpdateStatus(Request $request, string $tenantId, string $taskId): \Illuminate\Http\JsonResponse
    {
        $reseller = $this->reseller();

        $task = \App\Models\Task::where('tenant_id', $tenantId)
            ->where('assigned_to_type', 'reseller')
            ->where('assigned_to_id', $reseller->id)
            ->whereNull('deleted_at')
            ->findOrFail($taskId);

        $data = $request->validate([
            'status' => ['required', 'in:open,in_progress,waiting'],
        ]);

        $oldStatus = $task->status;
        $newStatus = $data['status'];

        if ($oldStatus === $newStatus) {
            return response()->json(['status' => $oldStatus]);
        }

        if (in_array($oldStatus, ['completed', 'cancelled', 'archived'])) {
            return response()->json(['error' => 'Cannot reopen a closed task.'], 422);
        }

        DB::transaction(function () use ($task, $newStatus, $oldStatus, $reseller, $tenantId) {
            $updateData = ['status' => $newStatus];
            if ($newStatus === 'in_progress' && !$task->started_at) {
                $updateData['started_at'] = now();
            }
            $task->update($updateData);

            \App\Models\TaskActivity::create([
                'tenant_id'   => $tenantId,
                'task_id'     => $task->id,
                'actor_type'  => 'reseller',
                'actor_id'    => (string) $reseller->id,
                'actor_name'  => $reseller->name ?? $reseller->email,
                'action_type' => 'task_status_changed',
                'old_values'  => ['status' => $oldStatus],
                'new_values'  => ['status' => $newStatus],
                'metadata'    => ['source' => 'referrer_portal'],
            ]);
        });

        return response()->json(['status' => $newStatus]);
    }

    public function calendar(string $tenantId): \Illuminate\View\View
    {
        $reseller = $this->reseller();
        $tenant   = Tenant::findOrFail($tenantId);
        return view('reseller.calendar', compact('reseller', 'tenant'));
    }

    public function calendarEvents(Request $request, string $tenantId): \Illuminate\Http\JsonResponse
    {
        $reseller = $this->reseller();
        $tz       = config('app.timezone', 'UTC');

        try {
            $from = \Illuminate\Support\Carbon::parse($request->query('from', now($tz)->startOfMonth()->toDateString()), $tz)->startOfDay();
            $to   = \Illuminate\Support\Carbon::parse($request->query('to',   now($tz)->endOfMonth()->toDateString()),   $tz)->endOfDay();
        } catch (\Throwable) {
            return response()->json(['error' => 'Invalid date range.'], 400);
        }

        if ($from->diffInDays($to) > 92) {
            return response()->json(['error' => 'Date range cannot exceed 92 days.'], 400);
        }

        $today   = \Illuminate\Support\Carbon::today($tz);
        $events  = collect();

        // ── Own deals (by expiry date) ────────────────────────────────────────
        Lead::where('tenant_id', $tenantId)
            ->forReseller($reseller->name)
            ->whereIn('status', ['active', 'expiring'])
            ->whereNotNull('days_left')
            ->where('days_left', '>=', 0)
            ->select('id', 'name', 'days_left', 'stage', 'status')
            ->get()
            ->each(function ($deal) use ($from, $to, $today, $tenantId, &$events) {
                $expiry = $today->copy()->addDays((int) $deal->days_left);
                if (!$expiry->between($from, $to)) return;
                $d = (int) $deal->days_left;
                $events->push([
                    'id'       => 'deal-' . $deal->id,
                    'type'     => 'deal',
                    'title'    => $deal->name,
                    'label'    => 'Deal Expiry',
                    'date'     => $expiry->toDateString(),
                    'days_left'=> $d,
                    'stage'    => ucwords(str_replace('_', ' ', $deal->stage ?? '')),
                    'status'   => $deal->status,
                    'priority' => $d <= 3 ? 'urgent' : ($d <= 7 ? 'high' : 'medium'),
                    'color'    => $d <= 3 ? 'red' : ($d <= 7 ? 'orange' : 'yellow'),
                    'done'     => false,
                    'url'      => "/reseller/{$tenantId}/deals/{$deal->id}",
                ]);
            });

        // ── Own tasks (by due date) ───────────────────────────────────────────
        DB::table('tasks')
            ->where('tenant_id', $tenantId)
            ->where('assigned_to_type', 'reseller')
            ->where('assigned_to_id', (string) $reseller->id)
            ->whereNull('deleted_at')
            ->whereNotNull('due_at')
            ->whereNotIn('status', ['cancelled', 'archived'])
            ->whereBetween('due_at', [$from, $to])
            ->select('id', 'title', 'status', 'priority', 'due_at')
            ->get()
            ->each(function ($task) use ($today, $tenantId, &$events) {
                $due  = \Illuminate\Support\Carbon::parse($task->due_at);
                $done = $task->status === 'completed';
                $overdue = !$done && $due->lt($today);
                $events->push([
                    'id'       => 'task-' . $task->id,
                    'type'     => 'task',
                    'title'    => $task->title,
                    'label'    => 'Task',
                    'date'     => $due->toDateString(),
                    'priority' => $task->priority,
                    'status'   => $task->status,
                    'done'     => $done,
                    'overdue'  => $overdue,
                    'color'    => $done ? 'gray' : ($overdue ? 'red' : match($task->priority) {
                        'urgent' => 'red', 'high' => 'orange', 'medium' => 'purple', default => 'purple',
                    }),
                    'url'      => "/reseller/{$tenantId}/tasks",
                ]);
            });

        $sorted  = $events->sortBy('date')->values();
        $grouped = $sorted->groupBy('date')->map(fn($g) => $g->values())->all();

        return response()->json(['events' => $sorted, 'grouped' => $grouped]);
    }
}
