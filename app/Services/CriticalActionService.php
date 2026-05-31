<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Aggregates critical actions from existing tables.
 * No separate event table — queries live data per request.
 * All queries are tenant-scoped and permission-safe.
 */
class CriticalActionService
{
    // Severity ordering for sorting — 'normal' treated as alias for 'low'
    private const SEVERITY_ORDER = ['urgent' => 0, 'high' => 1, 'medium' => 2, 'low' => 3, 'normal' => 3, 'info' => 4];

    // Per-request memoization: prevents redundant DB+cache round-trips when multiple
    // callsites bust the same tenant in the same HTTP request (e.g. moveStage path).
    // WARNING: queue workers are long-lived processes — call resetRequestMemo() at the
    // top of every ShouldQueue listener/job handle() to prevent cross-job memo bleed.
    private static array $bustedThisRequest = [];

    private static function tableExists(string $table): bool
    {
        return Cache::remember("schema_table_exists:{$table}", 300, fn() => Schema::hasTable($table));
    }

    // ── Public API ─────────────────────────────────────────────────

    /**
     * Lightweight badge counter — returns ['count' => N, 'has_urgent' => bool].
     *
     * Issues ~10 direct COUNT(*) queries against indexed columns.
     * No full action-object loading, no PHP-side array_filter() over 100+ items.
     * The suppressed-badge path uses has_urgent to decide whether to show 0 or the full count.
     *
     * The caller (CriticalActionsController::badge / _nav.blade.php) wraps this in
     * a 60-second Cache::remember(ca_badge_{tenantId}_{userId}).
     *
     * @return array{count: int, has_urgent: bool}
     */
    public function badgeCount(string $tenantId, bool $canSeeBilling = false, bool $canSeeExports = true, bool $canSeeUsers = true, bool $canViewReferrers = true): array
    {
        $count     = 0;
        $hasUrgent = false;

        // 1. Expiring deals — idx_leads_tenant_status_active (tenant_id, status) WHERE deleted_at IS NULL
        //    days_left <= 2 signals urgent severity in a single query (no extra round-trip).
        try {
            $row = DB::table('leads')
                ->where('tenant_id', $tenantId)
                ->where('status', 'expiring')
                ->whereNull('deleted_at')
                ->selectRaw('COUNT(*) as total, SUM(CASE WHEN days_left <= 2 THEN 1 ELSE 0 END) as urgent_ct')
                ->first();
            if ($row) {
                $count    += (int) $row->total;
                $hasUrgent = $hasUrgent || ((int) $row->urgent_ct > 0);
            }
        } catch (\Throwable) {}

        // 1b. Expired deals — must also trigger has_urgent
        // Exclude deals already counted by block 8 (signed/paid + pending commission)
        // to prevent double-counting in the badge and duplicate items in the panel.
        try {
            $expiredCount = DB::table('leads')
                ->where('tenant_id', $tenantId)
                ->where('status', 'expired')
                ->where('updated_at', '>', now()->subDays(7))
                ->whereNull('deleted_at')
                ->where(fn($q) =>
                    $q->whereNotIn('stage', ['signed', 'paid'])
                      ->orWhere('commission_status', '!=', 'pending')
                )
                ->count();
            if ($expiredCount > 0) {
                $count    += $expiredCount;
                $hasUrgent = true;
            }
        } catch (\Throwable) {}

        // 2. Pending archive + stage-move requests, plus clarification_requested with a referrer reply
        try {
            $count += DB::table('deal_approval_requests')
                ->where('tenant_id', $tenantId)
                ->whereIn('type', ['deal_archive', 'deal_stage_move'])
                ->where(fn($q) => $q
                    ->where('status', 'pending')
                    ->orWhere(fn($q2) => $q2->where('status', 'clarification_requested')->whereNotNull('visible_response'))
                )
                ->count();
        } catch (\Throwable) {}

        // 3a. Standalone pending/clarification extension requests (not batch items)
        // 'skipped' excluded — those items are not rendered in the CA panel, so counting them here inflates the badge
        try {
            $count += DB::table('deal_assignment_extension_requests')
                ->where('tenant_id', $tenantId)
                ->whereNull('batch_id')
                ->whereIn('status', ['pending_review', 'clarification_requested'])
                ->count();
        } catch (\Throwable) {}

        // 3b. Pending bulk extension request batches (counted as 1 per batch, not per item)
        try {
            if (self::tableExists('deal_extension_request_batches')) {
                $count += DB::table('deal_extension_request_batches')
                    ->where('tenant_id', $tenantId)
                    ->whereIn('status', ['pending', 'partially_approved', 'partially_declined'])
                    ->count();
            }
        } catch (\Throwable) {}

        // 4. Import batches needing action — idx_import_batches_tenant_status_created
        try {
            $count += DB::table('import_batches')
                ->where('tenant_id', $tenantId)
                ->where('created_at', '>', now()->subDays(14))
                ->where(fn($q) => $q
                    ->whereIn('status', ['previewed', 'failed'])
                    ->orWhere(fn($q2) => $q2->where('status', 'processing')->where('started_at', '<', now()->subMinutes(15)))
                )
                ->count();
        } catch (\Throwable) {}

        // 5. Unreplied message threads — idx_message_threads_admin_unread
        try {
            $count += DB::table('message_threads as t')
                ->where('t.tenant_id', $tenantId)
                ->where('t.admin_unread', '>', 0)
                ->whereExists(fn($q) => $q->select(DB::raw(1))
                    ->from('thread_messages as m')
                    ->whereColumn('m.thread_id', 't.id')
                    ->where('m.sender_type', 'reseller')
                    ->where('m.created_at', '<', now()->subHours(24))
                )
                ->count();
        } catch (\Throwable) {}

        // 6 & 7. Overdue + request-form tasks — idx_tasks_tenant_status_due_pending
        if (self::tableExists('tasks')) {
            try {
                $count += DB::table('tasks')
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at')
                    ->whereNotIn('status', ['completed', 'cancelled', 'archived'])
                    ->where('category', '!=', 'request_form')
                    ->where('due_at', '<', now())
                    ->count();
            } catch (\Throwable) {}

            try {
                $count += DB::table('tasks')
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at')
                    ->where('status', 'open')
                    ->where('category', 'request_form')
                    ->count();
            } catch (\Throwable) {}
        }

        // 8. Commission review queue — idx_leads_commission_review_queue
        try {
            $count += DB::table('leads')
                ->where('tenant_id', $tenantId)
                ->whereIn('stage', ['signed', 'paid'])
                ->where('commission_status', 'pending')
                ->whereNull('deleted_at')
                ->count();
        } catch (\Throwable) {}

        // 9. Pending export requests (gated by canSeeExports)
        if ($canSeeExports) {
            try {
                $count += DB::table('export_requests')
                    ->where('tenant_id', $tenantId)
                    ->whereIn('status', ['pending', 'failed'])
                    ->count();
            } catch (\Throwable) {}
        }

        // 10. Billing issues (gated by canSeeBilling) — suspended = urgent
        if ($canSeeBilling) {
            try {
                $sub = DB::table('subscriptions')
                    ->where('tenant_id', $tenantId)
                    ->orderByDesc('created_at')
                    ->select('status', 'trial_end_date')
                    ->first();
                if ($sub && in_array($sub->status, ['suspended', 'past_due'], true)) {
                    $count++;
                    $hasUrgent = true;
                } elseif ($sub && $sub->status === 'active') {
                    $hasFailedPayment = DB::table('payments')
                        ->where('tenant_id', $tenantId)
                        ->where('status', 'failed')
                        ->where('retry_count', '<', 3)
                        ->exists();
                    if ($hasFailedPayment) {
                        $count++;
                        $hasUrgent = true;
                    }
                } elseif ($sub && $sub->status === 'trial' && !empty($sub->trial_end_date)) {
                    $daysLeft = now()->diffInDays(\Carbon\Carbon::parse($sub->trial_end_date), false);
                    if ($daysLeft >= 0 && $daysLeft <= 7) {
                        $count++;
                    }
                }
            } catch (\Throwable) {}
        }

        // 11. Missing-referrer deals — only counted for users who can see referrer data
        if ($canViewReferrers) {
            try {
                $hasMissingReferrer = DB::table('leads')
                    ->where('tenant_id', $tenantId)
                    ->whereIn('status', ['active', 'expiring'])
                    ->where(fn($q) => $q->whereNull('reseller_name')->orWhere('reseller_name', ''))
                    ->whereNull('deleted_at')
                    ->exists();
                if ($hasMissingReferrer) {
                    $count++;
                }
            } catch (\Throwable) {}
        }

        // 12. Failed/conflicted rollbacks — presence indicator (+1); 14-day window mirrors failedRollbacks()
        //     'failed' = urgent (job crashed); 'completed_with_warnings' = medium (conflicts only)
        //     tableExists() cached 300s separately — block skipped on servers without the table
        if (self::tableExists('import_rollbacks')) {
            try {
                $rb = DB::table('import_rollbacks')
                    ->where('tenant_id', $tenantId)
                    ->whereIn('status', ['failed', 'completed_with_warnings'])
                    ->where('created_at', '>', now()->subDays(14))
                    ->selectRaw("COUNT(*) as total, SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_ct")
                    ->first();
                if ($rb && (int) $rb->total > 0) {
                    $count++;
                    // Only a hard failure is urgent — completed_with_warnings is medium severity
                    if ((int) $rb->failed_ct > 0) {
                        $hasUrgent = true;
                    }
                }
            } catch (\Throwable) {}
        }

        // 13. LGU IDS — pending default amount confirmations — presence indicator (+1)
        //     Urgent when any pending deal is already at contract_sent/signed/paid (money-on-the-line stages)
        if ($this->isLguIdsTenant($tenantId)) {
            try {
                $hasPendingDefaults = DB::table('leads')
                    ->where('tenant_id', $tenantId)
                    ->whereNotIn('status', ['expired', 'declined', 'archived'])
                    ->whereNull('deleted_at')
                    ->whereRaw("data->>'amount_defaulted' = 'true'")
                    ->whereRaw("data->>'amount_confirmation_status' = 'pending'")
                    ->exists();
                if ($hasPendingDefaults) {
                    $count++;
                    // Deals past contract_sent are money-on-the-line — unconfirmed amount is urgent
                    $hasAdvancedStagePending = DB::table('leads')
                        ->where('tenant_id', $tenantId)
                        ->whereIn('stage', ['contract_sent', 'signed', 'paid'])
                        ->whereNotIn('status', ['expired', 'declined', 'archived'])
                        ->whereNull('deleted_at')
                        ->whereRaw("data->>'amount_defaulted' = 'true'")
                        ->whereRaw("data->>'amount_confirmation_status' = 'pending'")
                        ->exists();
                    if ($hasAdvancedStagePending) {
                        $hasUrgent = true;
                    }
                }
            } catch (\Throwable) {}
        }

        // stalledDeals() and staleGoogleCalendarIntegrations() are intentionally excluded from
        // badge counting: each surfaces up to 10 individual CAs in the panel. Adding a presence
        // indicator would create a permanent badge for routine medium-severity maintenance items,
        // burying the urgent signal. They remain fully visible in the panel and dashboard widget.

        return ['count' => $count, 'has_urgent' => $hasUrgent];
    }

    /**
     * Invalidate all critical-actions caches for a tenant (call after any state-changing action).
     *
     * @param string $tenantId
     * @param string|null $userId  When set, also clears per-user badge caches.
     */
    public function invalidateCache(string $tenantId, ?string $userId = null): void
    {
        // Bust all ca_dashboard and ca_master permutations for this tenant.
        // Enumerate all billing×exports×users×referrers combos (16 for dashboard, 8 for master).
        $keys = [];
        foreach ([false, true] as $billing) {
            foreach ([false, true] as $exports) {
                foreach ([false, true] as $users) {
                    // ca_master: does not carry referrers in its opts (masterList lacks the param)
                    $masterOpts = ['billing' => $billing, 'exports' => $exports, 'users' => $users, 'limit_per_source' => 50, 'since' => null, 'until' => null];
                    $keys[] = "ca_master:{$tenantId}:" . md5(serialize($masterOpts));

                    // ca_dashboard: carries referrers gate — 2× the combos vs master
                    foreach ([false, true] as $referrers) {
                        $dashOpts = ['billing' => $billing, 'exports' => $exports, 'users' => $users, 'referrers' => $referrers, 'limit_per_source' => 5];
                        $keys[] = "ca_dashboard:{$tenantId}:" . md5(serialize($dashOpts));
                    }
                }
            }
        }

        if ($userId) {
            $keys[] = "ca_badge_{$tenantId}_{$userId}";
            $keys[] = "ca_badge_urgent:{$tenantId}:{$userId}";
            $keys[] = "ca_badge_suppressed:{$tenantId}:{$userId}";
        }

        Cache::deleteMultiple($keys);
    }

    /**
     * Bust panel caches AND per-user badge caches for every active admin/manager/owner.
     * Returns the plucked UID collection so callers can reuse it (e.g. for notif_unread_ busts)
     * without issuing a second DB query.
     * Use this from controllers, queue jobs, and services — all external bust callsites should prefer this method.
     * Note: super_admin users are not in tenant_memberships and manage their own badge cache.
     */
    public static function resetRequestMemo(): void
    {
        self::$bustedThisRequest = [];
    }

    public function invalidateAllAdminBadges(string $tenantId): Collection
    {
        if (isset(self::$bustedThisRequest[$tenantId])) {
            return self::$bustedThisRequest[$tenantId];
        }

        // Busts panel/dashboard cache keys only (no userId = no badge keys added inside invalidateCache).
        // Per-user badge keys are cleared separately in the foreach below.
        $this->invalidateCache($tenantId);
        $uids = collect();
        try {
            $uids = DB::table('tenant_memberships')
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->whereIn('role', ['owner', 'admin', 'manager'])
                ->pluck('tenant_user_id');

            $keys = [];
            foreach ($uids as $uid) {
                $keys[] = "ca_badge_{$tenantId}_{$uid}";
                $keys[] = "ca_badge_urgent:{$tenantId}:{$uid}";
                $keys[] = "ca_badge_suppressed:{$tenantId}:{$uid}";
            }
            if (!empty($keys)) {
                Cache::deleteMultiple($keys);
            }
        } catch (\Throwable) {}

        self::$bustedThisRequest[$tenantId] = $uids;
        return $uids;
    }

    /**
     * Top N actions for the dashboard widget (admin/manager view).
     */
    public function dashboardSummary(string $tenantId, int $limit = 6, bool $canSeeBilling = false, bool $canSeeExports = true, bool $canSeeUsers = true, bool $canViewReferrers = true): array
    {
        $opts = ['billing' => $canSeeBilling, 'exports' => $canSeeExports, 'users' => $canSeeUsers, 'referrers' => $canViewReferrers, 'limit_per_source' => 5];
        $all  = Cache::remember(
            "ca_dashboard:{$tenantId}:" . md5(serialize($opts)),
            90,
            fn() => $this->forTenant($tenantId, $opts)
        );
        // Dashboard widget: severity-first so urgent items are never buried by recent low-severity ones
        usort($all, fn($a, $b) =>
            (self::SEVERITY_ORDER[$a['severity']] ?? 9) <=> (self::SEVERITY_ORDER[$b['severity']] ?? 9)
            ?: strtotime($b['occurred_at']) <=> strtotime($a['occurred_at'])
        );
        return array_slice($all, 0, $limit);
    }

    /**
     * Full paginated list with search/filter for master list page.
     */
    public function masterList(string $tenantId, array $filters = [], int $perPage = 25): array
    {
        $opts = [
            'billing'          => $filters['can_see_billing'] ?? false,
            'exports'          => $filters['can_see_exports']  ?? true,
            'users'            => $filters['can_see_users']    ?? true,
            'limit_per_source' => 50,
            'since'            => $filters['since'] ?? null,
            'until'            => $filters['until'] ?? null,
        ];
        // Stringify Carbon objects before serializing — Carbon instances are not
        // deterministically serializable across requests, causing cache key collisions
        $cacheOpts = $opts;
        $cacheOpts['since'] = $opts['since'] instanceof \Carbon\Carbon ? $opts['since']->toIso8601String() : $opts['since'];
        $cacheOpts['until'] = $opts['until'] instanceof \Carbon\Carbon ? $opts['until']->toIso8601String() : $opts['until'];

        $all = Cache::remember(
            "ca_master:{$tenantId}:" . md5(serialize($cacheOpts)),
            45,
            fn() => $this->forTenant($tenantId, $opts)
        );

        // Filter out actions the current user has dismissed.
        // Cached for 30 s per user — invalidated immediately on dismiss so UX stays snappy.
        if (! empty($filters['user_id'])) {
            try {
                $userType          = $filters['user_type'] ?? 'tenant_user';
                $dismissedCacheKey = "ca_dismissed:{$tenantId}:{$filters['user_id']}:{$userType}";

                $dismissed = Cache::remember(
                    $dismissedCacheKey,
                    30,
                    fn() => \Illuminate\Support\Facades\DB::table('critical_action_dismissals')
                        ->where('tenant_id', $tenantId)
                        ->where('user_id', $filters['user_id'])
                        ->where('user_type', $userType)
                        ->where(fn($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                        ->pluck('fingerprint')
                        ->flip() // convert to hash map for O(1) lookup
                        ->all()
                );

                if (! empty($dismissed)) {
                    $all = array_filter($all, fn($a) => ! isset($dismissed[$a['fingerprint'] ?? '']));
                }
            } catch (\Throwable) {}
        }

        // Apply filters
        if (! empty($filters['severity'])) {
            $all = array_filter($all, fn($a) => $a['severity'] === $filters['severity']);
        }
        if (! empty($filters['category'])) {
            $all = array_filter($all, fn($a) => $a['category'] === $filters['category']);
        }
        if (! empty($filters['search'])) {
            $q = strtolower($filters['search']);
            $all = array_filter($all, fn($a) =>
                str_contains(strtolower($a['summary']), $q) ||
                str_contains(strtolower($a['actor_name'] ?? ''), $q) ||
                str_contains(strtolower($a['related_label'] ?? ''), $q)
            );
        }

        // Sort: default most-recent-first; 'recency_asc' = oldest-first; 'severity' = severity-first
        $sort = $filters['sort'] ?? 'recency_desc';
        if ($sort === 'recency_asc') {
            usort($all, fn($a, $b) =>
                strtotime($a['occurred_at']) <=> strtotime($b['occurred_at'])
                ?: (self::SEVERITY_ORDER[$a['severity']] ?? 9) <=> (self::SEVERITY_ORDER[$b['severity']] ?? 9)
            );
        } else {
            usort($all, fn($a, $b) =>
                strtotime($b['occurred_at']) <=> strtotime($a['occurred_at'])
                ?: (self::SEVERITY_ORDER[$a['severity']] ?? 9) <=> (self::SEVERITY_ORDER[$b['severity']] ?? 9)
            );
        }

        $all   = array_values($all);
        $total = count($all);
        $page  = max(1, (int) ($filters['page'] ?? 1));
        $items = array_slice($all, ($page - 1) * $perPage, $perPage);

        return [
            'items'        => $items,
            'total'        => $total,
            'page'         => $page,
            'per_page'     => $perPage,
            'total_pages'  => max(1, (int) ceil($total / $perPage)),
        ];
    }

    /**
     * Referrer-scoped recent actions for their dashboard.
     * Only shows actions on the Referrer's own accessible deals.
     */
    public function forReseller(string $tenantId, string $resellerName, int $limit = 8, ?string $resellerId = null): array
    {
        $cacheKey = "ca_reseller:{$tenantId}:" . md5($resellerName . ':' . ($resellerId ?? ''));
        return Cache::remember($cacheKey, 60, function () use ($tenantId, $resellerName, $resellerId, $limit) {
            $sources = [
                fn() => $this->resellerNewlyAssignedDeals($tenantId, $resellerName),
                fn() => $this->resellerExpiringDeals($tenantId, $resellerName),
                fn() => $this->resellerStalledDeals($tenantId, $resellerName),
                fn() => $this->resellerExtensionRequests($tenantId, $resellerName),
                fn() => $this->resellerBulkExtensionBatches($tenantId, $resellerName),
                fn() => $this->resellerUnreadMessages($tenantId, $resellerName),
                fn() => $this->resellerLeadHistory($tenantId, $resellerName),
                fn() => $this->resellerCommissionUpdates($tenantId, $resellerName),
                fn() => $this->resellerPendingApprovals($tenantId, $resellerName),
            ];

            if ($resellerId) {
                $sources[] = fn() => $this->resellerImportEvents($tenantId, $resellerId);
                $sources[] = fn() => $this->resellerOverdueTasks($tenantId, $resellerId);
                $sources[] = fn() => $this->resellerFailedExports($tenantId, $resellerId);
            }

            // LGU IDS only — deals with no notes yet (additive, never runs for other tenants)
            if ($this->isLguIdsTenant($tenantId)) {
                $sources[] = fn() => $this->resellerDealsWithNoNotes($tenantId, $resellerName);
            }

            $items = [];
            foreach ($sources as $source) {
                try {
                    $items = array_merge($items, $source());
                } catch (\Throwable $e) {
                    Log::warning('[CriticalActionService] Referrer source failed', ['error' => $e->getMessage()]);
                }
            }

            usort($items, fn($a, $b) =>
                (self::SEVERITY_ORDER[$a['severity']] ?? 9) <=> (self::SEVERITY_ORDER[$b['severity']] ?? 9)
                ?: strtotime($b['occurred_at']) <=> strtotime($a['occurred_at'])
            );

            return array_slice($items, 0, $limit);
        });
    }

    /**
     * Critical actions for a Partner — unread messages + expiring deals.
     */
    public function forPartner(string $partnerId, string $tenantId, int $limit = 6): array
    {
        $cacheKey = "ca_partner:{$tenantId}:{$partnerId}";
        $cached   = Cache::get($cacheKey);
        if ($cached !== null) return array_slice($cached, 0, $limit);

        $actions  = [];
        $dealIds  = []; // initialized here so commission block can safely reference it

        // Unread messages in partner threads (partner_unread > 0)
        try {
            $threads = DB::table('partner_threads')
                ->where('tenant_id', $tenantId)
                ->where('partner_id', $partnerId)
                ->where('partner_unread', '>', 0)
                ->select('id', 'deal_id', 'partner_unread', 'last_message_at')
                ->orderByDesc('last_message_at')
                ->limit(5)
                ->get();

            if ($threads->isNotEmpty()) {
                $totalUnread = $threads->sum('partner_unread');
                $actions[] = $this->make([
                    'type'          => 'partner_unread_messages',
                    'category'      => 'messaging',
                    'severity'      => 'medium',
                    'summary'       => "{$totalUnread} unread message" . ($totalUnread > 1 ? 's' : '') . ' from your Referrer',
                    'actor_name'    => 'Referrer',
                    'actor_role'    => 'Referrer',
                    'related_label' => 'Messages',
                    'related_type'  => 'message',
                    'related_id'    => null,
                    'occurred_at'   => now(),
                    'action_url'    => '/partner/messages',
                    'action_label'  => 'View Messages',
                    'action_needed' => true,
                    'source'        => 'partner_threads',
                    'description'   => 'Your Referrer sent you a message. Reply to stay on top of your deals.',
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('[CriticalActionService] forPartner:messages failed', ['partner_id' => $partnerId, 'error' => $e->getMessage()]);
        }

        // Expiring deals this partner has a split on
        try {
            $partnerEmail = DB::table('partner_users')->where('id', $partnerId)->value('email');

            if ($partnerEmail) {
                $dealIds = DB::table('deal_partner_splits')
                    ->where('tenant_id', $tenantId)
                    ->whereRaw('LOWER(partner_email) = ?', [strtolower($partnerEmail)])
                    ->whereNull('deleted_at')
                    ->where('status', '!=', 'removed')
                    ->pluck('deal_id')
                    ->toArray();

                if (!empty($dealIds)) {
                    $expiring = DB::table('leads')
                        ->where('tenant_id', $tenantId)
                        ->whereNull('deleted_at')
                        ->whereIn('id', $dealIds)
                        ->where('status', 'expiring')
                        ->select('id', 'name', 'days_left', 'updated_at')
                        ->orderBy('days_left')
                        ->limit(3)
                        ->get();

                    foreach ($expiring as $deal) {
                        $actions[] = $this->make([
                            'type'          => 'deal_expiring',
                            'category'      => 'deal',
                            'severity'      => ($deal->days_left ?? 0) <= 2 ? 'urgent' : 'high',
                            'summary'       => "Your deal is expiring soon: {$deal->name}",
                            'actor_name'    => 'System',
                            'actor_role'    => 'System',
                            'related_label' => $deal->name,
                            'related_type'  => 'deal',
                            'related_id'    => $deal->id,
                            'occurred_at'   => $deal->updated_at ?? now(),
                            'action_url'    => "/partner/deals/{$deal->id}",
                            'action_label'  => 'View Deal',
                            'action_needed' => true,
                            'source'        => 'leads',
                            'meta'          => ['days_left' => $deal->days_left],
                        ]);
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[CriticalActionService] forPartner:expiring failed', ['partner_id' => $partnerId, 'error' => $e->getMessage()]);
        }

        // Commission status updates on partner's deals (locked/paid in last 7 days)
        try {
            if (!empty($dealIds)) {
                $commRows = DB::table('leads')
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at')
                    ->whereIn('id', $dealIds)
                    ->whereIn('commission_status', ['locked', 'paid'])
                    ->where('updated_at', '>', now()->subDays(7))
                    ->select('id', 'name', 'commission_status', 'updated_at')
                    ->orderByDesc('updated_at')
                    ->limit(3)
                    ->get();

                foreach ($commRows as $r) {
                    $actions[] = $this->make([
                        'type'          => $r->commission_status === 'paid' ? 'commission_paid' : 'commission_locked',
                        'category'      => 'commission',
                        'severity'      => $r->commission_status === 'paid' ? 'info' : 'medium',
                        'summary'       => $r->commission_status === 'paid'
                            ? "Commission paid for: {$r->name}"
                            : "Commission locked for: {$r->name}",
                        'actor_name'    => 'System',
                        'actor_role'    => 'System',
                        'related_label' => $r->name,
                        'related_type'  => 'deal',
                        'related_id'    => $r->id,
                        'occurred_at'   => $r->updated_at ?? now(),
                        'action_url'    => "/partner/commissions",
                        'action_label'  => 'View Commissions',
                        'action_needed' => $r->commission_status === 'locked',
                        'source'        => 'leads',
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[CriticalActionService] forPartner:commission failed', ['partner_id' => $partnerId, 'error' => $e->getMessage()]);
        }

        usort($actions, fn($a, $b) =>
            (self::SEVERITY_ORDER[$a['severity']] ?? 9) <=> (self::SEVERITY_ORDER[$b['severity']] ?? 9)
            ?: strtotime($b['occurred_at']) <=> strtotime($a['occurred_at'])
        );

        Cache::put($cacheKey, $actions, 60);
        return array_slice($actions, 0, $limit);
    }

    // ── Tenant-level aggregation ────────────────────────────────────

    private function forTenant(string $tenantId, array $opts = []): array
    {
        $limitPer     = $opts['limit_per_source'] ?? 10;
        $since        = $opts['since'] ?? now()->subDays(30);
        $canBilling   = $opts['billing']    ?? false;
        $canExports   = $opts['exports']    ?? true;
        $canUsers     = $opts['users']      ?? true;
        $canReferrers = $opts['referrers']  ?? true;

        $sources = [
            fn() => $this->expiringDeals($tenantId),
            fn() => $this->expiredDeals($tenantId),
            fn() => $this->pendingArchiveRequests($tenantId),
            fn() => $this->pendingStageMoveRequests($tenantId),
            fn() => $this->pendingExtensionRequests($tenantId),
            fn() => $this->pendingBulkExtensionBatches($tenantId),
            fn() => $this->failedRollbacks($tenantId),
            fn() => $this->recentLeadHistory($tenantId, $limitPer, $since),
            fn() => $this->importEvents($tenantId, $limitPer),
            fn() => $this->unrepliedMessages($tenantId),
            fn() => $this->unreadPartnerMessages($tenantId),
            fn() => $this->recentActivityLogs($tenantId, $limitPer, $since),
            fn() => $this->overdueOpenTasks($tenantId),
            fn() => $this->openRequestFormTasks($tenantId),
            fn() => $this->pendingDefaultAmounts($tenantId),
            fn() => $this->recentReferrerAmountChanges($tenantId, $since),
            fn() => $this->stalledDeals($tenantId),
            fn() => $this->commissionReviewQueue($tenantId),
            fn() => $this->staleGoogleCalendarIntegrations($tenantId),
        ];

        if ($canUsers) {
            $sources[] = fn() => $this->pendingInvites($tenantId);
            $sources[] = fn() => $this->recentAcceptedInvites($tenantId, $limitPer, $since);
            $sources[] = fn() => $this->newReferrerSignups($tenantId);
        }

        $sources[] = fn() => $this->newPartnersOnDeals($tenantId);

        if ($canExports) {
            $sources[] = fn() => $this->pendingExportRequests($tenantId);
        }

        if ($canBilling) {
            $sources[] = fn() => $this->billingIssues($tenantId);
        }

        if ($canReferrers) {
            $sources[] = fn() => $this->missingReferrerDeals($tenantId);
        }

        $all  = [];
        $seen = [];
        foreach ($sources as $source) {
            try {
                foreach ($source() as $action) {
                    // Deduplicate by type+related_id so the same deal can't appear in
                    // both expiringDeals and stalledDeals, inflating the badge count
                    // Aggregate actions (related_id=null) use a stable '__agg__' sentinel so the
                    // key doesn't shift when the count changes (e.g., "3 deals" → "4 deals")
                    $dedupeKey = $action['type'] . ':' . ($action['related_id'] ?? '__agg__');
                    if (!isset($seen[$dedupeKey])) {
                        $seen[$dedupeKey] = true;
                        $all[] = $action;
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('[CriticalActionService] Tenant source failed', ['tenant_id' => $tenantId, 'error' => $e->getMessage()]);
            }
        }
        return $all;
    }

    // ── Source queries ─────────────────────────────────────────────

    private function expiringDeals(string $tenantId): array
    {
        $rows = DB::table('leads')
            ->where('tenant_id', $tenantId)
            ->where('status', 'expiring')
            ->whereNull('deleted_at')
            ->select('id', 'name', 'reseller_name', 'days_left', 'deal_value', 'updated_at')
            ->orderBy('days_left')
            ->limit(10)
            ->get();

        return $rows->map(fn($r) => $this->make([
            'type'          => 'deal_expiring',
            'category'      => 'deal',
            'severity'      => ($r->days_left ?? 0) <= 2 ? 'urgent' : 'high',
            'summary'       => "Deal expiring soon: {$r->name}",
            'actor_name'    => $r->reseller_name ?? 'Unassigned',
            'actor_role'    => 'Referrer',
            'related_label' => $r->name,
            'related_type'  => 'deal',
            'related_id'    => $r->id,
            'occurred_at'   => $r->updated_at ?? now(),
            'action_url'    => "/tenant/{$tenantId}/deals/{$r->id}",
            'action_label'  => 'View Deal',
            'action_needed' => true,
            'source'        => 'leads',
            'meta'          => ['days_left' => $r->days_left],
        ]))->toArray();
    }

    private function expiredDeals(string $tenantId): array
    {
        $rows = DB::table('leads')
            ->where('tenant_id', $tenantId)
            ->where('status', 'expired')
            ->where('updated_at', '>', now()->subDays(7))
            ->whereNull('deleted_at')
            ->where(fn($q) =>
                $q->whereNotIn('stage', ['signed', 'paid'])
                  ->orWhere('commission_status', '!=', 'pending')
            )
            ->select('id', 'name', 'reseller_name', 'updated_at')
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get();

        return $rows->map(fn($r) => $this->make([
            'type'          => 'deal_expired',
            'category'      => 'deal',
            'severity'      => 'urgent',
            'summary'       => "Deal expired: {$r->name}",
            'actor_name'    => $r->reseller_name ?? 'Unassigned',
            'actor_role'    => 'Referrer',
            'related_label' => $r->name,
            'related_type'  => 'deal',
            'related_id'    => $r->id,
            'occurred_at'   => $r->updated_at ?? now(),
            'action_url'    => "/tenant/{$tenantId}/deals/{$r->id}",
            'action_label'  => 'View Deal',
            'action_needed' => true,
            'source'        => 'leads',
        ]))->toArray();
    }

    private function missingReferrerDeals(string $tenantId): array
    {
        $count = DB::table('leads')
            ->where('tenant_id', $tenantId)
            ->where(fn($q) => $q->whereNull('reseller_name')->orWhere('reseller_name', ''))
            ->whereIn('status', ['active', 'expiring'])
            ->whereNull('deleted_at')
            ->count();

        if ($count === 0) return [];

        return [$this->make([
            'type'          => 'missing_referrer',
            'category'      => 'deal',
            'severity'      => 'medium',
            'summary'       => "{$count} deal" . ($count > 1 ? 's' : '') . " missing Referrer assignment",
            'actor_name'    => 'System',
            'actor_role'    => 'System',
            'related_label' => "{$count} deals",
            'related_type'  => 'deal',
            'related_id'    => null,
            'occurred_at'   => now(),
            'action_url'    => "/tenant/{$tenantId}/deals?reseller_name=MISSING",
            'action_needed' => true,
            'source'        => 'leads',
        ])];
    }

    private function recentLeadHistory(string $tenantId, int $limit, $since): array
    {
        $rows = DB::table('lead_history as h')
            ->join('leads as l', 'l.id', '=', 'h.lead_id')
            ->where('l.tenant_id', $tenantId)
            ->whereNull('l.deleted_at')
            ->where('h.created_at', '>', $since)
            ->whereIn('h.type', ['stage', 'assignment', 'commission'])
            ->select('h.id', 'h.action', 'h.type', 'h.reseller', 'h.date', 'h.created_at', 'l.id as lead_id', 'l.name as lead_name')
            ->orderByDesc('h.created_at')
            ->limit($limit)
            ->get();

        return $rows->map(fn($r) => $this->make([
            'type'          => 'deal_' . $r->type,
            'category'      => $r->type === 'commission' ? 'commission' : 'deal',
            'severity'      => $r->type === 'commission' ? 'medium' : 'info',
            'summary'       => $r->action,
            'actor_name'    => $r->reseller ?? 'System',
            'actor_role'    => $r->reseller ? 'Referrer' : 'System',
            'related_label' => $r->lead_name,
            'related_type'  => 'deal',
            'related_id'    => $r->lead_id,
            'occurred_at'   => $r->created_at ?? now(),
            'action_url'    => "/tenant/{$tenantId}/deals/{$r->lead_id}",
            'action_needed' => false,
            'source'        => 'lead_history',
        ]))->toArray();
    }

    private function importEvents(string $tenantId, int $limit): array
    {
        $rows = DB::table('import_batches')
            ->where('tenant_id', $tenantId)
            ->where(fn($q) => $q
                ->whereIn('status', ['completed_with_warnings', 'failed', 'completed', 'previewed', 'previewing'])
                ->orWhere(fn($q2) => $q2->where('status', 'processing')->where('started_at', '<', now()->subMinutes(15)))
            )
            ->where('created_at', '>', now()->subDays(14))
            ->select('id', 'status', 'file_name', 'import_type', 'failed_rows',
                     'successful_rows', 'total_rows', 'unknown_referrer_rows',
                     'imported_by_id', 'imported_by_role', 'created_at', 'completed_at')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        return $rows->map(function ($r) use ($tenantId) {
            $batchBase = match($r->import_type ?? '') {
                'lgu_ids_deals' => "/tenant/{$tenantId}/imports/lgu-ids/{$r->id}",
                'contacts'      => "/tenant/{$tenantId}/imports/contacts/{$r->id}",
                default         => "/tenant/{$tenantId}/imports/deals/{$r->id}",
            };

            $typeLabel = match($r->import_type ?? '') {
                'lgu_ids_deals' => 'LGU IDS deal import',
                'contacts'      => 'Contacts import',
                default         => 'Deal import',
            };

            [$severity, $summary, $actionUrl, $actionLabel, $actionNeeded] = match ($r->status) {
                'previewed'               => ['high',   "{$typeLabel} ready to confirm: {$r->file_name}",       $batchBase,           'Confirm Import',   true],
                'previewing'             => ['medium', "{$typeLabel} upload in progress: {$r->file_name}",      $batchBase,           'Review Upload',    true],
                'processing'              => ['high',   "{$typeLabel} stuck — processing for 15+ minutes: {$r->file_name}", $batchBase, 'View Import',  true],
                'failed'                  => ['high',   "{$typeLabel} failed: {$r->file_name}",                 "{$batchBase}/report", 'View Report',     true],
                'completed_with_warnings' => ['medium', "{$typeLabel} completed with warnings: {$r->file_name}" . ($r->failed_rows > 0 ? " — {$r->failed_rows} row(s) failed" : ''), "{$batchBase}/report", 'View Report', true],
                default                   => ['info',   "{$typeLabel} completed: {$r->file_name}",              "{$batchBase}/report", 'View Report',     false],
            };

            if (($r->unknown_referrer_rows ?? 0) > 0 && in_array($r->status, ['completed', 'completed_with_warnings'])) {
                $summary     .= " — {$r->unknown_referrer_rows} referrers need inviting";
                $actionNeeded = true;
            }

            return $this->make([
                'type'          => 'import_' . $r->status,
                'category'      => 'import',
                'severity'      => $severity,
                'summary'       => $summary,
                'actor_name'    => ucfirst($r->imported_by_role ?? 'Admin'),
                'actor_role'    => ucfirst($r->imported_by_role ?? 'Admin'),
                'related_label' => $r->file_name,
                'related_type'  => 'import',
                'related_id'    => $r->id,
                'occurred_at'   => $r->completed_at ?? $r->created_at ?? now(),
                'action_url'    => $actionUrl,
                'action_label'  => $actionLabel,
                'action_needed' => $actionNeeded,
                'source'        => 'import_batches',
            ]);
        })->toArray();
    }

    private function pendingInvites(string $tenantId): array
    {
        $actions = [];

        // Tenant user invites expiring within 48 hours
        $expiringUsers = DB::table('tenant_invitations')
            ->where('tenant_id', $tenantId)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->where('expires_at', '<', now()->addHours(48))
            ->count();

        if ($expiringUsers > 0) {
            $actions[] = $this->make([
                'type'          => 'invite_expiring',
                'category'      => 'user',
                'severity'      => 'high',
                'summary'       => "{$expiringUsers} team invitation" . ($expiringUsers > 1 ? 's' : '') . " expiring within 48 hours",
                'actor_name'    => 'System',
                'actor_role'    => 'System',
                'related_label' => 'Team Invitations',
                'related_type'  => 'invitation',
                'related_id'    => null,
                'occurred_at'   => now(),
                'action_url'    => "/tenant/{$tenantId}/users#pending-invitations",
                'action_needed' => true,
                'source'        => 'tenant_invitations',
            ]);
        }

        // Referrer invites approaching 90-day expiry (within 7 days)
        $expiringReferrers = DB::table('resellers')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->where('status', 'invited')
            ->where('created_at', '<', now()->subDays(83))
            ->where('created_at', '>', now()->subDays(90))
            ->count();

        if ($expiringReferrers > 0) {
            $actions[] = $this->make([
                'type'          => 'referrer_invite_expiring',
                'category'      => 'user',
                'severity'      => 'high',
                'summary'       => "{$expiringReferrers} referrer invite" . ($expiringReferrers > 1 ? 's' : '') . " expiring within 7 days — re-invite or they'll lose access",
                'actor_name'    => 'System',
                'actor_role'    => 'System',
                'related_label' => 'Referrer Invitations',
                'related_type'  => 'referrer',
                'related_id'    => null,
                'occurred_at'   => now(),
                'action_url'    => "/tenant/{$tenantId}/referrers",
                'action_needed' => true,
                'source'        => 'resellers',
            ]);
        }

        return $actions;
    }

    private function recentAcceptedInvites(string $tenantId, int $limit, $since): array
    {
        $rows = DB::table('tenant_invitations')
            ->where('tenant_id', $tenantId)
            ->where('status', 'accepted')
            ->where('accepted_at', '>', $since)
            ->select('id', 'email', 'role', 'accepted_at')
            ->orderByDesc('accepted_at')
            ->limit($limit)
            ->get();

        return $rows->map(fn($r) => $this->make([
            'type'          => 'invite_accepted',
            'category'      => 'user',
            'severity'      => 'info',
            'summary'       => "Invitation accepted: {$r->email} joined as " . ucfirst($r->role),
            'actor_name'    => $r->email,
            'actor_role'    => ucfirst($r->role),
            'related_label' => $r->email,
            'related_type'  => 'user',
            'related_id'    => $r->id,
            'occurred_at'   => $r->accepted_at ?? now(),
            'action_url'    => "/tenant/{$tenantId}/users",
            'action_needed' => false,
            'source'        => 'tenant_invitations',
        ]))->toArray();
    }

    private function unrepliedMessages(string $tenantId): array
    {
        // Threads where admin has unread messages AND the last Referrer message is > 24h old.
        // Using admin_unread (reset when admin reads the thread) + a 24h-old Referrer message.
        try {
            $count = DB::table('message_threads as t')
                ->where('t.tenant_id', $tenantId)
                ->where('t.admin_unread', '>', 0)
                ->whereExists(fn($q) => $q->select(DB::raw(1))
                    ->from('thread_messages as m')
                    ->whereColumn('m.thread_id', 't.id')
                    ->where('m.sender_type', 'reseller')
                    ->where('m.created_at', '<', now()->subHours(24))
                )
                ->count();

            if ($count === 0) return [];

            return [$this->make([
                'type'          => 'message_unreplied',
                'category'      => 'messaging',
                'severity'      => 'medium',
                'summary'       => "{$count} message thread" . ($count > 1 ? 's' : '') . " unreplied for over 24 hours",
                'actor_name'    => 'System',
                'actor_role'    => 'System',
                'related_label' => 'Messages',
                'related_type'  => 'message',
                'related_id'    => null,
                'occurred_at'   => now(),
                'action_url'    => "/tenant/{$tenantId}/messages?tab=needs_reply",
                'action_needed' => true,
                'source'        => 'message_threads',
            ])];
        } catch (\Throwable $e) {
            Log::warning('[CriticalActionService] unrepliedMessages failed', ['tenant_id' => $tenantId, 'error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Admin view: two separate actions —
     * 1) Direct partner messages the admin hasn't read yet (admin_unread > 0, thread_type = 'direct')
     * 2) Deal-thread partner messages the Referrer hasn't replied to (reseller_unread > 0, deal_id IS NOT NULL)
     */
    private function unreadPartnerMessages(string $tenantId): array
    {
        $actions = [];
        try {
            // Admin's own unread direct partner messages
            $adminUnread = DB::table('partner_threads')
                ->where('tenant_id', $tenantId)
                ->where('thread_type', 'direct')
                ->where('admin_unread', '>', 0)
                ->count();

            if ($adminUnread > 0) {
                $actions[] = $this->make([
                    'type'          => 'partner_direct_messages_unread',
                    'category'      => 'messaging',
                    'severity'      => 'medium',
                    'summary'       => "{$adminUnread} unread direct message" . ($adminUnread > 1 ? 's' : '') . ' from Partners',
                    'actor_name'    => 'Partners',
                    'actor_role'    => 'Partner',
                    'related_label' => 'Partner Messages',
                    'related_type'  => 'message',
                    'related_id'    => null,
                    'occurred_at'   => now(),
                    'action_url'    => "/tenant/{$tenantId}/messages?tab=partners",
                    'action_label'  => 'View Partner Messages',
                    'action_needed' => true,
                    'source'        => 'partner_threads',
                    'description'   => 'Partners have sent you direct messages that need a response.',
                ]);
            }

            // Deal-thread partner messages the Referrer hasn't replied to
            $referrerUnread = DB::table('partner_threads')
                ->where('tenant_id', $tenantId)
                ->whereNotNull('deal_id')
                ->where('reseller_unread', '>', 0)
                ->count();

            if ($referrerUnread > 0) {
                $actions[] = $this->make([
                    'type'          => 'partner_deal_messages_unread',
                    'category'      => 'messaging',
                    'severity'      => 'low',
                    'summary'       => "{$referrerUnread} deal thread" . ($referrerUnread > 1 ? 's' : '') . ' with partner messages awaiting Referrer reply',
                    'actor_name'    => 'Partners',
                    'actor_role'    => 'Partner',
                    'related_label' => 'Partner Deal Messages',
                    'related_type'  => 'message',
                    'related_id'    => null,
                    'occurred_at'   => now(),
                    'action_url'    => "/tenant/{$tenantId}/messages?tab=partners",
                    'action_label'  => 'View Partner Messages',
                    'action_needed' => false,
                    'source'        => 'partner_threads',
                    'description'   => 'Partners have sent deal messages that their Referrer has not yet replied to.',
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('[CriticalActionService] unreadPartnerMessages failed', ['tenant_id' => $tenantId, 'error' => $e->getMessage()]);
        }
        return $actions;
    }

    private function recentActivityLogs(string $tenantId, int $limit, $since): array
    {
        try {
            // Exclude audit-only rows without using the metadata JSON column in the
            // index-path: pull only the narrow columns first, then filter audit rows
            // in a subquery so the planner can use idx_activity_logs_tenant_created.
            // The metadata->>'audit_event' filter is pushed into a NOT EXISTS sub-select
            // to avoid a full-column JSON evaluation on every row before the LIMIT.
            $rows = DB::table('activity_logs')
                ->where('tenant_id', $tenantId)
                ->where('created_at', '>', $since)
                ->whereRaw("COALESCE(metadata->>'audit_event', 'false') != 'true'")
                ->select('id', 'user_id', 'action', 'entity', 'entity_id', 'created_at')
                ->orderByDesc('created_at')
                ->limit($limit)
                ->get();

            // Batch-resolve actor names from tenant_users and super-admin users tables
            $userIds = $rows->pluck('user_id')->filter()->unique()->values()->toArray();
            $actorMap = [];
            if (!empty($userIds)) {
                DB::table('tenant_users')->whereIn('id', $userIds)
                    ->select('id', DB::raw("TRIM(CONCAT(COALESCE(first_name,''), ' ', COALESCE(last_name,''))) as name"))
                    ->get()->each(fn($u) => $actorMap[(string)$u->id] = $u->name ?: 'Team member');
                DB::table('users')->whereIn('id', $userIds)->select('id', 'name')
                    ->get()->each(fn($u) => $actorMap[(string)$u->id] ??= ($u->name ?: 'Super Admin'));
            }

            return $rows->map(function ($r) use ($tenantId, $actorMap) {
                $actorName = isset($r->user_id) ? ($actorMap[(string)$r->user_id] ?? 'Team member') : 'System';

                $actionUrl = match($r->entity ?? '') {
                    'lead'           => "/tenant/{$tenantId}/deals/{$r->entity_id}",
                    'reseller',
                    'referrer'       => "/tenant/{$tenantId}/referrers/{$r->entity_id}",
                    'import',
                    'import_batch'   => "/tenant/{$tenantId}/imports",
                    default          => null,
                };

                return $this->make([
                    'type'          => 'activity_' . str_replace([' ', '-'], '_', strtolower($r->action ?? 'action')),
                    'category'      => 'activity',
                    'severity'      => 'info',
                    'summary'       => ucfirst(str_replace('_', ' ', $r->action ?? 'Action recorded')),
                    'actor_name'    => $actorName,
                    'actor_role'    => 'Admin',
                    'related_label' => $r->entity ?? '',
                    'related_type'  => $r->entity ?? 'record',
                    'related_id'    => $r->entity_id,
                    'occurred_at'   => $r->created_at ?? now(),
                    'action_url'    => $actionUrl,
                    'action_needed' => false,
                    'source'        => 'activity_logs',
                ]);
            })->toArray();
        } catch (\Throwable $e) {
            Log::warning('[CriticalActionService] recentActivityLogs failed', ['tenant_id' => $tenantId, 'error' => $e->getMessage()]);
            return [];
        }
    }

    // ── Referrer-scoped queries ────────────────────────────────────

    private function resellerNewlyAssignedDeals(string $tenantId, string $resellerName): array
    {
        $lower = strtolower($resellerName);

        // Deals where this referrer was assigned within the last 48 hours via a lead_history
        // assignment entry. Surfaces as action_needed=true so it appears in the Actions Needed widget.
        $rows = DB::table('leads as l')
            ->join('lead_history as h', fn($j) => $j
                ->on('h.lead_id', '=', 'l.id')
                ->where('h.type', 'assignment')
                ->where('h.created_at', '>', now()->subHours(48))
            )
            ->where('l.tenant_id', $tenantId)
            ->whereNull('l.deleted_at')
            ->where('l.status', '!=', 'archived')
            ->whereRaw('LOWER(l.reseller_name) = ?', [$lower])
            ->select('l.id', 'l.name', 'l.stage', 'h.created_at as assigned_at')
            ->orderByDesc('h.created_at')
            ->limit(5)
            ->get();

        return $rows->map(fn($r) => $this->make([
            'type'          => 'deal_assignment',
            'category'      => 'deal',
            'severity'      => 'high',
            'summary'       => 'New deal assigned to you: ' . $r->name,
            'actor_name'    => 'Admin',
            'actor_role'    => 'Admin',
            'related_label' => $r->name,
            'related_type'  => 'deal',
            'related_id'    => $r->id,
            'occurred_at'   => $r->assigned_at ?? now(),
            'action_url'    => "/reseller/{$tenantId}/deals/{$r->id}",
            'action_label'  => 'View Deal',
            'action_needed' => true,
            'source'        => 'lead_history',
            'description'   => 'You were assigned to this deal. Review the details and take your first action.',
        ]))->toArray();
    }

    private function resellerExpiringDeals(string $tenantId, string $resellerName): array
    {
        // Show deals already marked expiring OR still active but with ≤5 days left
        $lower = strtolower($resellerName);
        $rows = DB::table('leads')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->where(fn($q) => $q
                ->whereRaw('LOWER(reseller_name) = ?', [$lower])
                ->orWhereExists(fn($sub) => $sub
                    ->from('commission_splits')
                    ->whereColumn('commission_splits.lead_id', 'leads.id')
                    ->whereRaw('LOWER(commission_splits.reseller_name) = ?', [$lower])
                )
            )
            ->where(fn($q) =>
                $q->where('status', 'expiring')
                  ->orWhere(fn($q2) => $q2->where('status', 'active')->where('days_left', '<=', 5))
            )
            ->select('id', 'name', 'days_left', 'status', 'updated_at')
            ->orderBy('days_left')
            ->limit(5)
            ->get();

        return $rows->map(fn($r) => $this->make([
            'type'          => 'deal_expiring',
            'category'      => 'deal',
            'severity'      => ($r->days_left ?? 0) <= 2 ? 'urgent' : 'high',
            'summary'       => "Your deal is expiring soon: {$r->name}",
            'actor_name'    => 'System',
            'actor_role'    => 'System',
            'related_label' => $r->name,
            'related_type'  => 'deal',
            'related_id'    => $r->id,
            'occurred_at'   => $r->updated_at ?? now(),
            'action_url'    => "/reseller/{$tenantId}/deals/{$r->id}",
            'action_label'  => 'View Deal',
            'action_needed' => true,
            'source'        => 'leads',
            'meta'          => ['days_left' => $r->days_left],
        ]))->toArray();
    }

    private function resellerLeadHistory(string $tenantId, string $resellerName): array
    {
        $lower = strtolower($resellerName);
        $rows = DB::table('lead_history as h')
            ->join('leads as l', 'l.id', '=', 'h.lead_id')
            ->where('l.tenant_id', $tenantId)
            ->whereNull('l.deleted_at')
            ->where(fn($q) => $q
                ->whereRaw('LOWER(l.reseller_name) = ?', [$lower])
                ->orWhereExists(fn($sub) => $sub
                    ->from('commission_splits')
                    ->whereColumn('commission_splits.lead_id', 'l.id')
                    ->whereRaw('LOWER(commission_splits.reseller_name) = ?', [$lower])
                )
            )
            ->where('h.created_at', '>', now()->subDays(14))
            ->whereIn('h.type', ['stage', 'commission', 'assignment'])
            ->select('h.id', 'h.action', 'h.type', 'h.created_at', 'l.id as lead_id', 'l.name as lead_name')
            ->orderByDesc('h.created_at')
            ->limit(8)
            ->get();

        return $rows->map(fn($r) => $this->make([
            'type'          => 'deal_' . $r->type,
            'category'      => $r->type === 'commission' ? 'commission' : 'deal',
            'severity'      => 'info',
            'summary'       => $r->action,
            'actor_name'    => 'You',
            'actor_role'    => 'Referrer',
            'related_label' => $r->lead_name,
            'related_type'  => 'deal',
            'related_id'    => $r->lead_id,
            'occurred_at'   => $r->created_at ?? now(),
            'action_url'    => "/reseller/{$tenantId}/deals/{$r->lead_id}",
            'action_needed' => false,
            'source'        => 'lead_history',
        ]))->toArray();
    }

    private function resellerPendingApprovals(string $tenantId, string $resellerName): array
    {
        try {
            $reseller = DB::table('resellers')
                ->where('tenant_id', $tenantId)
                ->whereRaw('LOWER(name) = ?', [strtolower($resellerName)])
                ->value('id');

            if (!$reseller) return [];

            $rows = DB::table('deal_approval_requests as dar')
                ->join('leads as l', 'l.id', '=', 'dar.deal_id')
                ->where('dar.tenant_id', $tenantId)
                ->whereNull('l.deleted_at')
                ->where('l.status', '!=', 'archived')
                ->whereIn('dar.status', ['pending', 'clarification_requested'])
                ->where('dar.requested_by_type', 'reseller')
                ->where('dar.requested_by_id', $reseller)
                ->select('dar.id', 'dar.type', 'dar.status', 'dar.visible_response', 'dar.created_at', 'l.id as lead_id', 'l.name as lead_name')
                ->orderByDesc('dar.created_at')
                ->limit(5)
                ->get();

            return $rows->map(function ($r) use ($tenantId) {
                $isClarify  = $r->status === 'clarification_requested';
                $hasReply   = $isClarify && !empty($r->visible_response);
                $summary    = match(true) {
                    $r->type === 'deal_stage_move'  => "Stage approval pending review: {$r->lead_name}",
                    $hasReply                       => "Reply sent — waiting for admin decision: {$r->lead_name}",
                    $isClarify                      => "Clarification needed — your input required: {$r->lead_name}",
                    default                         => "Archive request pending review: {$r->lead_name}",
                };
                return $this->make([
                    'type'          => $r->type === 'deal_archive' ? 'pending_archive_request' : 'pending_stage_approval',
                    'category'      => 'deal',
                    'severity'      => ($isClarify && !$hasReply) ? 'high' : 'medium',
                    'summary'       => $summary,
                    'actor_name'    => 'You',
                    'actor_role'    => 'Referrer',
                    'related_label' => $r->lead_name,
                    'related_type'  => 'deal',
                    'related_id'    => $r->lead_id,
                    'occurred_at'   => $r->created_at ?? now(),
                    'action_url'    => "/reseller/{$tenantId}/deals/{$r->lead_id}",
                    'action_label'  => ($isClarify && !$hasReply) ? 'Respond to Clarification' : 'View Deal',
                    'action_needed' => ($isClarify && !$hasReply),
                    'source'        => 'deal_approval_requests',
                ]);
            })->toArray();
        } catch (\Throwable $e) {
            Log::warning('[CriticalActionService] resellerPendingApprovals failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    private function resellerStalledDeals(string $tenantId, string $resellerName): array
    {
        $lower = strtolower($resellerName);
        $rows = DB::table('leads')
            ->where('tenant_id', $tenantId)
            ->where(fn($q) => $q
                ->whereRaw('LOWER(reseller_name) = ?', [$lower])
                ->orWhereExists(fn($sub) => $sub
                    ->from('commission_splits')
                    ->whereColumn('commission_splits.lead_id', 'leads.id')
                    ->whereRaw('LOWER(commission_splits.reseller_name) = ?', [$lower])
                )
            )
            ->whereIn('status', ['active', 'expiring'])
            ->where('updated_at', '<', now()->subDays(14))
            ->whereNull('deleted_at')
            ->select('id', 'name', 'stage', 'updated_at')
            ->orderBy('updated_at')
            ->limit(3)
            ->get();

        return $rows->map(fn($r) => $this->make([
            'type'          => 'deal_stalled',
            'category'      => 'deal',
            'severity'      => 'medium',
            'summary'       => "Deal hasn't been updated in 14+ days: {$r->name}",
            'actor_name'    => 'System',
            'actor_role'    => 'System',
            'related_label' => $r->name,
            'related_type'  => 'deal',
            'related_id'    => $r->id,
            'occurred_at'   => $r->updated_at ?? now(),
            'action_url'    => "/reseller/{$tenantId}/deals/{$r->id}",
            'action_label'  => 'Update Deal',
            'action_needed' => true,
            'source'        => 'leads',
            'description'   => 'Add a note or move this deal to keep it active.',
        ]))->toArray();
    }

    private function resellerCommissionUpdates(string $tenantId, string $resellerName): array
    {
        $lower = strtolower($resellerName);
        $rows = DB::table('leads')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->where(fn($q) => $q
                ->whereRaw('LOWER(reseller_name) = ?', [$lower])
                ->orWhereExists(fn($sub) => $sub
                    ->from('commission_splits')
                    ->whereColumn('commission_splits.lead_id', 'leads.id')
                    ->whereRaw('LOWER(commission_splits.reseller_name) = ?', [$lower])
                )
            )
            ->whereIn('commission_status', ['locked', 'paid'])
            ->where('updated_at', '>', now()->subDays(7))
            ->select('id', 'name', 'commission_status', 'deal_value', 'updated_at')
            ->orderByDesc('updated_at')
            ->limit(3)
            ->get();

        return $rows->map(fn($r) => $this->make([
            'type'          => $r->commission_status === 'paid' ? 'commission_paid' : 'commission_locked',
            'category'      => 'commission',
            'severity'      => $r->commission_status === 'paid' ? 'info' : 'medium',
            'summary'       => $r->commission_status === 'paid'
                ? "Commission paid for: {$r->name}"
                : "Commission locked — review your deal: {$r->name}",
            'actor_name'    => 'System',
            'actor_role'    => 'System',
            'related_label' => $r->name,
            'related_type'  => 'deal',
            'related_id'    => $r->id,
            'occurred_at'   => $r->updated_at ?? now(),
            'action_url'    => "/reseller/{$tenantId}/deals/{$r->id}",
            'action_label'  => 'View Deal',
            'action_needed' => $r->commission_status !== 'paid',
            'source'        => 'leads',
        ]))->toArray();
    }

    private function resellerImportEvents(string $tenantId, string $resellerId): array
    {
        try {
            $rows = DB::table('activity_logs')
                ->where('tenant_id', $tenantId)
                ->where('entity', 'reseller')
                ->where('entity_id', $resellerId)
                ->whereIn('action', ['deal_import_completed', 'contacts_import_completed'])
                ->where('created_at', '>', now()->subDays(30))
                ->orderByDesc('created_at')
                ->limit(5)
                ->get();

            return $rows->map(function ($r) use ($tenantId) {
                $meta       = is_string($r->metadata) ? json_decode($r->metadata, true) : (array) ($r->metadata ?? []);
                $isContacts = $r->action === 'contacts_import_completed';
                $typeLabel  = $isContacts ? 'Contacts import' : 'Deal import';
                $fileName   = $meta['file_name'] ?? 'file';
                $created    = (int) ($meta['created'] ?? 0);
                $failed     = (int) ($meta['failed'] ?? 0);
                $skipped    = (int) ($meta['skipped'] ?? 0);
                $batchId    = $meta['batch_id'] ?? null;

                $detail    = "{$created} imported" . ($skipped > 0 ? ", {$skipped} skipped" : '') . ($failed > 0 ? ", {$failed} failed" : '');
                $actionUrl = $isContacts
                    ? "/reseller/{$tenantId}/contacts/imports" . ($batchId ? "/{$batchId}/report" : '')
                    : "/reseller/{$tenantId}/deals/imports"   . ($batchId ? "/{$batchId}/report" : '');

                return $this->make([
                    'type'          => $r->action,
                    'category'      => 'import',
                    'severity'      => $failed > 0 ? 'medium' : 'info',
                    'summary'       => "{$typeLabel} completed: {$fileName} — {$detail}",
                    'actor_name'    => 'You',
                    'actor_role'    => 'Referrer',
                    'related_label' => $fileName,
                    'related_type'  => 'import',
                    'related_id'    => $batchId,
                    'occurred_at'   => $r->created_at ?? now(),
                    'action_url'    => $actionUrl,
                    'action_label'  => 'View Report',
                    'action_needed' => $failed > 0,
                    'source'        => 'activity_logs',
                ]);
            })->toArray();
        } catch (\Throwable $e) {
            Log::warning('[CriticalActionService] resellerImportEvents failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    private function pendingExportRequests(string $tenantId): array
    {
        try {
            $rows = DB::table('export_requests')
                ->where('tenant_id', $tenantId)
                ->whereIn('status', ['pending', 'failed'])
                ->select('id', 'status', 'requester_type', 'requester_id', 'requester_role', 'export_type', 'is_sensitive', 'reason', 'created_at')
                ->orderByDesc('created_at')
                ->limit(10)
                ->get();

            return $rows->map(fn($r) => $this->make([
                'type'          => $r->status === 'failed' ? 'export_failed' : 'export_approval_pending',
                'category'      => 'export',
                'severity'      => $r->status === 'failed' ? 'high' : ($r->is_sensitive ? 'high' : 'medium'),
                'summary'       => $r->status === 'failed'
                    ? "Export failed: {$r->export_type} data — retry or investigate"
                    : "Export approval needed: {$r->export_type} data",
                'actor_name'    => ucfirst($r->requester_role),
                'actor_role'    => ucfirst($r->requester_role),
                'related_label' => ucwords(str_replace('_', ' ', $r->export_type)) . ' export',
                'related_type'  => 'export_request',
                'related_id'    => $r->id,
                'occurred_at'   => $r->created_at ?? now(),
                'action_url'    => "/tenant/{$tenantId}/exports/{$r->id}",
                'action_label'  => $r->status === 'failed' ? 'View Failed Export' : 'Review Request',
                'action_needed' => true,
                'source'        => 'export_requests',
                'meta'          => ['is_sensitive' => (bool) $r->is_sensitive, 'status' => $r->status],
            ]))->toArray();
        } catch (\Throwable $e) {
            Log::warning('[CriticalActionService] pendingExportRequests failed', ['tenant_id' => $tenantId, 'error' => $e->getMessage()]);
            return [];
        }
    }

    // ── New source: extension requests (admin view) ────────────────

    private function pendingArchiveRequests(string $tenantId): array
    {
        try {
            $rows = DB::table('deal_approval_requests as r')
                ->join('leads as l', 'l.id', '=', 'r.deal_id')
                ->where('r.tenant_id', $tenantId)
                ->whereNull('l.deleted_at')
                ->where('r.type', 'deal_archive')
                ->where(fn($q) => $q
                    ->where('r.status', 'pending')
                    ->orWhere(fn($q2) => $q2->where('r.status', 'clarification_requested')->whereNotNull('r.visible_response'))
                )
                ->select(
                    'r.id', 'r.status', 'r.reason', 'r.created_at', 'r.requested_by_id',
                    'r.request_payload', 'r.visible_response',
                    'l.id as lead_id', 'l.name as lead_name', 'l.reseller_name', 'l.stage'
                )
                ->orderBy('r.created_at')
                ->limit(10)
                ->get();

            return $rows->map(function ($r) use ($tenantId) {
                $payload      = is_string($r->request_payload) ? json_decode($r->request_payload, true) : (array) ($r->request_payload ?? []);
                $referrerName = $payload['referrer_name'] ?? $r->reseller_name ?? 'Referrer';
                $reason       = \Illuminate\Support\Str::limit($r->reason ?? '', 80);
                $replied      = !empty($r->visible_response);

                return $this->make([
                    'type'          => 'archive_request_pending',
                    'category'      => 'deal',
                    'severity'      => 'high',
                    'summary'       => $replied
                        ? "Referrer replied to clarification: {$r->lead_name}"
                        : "Archive request pending: {$r->lead_name}",
                    'actor_name'    => $referrerName,
                    'actor_role'    => 'Referrer',
                    'related_label' => $r->lead_name,
                    'related_type'  => 'deal',
                    'related_id'    => $r->lead_id,
                    'occurred_at'   => $r->created_at ?? now(),
                    'action_url'    => "/tenant/{$tenantId}/deals/archive-requests/{$r->id}",
                    'action_label'  => $replied ? 'Review Reply' : 'Review Archive Request',
                    'action_needed' => true,
                    'source'        => 'deal_approval_requests',
                    'meta'          => ['reason' => $reason, 'stage' => $r->stage, 'approval_id' => $r->id, 'replied' => $replied],
                ]);
            })->toArray();
        } catch (\Throwable $e) {
            Log::warning('[CriticalActionService] pendingArchiveRequests failed', ['tenant_id' => $tenantId, 'error' => $e->getMessage()]);
            return [];
        }
    }

    private function pendingStageMoveRequests(string $tenantId): array
    {
        try {
            $rows = DB::table('deal_approval_requests as r')
                ->join('leads as l', 'l.id', '=', 'r.deal_id')
                ->where('r.tenant_id', $tenantId)
                ->whereNull('l.deleted_at')
                ->where('r.type', 'deal_stage_move')
                ->where('r.status', 'pending')
                ->select(
                    'r.id', 'r.reason', 'r.created_at', 'r.request_payload', 'r.missing_requirements',
                    'l.id as lead_id', 'l.name as lead_name', 'l.reseller_name', 'l.stage'
                )
                ->orderBy('r.created_at')
                ->limit(10)
                ->get();

            return $rows->map(function ($r) use ($tenantId) {
                $payload = is_string($r->request_payload) ? json_decode($r->request_payload, true) : (array) ($r->request_payload ?? []);
                $referrerName = $payload['referrer_name'] ?? $r->reseller_name ?? 'Referrer';
                $targetStage  = $payload['target_stage'] ?? '?';
                $missingCount = 0;
                if ($r->missing_requirements) {
                    $missing = is_string($r->missing_requirements) ? json_decode($r->missing_requirements, true) : (array) ($r->missing_requirements ?? []);
                    $missingCount = count($missing);
                }

                $summary = $missingCount > 0
                    ? "Stage move needs review: {$r->lead_name} → " . ucfirst(str_replace('_', ' ', $targetStage)) . " ({$missingCount} unmet requirement" . ($missingCount > 1 ? 's' : '') . ')'
                    : "Stage move requested: {$r->lead_name} → " . ucfirst(str_replace('_', ' ', $targetStage));

                return $this->make([
                    'type'          => 'stage_move_request_pending',
                    'category'      => 'deal',
                    'severity'      => $missingCount > 0 ? 'high' : 'medium',
                    'summary'       => $summary,
                    'actor_name'    => $referrerName,
                    'actor_role'    => 'Referrer',
                    'related_label' => $r->lead_name,
                    'related_type'  => 'deal',
                    'related_id'    => $r->lead_id,
                    'occurred_at'   => $r->created_at ?? now(),
                    'action_url'    => "/tenant/{$tenantId}/deals/{$r->lead_id}",
                    'action_label'  => 'Review & Approve',
                    'action_needed' => true,
                    'source'        => 'deal_approval_requests',
                    'meta'          => ['target_stage' => $targetStage, 'missing_count' => $missingCount, 'approval_id' => $r->id],
                ]);
            })->toArray();
        } catch (\Throwable $e) {
            Log::warning('[CriticalActionService] pendingStageMoveRequests failed', ['tenant_id' => $tenantId, 'error' => $e->getMessage()]);
            return [];
        }
    }

    private function pendingExtensionRequests(string $tenantId): array
    {
        try {
            $rows = DB::table('deal_assignment_extension_requests as r')
                ->join('leads as l', 'l.id', '=', 'r.deal_id')
                ->where('r.tenant_id', $tenantId)
                ->whereNull('l.deleted_at')
                ->whereNull('r.batch_id')  // Batch items are shown via pendingBulkExtensionBatches()
                ->whereIn('r.status', ['pending_review', 'clarification_requested'])
                ->select(
                    'r.id', 'r.status', 'r.requested_days', 'r.reason',
                    'r.requested_by_role', 'r.created_at',
                    'l.id as lead_id', 'l.name as lead_name', 'l.reseller_name'
                )
                ->orderBy('r.created_at')
                ->limit(10)
                ->get();

            return $rows->map(fn($r) => $this->make([
                'type'          => 'extension_request_pending',
                'category'      => 'deal',
                'severity'      => 'high',
                'summary'       => $r->status === 'pending_review'
                    ? "Extension request awaiting review: {$r->lead_name}"
                    : "Extension request needs clarification: {$r->lead_name}",
                'actor_name'    => $r->reseller_name ?? ucfirst($r->requested_by_role ?? 'Referrer'),
                'actor_role'    => 'Referrer',
                'related_label' => $r->lead_name,
                'related_type'  => 'deal',
                'related_id'    => $r->lead_id,
                'occurred_at'   => $r->created_at ?? now(),
                'action_url'    => "/tenant/{$tenantId}/deals/{$r->lead_id}?extension_request_id={$r->id}",
                'action_label'  => 'Review Request',
                'action_needed' => true,
                'source'        => 'deal_assignment_extension_requests',
                'meta'          => ['status' => $r->status, 'requested_days' => $r->requested_days, 'extension_request_id' => $r->id],
            ]))->toArray();
        } catch (\Throwable $e) {
            Log::warning('[CriticalActionService] pendingExtensionRequests failed', ['tenant_id' => $tenantId, 'error' => $e->getMessage()]);
            return [];
        }
    }

    // ── New source: failed/conflicted rollbacks ─────────────────────

    private function failedRollbacks(string $tenantId): array
    {
        try {
            $rows = DB::table('import_rollbacks as rb')
                ->join('import_batches as b', 'b.id', '=', 'rb.import_batch_id')
                ->where('rb.tenant_id', $tenantId)
                ->whereIn('rb.status', ['failed', 'completed_with_warnings'])
                ->where('rb.created_at', '>', now()->subDays(14))
                ->select(
                    'rb.id', 'rb.import_batch_id', 'rb.status', 'rb.records_conflict', 'rb.records_failed',
                    'rb.created_at', 'b.file_name'
                )
                ->orderByDesc('rb.created_at')
                ->limit(5)
                ->get();

            return $rows->map(fn($r) => $this->make([
                'type'          => $r->status === 'failed' ? 'rollback_failed' : 'rollback_conflict',
                'category'      => 'import',
                'severity'      => $r->status === 'failed' ? 'high' : 'medium',
                'summary'       => $r->status === 'failed'
                    ? "Import rollback failed: {$r->file_name}"
                    : "Import rollback completed with conflicts: {$r->file_name}",
                'actor_name'    => 'System',
                'actor_role'    => 'System',
                'related_label' => $r->file_name,
                'related_type'  => 'import',
                'related_id'    => $r->id,
                'occurred_at'   => $r->created_at ?? now(),
                'action_url'    => "/tenant/{$tenantId}/imports/{$r->import_batch_id}/rollback/{$r->id}",
                'action_label'  => 'View Rollback',
                'action_needed' => true,
                'source'        => 'import_rollbacks',
                'meta'          => ['records_conflict' => $r->records_conflict, 'records_failed' => $r->records_failed],
            ]))->toArray();
        } catch (\Throwable $e) {
            Log::warning('[CriticalActionService] failedRollbacks failed', ['tenant_id' => $tenantId, 'error' => $e->getMessage()]);
            return [];
        }
    }

    // ── Bulk extension request batches (admin view) ────────────────

    private function pendingBulkExtensionBatches(string $tenantId): array
    {
        try {
            $rows = DB::table('deal_extension_request_batches as b')
                ->leftJoin('resellers as r', 'r.id', '=', 'b.requested_by_reseller_id')
                ->where('b.tenant_id', $tenantId)
                ->whereIn('b.status', ['pending', 'partially_approved', 'partially_declined'])
                ->select(
                    'b.id', 'b.batch_reference', 'b.status', 'b.total_items',
                    'b.pending_count', 'b.skipped_count', 'b.created_at',
                    'r.name as reseller_name'
                )
                ->orderBy('b.created_at')
                ->limit(10)
                ->get();

            return $rows->map(function ($r) use ($tenantId) {
                $actionable = (int)$r->pending_count + (int)$r->skipped_count;
                $dealWord   = $actionable === 1 ? 'deal' : 'deals';
                return $this->make([
                'type'          => 'bulk_extension_request_pending',
                'category'      => 'deal',
                'severity'      => 'high',
                'summary'       => "Extension request: {$actionable} {$dealWord} need review — from " . ($r->reseller_name ?? 'Referrer'),
                'actor_name'    => $r->reseller_name ?? 'Referrer',
                'actor_role'    => 'Referrer',
                'related_label' => $r->batch_reference ?? 'Batch',
                'related_type'  => 'extension_batch',
                'related_id'    => $r->id,
                'occurred_at'   => $r->created_at ?? now(),
                'action_url'    => "/tenant/{$tenantId}/extension-requests/{$r->id}",
                'action_label'  => 'Review Requests',
                'action_needed' => true,
                'source'        => 'deal_extension_request_batches',
                'meta'          => [
                    'batch_id'      => $r->id,
                    'total_items'   => $r->total_items,
                    'pending_count' => $r->pending_count,
                    'skipped_count' => $r->skipped_count,
                    'status'        => $r->status,
                ],
            ]);
            })->toArray();
        } catch (\Throwable $e) {
            Log::warning('[CriticalActionService] pendingBulkExtensionBatches failed', ['tenant_id' => $tenantId, 'error' => $e->getMessage()]);
            return [];
        }
    }

    // ── Referrer bulk extension requests ───────────────────────────

    private function resellerBulkExtensionBatches(string $tenantId, string $resellerName): array
    {
        try {
            $reseller = DB::table('resellers')
                ->where('tenant_id', $tenantId)
                ->whereRaw('LOWER(name) = ?', [strtolower($resellerName)])
                ->value('id');

            if (!$reseller) return [];

            $rows = DB::table('deal_extension_request_batches')
                ->where('tenant_id', $tenantId)
                ->where('requested_by_reseller_id', $reseller)
                ->whereIn('status', ['pending', 'partially_approved', 'partially_declined'])
                ->select('id', 'batch_reference', 'status', 'total_items', 'pending_count', 'approved_count', 'declined_count', 'created_at')
                ->orderByDesc('created_at')
                ->limit(5)
                ->get();

            return $rows->map(fn($r) => $this->make([
                'type'          => 'my_bulk_extension_request',
                'category'      => 'deal',
                'severity'      => 'medium',
                'summary'       => "Bulk extension request pending review: {$r->total_items} deal" . ($r->total_items > 1 ? 's' : ''),
                'actor_name'    => 'You',
                'actor_role'    => 'Referrer',
                'related_label' => $r->batch_reference ?? 'Bulk Request',
                'related_type'  => 'extension_batch',
                'related_id'    => $r->id,
                'occurred_at'   => $r->created_at ?? now(),
                'action_url'    => "/reseller/{$tenantId}/extension-requests/{$r->id}",
                'action_label'  => 'View Request',
                'action_needed' => false,
                'source'        => 'deal_extension_request_batches',
                'meta'          => [
                    'batch_id'      => $r->id,
                    'total_items'   => $r->total_items,
                    'pending_count' => $r->pending_count,
                    'approved_count'=> $r->approved_count,
                    'declined_count'=> $r->declined_count,
                    'status'        => $r->status,
                ],
            ]))->toArray();
        } catch (\Throwable $e) {
            Log::warning('[CriticalActionService] resellerBulkExtensionBatches failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    // ── New source: billing issues (gated by canSeeBilling) ────────

    private function billingIssues(string $tenantId): array
    {
        try {
            $sub = DB::table('subscriptions')
                ->where('tenant_id', $tenantId)
                ->orderByDesc('created_at')
                ->select('id', 'status', 'trial_end_date', 'updated_at')
                ->first();

            if (!$sub) return [];

            $actions = [];

            if ($sub->status === 'suspended') {
                $actions[] = $this->make([
                    'type'          => 'subscription_suspended',
                    'category'      => 'billing',
                    'severity'      => 'urgent',
                    'summary'       => 'Workspace subscription is suspended — access may be restricted',
                    'actor_name'    => 'System',
                    'actor_role'    => 'System',
                    'related_label' => 'Subscription',
                    'related_type'  => 'billing',
                    'related_id'    => $sub->id ?? null,
                    'occurred_at'   => $sub->updated_at ?? now(),
                    'action_url'    => "/tenant/{$tenantId}/billing",
                    'action_label'  => 'Review Billing',
                    'action_needed' => true,
                    'source'        => 'subscriptions',
                ]);
            } elseif ($sub->status === 'past_due') {
                $actions[] = $this->make([
                    'type'          => 'payment_overdue',
                    'category'      => 'billing',
                    'severity'      => 'urgent',
                    'summary'       => 'Payment overdue — subscription at risk of suspension',
                    'actor_name'    => 'System',
                    'actor_role'    => 'System',
                    'related_label' => 'Subscription',
                    'related_type'  => 'billing',
                    'related_id'    => $sub->id ?? null,
                    'occurred_at'   => $sub->updated_at ?? now(),
                    'action_url'    => "/tenant/{$tenantId}/billing",
                    'action_label'  => 'Update Payment',
                    'action_needed' => true,
                    'source'        => 'subscriptions',
                ]);
            } elseif ($sub->status === 'active') {
                // Surface payment_failed during the retry window (before subscription goes past_due)
                $failedPayment = DB::table('payments')
                    ->where('tenant_id', $tenantId)
                    ->where('status', 'failed')
                    ->where('retry_count', '<', 3)
                    ->orderByDesc('created_at')
                    ->first();

                if ($failedPayment) {
                    $actions[] = $this->make([
                        'type'          => 'payment_failed',
                        'category'      => 'billing',
                        'severity'      => 'urgent',
                        'summary'       => 'Payment failed — retrying automatically. Update your payment method to avoid suspension.',
                        'actor_name'    => 'System',
                        'actor_role'    => 'System',
                        'related_label' => 'Payment',
                        'related_type'  => 'billing',
                        'related_id'    => $failedPayment->id ?? null,
                        'occurred_at'   => $failedPayment->updated_at ?? now(),
                        'action_url'    => "/tenant/{$tenantId}/billing",
                        'action_label'  => 'Update Payment Method',
                        'action_needed' => true,
                        'source'        => 'payments',
                    ]);
                }
            } elseif ($sub->status === 'trial' && !empty($sub->trial_end_date)) {
                $daysLeft = now()->diffInDays(\Carbon\Carbon::parse($sub->trial_end_date), false);
                if ($daysLeft >= 0 && $daysLeft <= 7) {
                    $actions[] = $this->make([
                        'type'          => 'trial_ending',
                        'category'      => 'billing',
                        'severity'      => $daysLeft <= 2 ? 'high' : 'medium',
                        'summary'       => "Trial ending in {$daysLeft} day" . ($daysLeft === 1 ? '' : 's') . ' — upgrade to keep access',
                        'actor_name'    => 'System',
                        'actor_role'    => 'System',
                        'related_label' => 'Trial subscription',
                        'related_type'  => 'billing',
                        'related_id'    => $sub->id ?? null,
                        'occurred_at'   => now(),
                        'action_url'    => "/tenant/{$tenantId}/billing",
                        'action_label'  => 'Upgrade Plan',
                        'action_needed' => true,
                        'source'        => 'subscriptions',
                        'meta'          => ['days_left' => $daysLeft],
                    ]);
                }
            }

            return $actions;
        } catch (\Throwable $e) {
            Log::warning('[CriticalActionService] billingIssues failed', ['tenant_id' => $tenantId, 'error' => $e->getMessage()]);
            return [];
        }
    }

    private function resellerOverdueTasks(string $tenantId, string $resellerId): array
    {
        if (!self::tableExists('tasks')) return [];

        try {
            $tasks = DB::table('tasks')
                ->where('tenant_id', $tenantId)
                ->whereNull('deleted_at')
                ->where('assigned_to_type', 'reseller')
                ->where('assigned_to_id', $resellerId)
                ->whereNotIn('status', ['completed', 'cancelled', 'archived'])
                ->where('due_at', '<', now())
                ->select('id', 'title', 'priority', 'due_at', 'created_at')
                ->orderBy('due_at')
                ->limit(5)
                ->get();

            return $tasks->map(fn($t) => $this->make([
                'type'          => 'overdue_task',
                'category'      => 'task',
                'severity'      => in_array($t->priority, ['urgent', 'high']) ? 'high' : 'medium',
                'summary'       => "Overdue task: {$t->title}",
                'actor_name'    => 'You',
                'actor_role'    => 'Referrer',
                'related_label' => $t->title,
                'related_type'  => 'task',
                'related_id'    => $t->id,
                'occurred_at'   => $t->due_at ?? $t->created_at,
                'action_url'    => "/reseller/{$tenantId}/tasks",
                'action_label'  => 'View Tasks',
                'action_needed' => true,
                'source'        => 'tasks',
            ]))->toArray();
        } catch (\Throwable $e) {
            Log::warning('[CriticalActionService] resellerOverdueTasks failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    private function resellerFailedExports(string $tenantId, string $resellerId): array
    {
        if (!self::tableExists('export_requests')) return [];

        try {
            $rows = DB::table('export_requests')
                ->where('tenant_id', $tenantId)
                ->where('requester_type', 'reseller')
                ->where('requester_id', $resellerId)
                ->where('status', 'failed')
                ->where('updated_at', '>', now()->subDays(30))
                ->orderByDesc('updated_at')
                ->limit(5)
                ->get(['id', 'export_type', 'updated_at']);

            return $rows->map(fn($r) => $this->make([
                'type'          => 'export_failed',
                'category'      => 'export',
                'severity'      => 'normal',
                'description'   => 'This export failed permanently — there is no auto-retry path. Start a new export from the dashboard.',
                'summary'       => 'Export failed: ' . ucfirst(str_replace('_', ' ', $r->export_type)),
                'actor_name'    => 'System',
                'actor_role'    => 'System',
                'related_label' => ucfirst(str_replace('_', ' ', $r->export_type)) . ' export',
                'related_type'  => 'export',
                'related_id'    => $r->id,
                'occurred_at'   => $r->updated_at ?? now(),
                'action_url'    => null,
                'action_label'  => null,
                'action_needed' => false,
                'source'        => 'export_requests',
            ]))->toArray();
        } catch (\Throwable $e) {
            Log::warning('[CriticalActionService] resellerFailedExports failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    // ── New Referrer source: extension request responses ───────────

    private function resellerExtensionRequests(string $tenantId, string $resellerName): array
    {
        try {
            $lower = strtolower($resellerName);
            $rows = DB::table('deal_assignment_extension_requests as r')
                ->join('leads as l', 'l.id', '=', 'r.deal_id')
                ->where('r.tenant_id', $tenantId)
                ->whereNull('l.deleted_at')
                ->where(fn($q) => $q
                    ->whereRaw('LOWER(l.reseller_name) = ?', [$lower])
                    ->orWhereExists(fn($sub) => $sub
                        ->from('commission_splits')
                        ->whereColumn('commission_splits.lead_id', 'l.id')
                        ->whereRaw('LOWER(commission_splits.reseller_name) = ?', [$lower])
                    )
                )
                ->whereIn('r.status', ['pending_review', 'clarification_requested'])
                ->select('r.id', 'r.status', 'r.requested_days', 'r.created_at', 'l.id as lead_id', 'l.name as lead_name')
                ->orderByDesc('r.created_at')
                ->limit(5)
                ->get();

            return $rows->map(fn($r) => $this->make([
                'type'          => 'my_extension_request',
                'category'      => 'deal',
                'severity'      => 'medium',
                'summary'       => $r->status === 'pending_review'
                    ? "Your extension request is pending review: {$r->lead_name}"
                    : "Clarification needed for your extension request: {$r->lead_name}",
                'actor_name'    => 'You',
                'actor_role'    => 'Referrer',
                'related_label' => $r->lead_name,
                'related_type'  => 'deal',
                'related_id'    => $r->lead_id,
                'occurred_at'   => $r->created_at ?? now(),
                'action_url'    => "/reseller/{$tenantId}/deals/{$r->lead_id}",
                'action_label'  => 'View Deal',
                'action_needed' => $r->status === 'clarification_requested',
                'source'        => 'deal_assignment_extension_requests',
                'meta'          => ['status' => $r->status, 'requested_days' => $r->requested_days],
            ]))->toArray();
        } catch (\Throwable $e) {
            Log::warning('[CriticalActionService] resellerExtensionRequests failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    // ── New Referrer source: unread partner messages ───────────────

    private function resellerUnreadMessages(string $tenantId, string $resellerName): array
    {
        try {
            $reseller = DB::table('resellers')
                ->where('tenant_id', $tenantId)
                ->whereRaw('LOWER(name) = ?', [strtolower($resellerName)])
                ->select('id')
                ->first();

            if (!$reseller) return [];

            // Count partner_threads where this Referrer has unread partner messages
            $count = DB::table('partner_threads')
                ->where('tenant_id', $tenantId)
                ->where('reseller_id', $reseller->id)   // fixed: was partner_id (wrong column)
                ->where('reseller_unread', '>', 0)
                ->count();

            if ($count === 0) return [];

            return [$this->make([
                'type'          => 'partner_message_unread',
                'category'      => 'messaging',
                'severity'      => 'medium',
                'summary'       => "{$count} unread message" . ($count > 1 ? 's' : '') . ' from partners on your deals',
                'actor_name'    => 'Partner',
                'actor_role'    => 'Partner',
                'related_label' => 'Messages',
                'related_type'  => 'message',
                'related_id'    => null,
                'occurred_at'   => now(),
                'action_url'    => "/reseller/{$tenantId}/messages",
                'action_label'  => 'View Messages',
                'action_needed' => true,
                'source'        => 'partner_threads',
            ])];
        } catch (\Throwable $e) {
            Log::warning('[CriticalActionService] resellerUnreadMessages failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    // ── Task sources ───────────────────────────────────────────────

    private function overdueOpenTasks(string $tenantId): array
    {
        if (!self::tableExists('tasks')) return [];

        $tasks = DB::table('tasks')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->whereNotIn('status', ['completed', 'cancelled', 'archived'])
            ->where('category', '!=', 'request_form')
            ->where('due_at', '<', now())
            ->select('id', 'title', 'priority', 'assigned_to_id', 'due_at', 'created_at')
            ->orderBy('due_at')
            ->limit(5)
            ->get();

        return $tasks->map(fn($t) => $this->make([
            'type'          => 'overdue_task',
            'category'      => 'task',
            'severity'      => $t->priority === 'urgent' ? 'urgent' : ($t->priority === 'high' ? 'high' : 'medium'),
            'summary'       => "Overdue task: {$t->title}",
            'actor_name'    => 'System',
            'actor_role'    => 'System',
            'related_label' => $t->title,
            'related_type'  => 'task',
            'related_id'    => $t->id,
            'occurred_at'   => $t->due_at ?? $t->created_at,
            'action_url'    => "/tenant/{$tenantId}/tasks/{$t->id}",
            'action_label'  => 'View Task',
            'action_needed' => true,
            'source'        => 'tasks',
        ]))->toArray();
    }

    private function openRequestFormTasks(string $tenantId): array
    {
        if (!self::tableExists('tasks')) return [];

        $tasks = DB::table('tasks')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->where('status', 'open')
            ->where('category', 'request_form')
            ->select('id', 'title', 'requestor_name', 'created_at')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return $tasks->map(fn($t) => $this->make([
            'type'          => 'new_request_form_task',
            'category'      => 'task',
            'severity'      => 'high',
            'summary'       => "New request from {$t->requestor_name}: {$t->title}",
            'actor_name'    => $t->requestor_name ?? 'Public',
            'actor_role'    => 'Public',
            'related_label' => $t->title,
            'related_type'  => 'task',
            'related_id'    => $t->id,
            'occurred_at'   => $t->created_at,
            'action_url'    => "/tenant/{$tenantId}/tasks/{$t->id}",
            'action_label'  => 'View Task',
            'action_needed' => true,
            'source'        => 'request_form_tasks',
        ]))->toArray();
    }

    // ── LGU IDS: deals with pending default amount confirmation ────

    private function pendingDefaultAmounts(string $tenantId): array
    {
        if (! $this->isLguIdsTenant($tenantId)) return [];

        try {
            $rows = DB::table('leads')
                ->where('tenant_id', $tenantId)
                ->whereNotIn('status', ['expired', 'declined', 'archived'])
                ->whereNull('deleted_at')
                ->whereRaw("data->>'amount_defaulted' = 'true'")
                ->whereRaw("data->>'amount_confirmation_status' = 'pending'")
                ->select('id', 'name', 'reseller_name', 'stage', 'deal_value', 'updated_at')
                ->orderByRaw("CASE stage WHEN 'paid' THEN 0 WHEN 'signed' THEN 1 WHEN 'contract_sent' THEN 2 ELSE 3 END")
                ->limit(10)
                ->get();

            $advancedStages = ['contract_sent', 'signed', 'paid'];

            return $rows->map(fn($r) => $this->make([
                'type'          => 'default_amount_pending',
                'category'      => 'deal',
                'severity'      => in_array($r->stage ?? '', $advancedStages) ? 'high' : 'medium',
                'summary'       => 'Default deal amount unconfirmed: ' . $r->name,
                'actor_name'    => $r->reseller_name ?? 'Unassigned',
                'actor_role'    => 'Referrer',
                'related_label' => $r->name,
                'related_type'  => 'deal',
                'related_id'    => $r->id,
                'occurred_at'   => $r->updated_at ?? now(),
                'action_url'    => "/tenant/{$tenantId}/deals/{$r->id}",
                'action_label'  => 'Confirm Amount',
                'action_needed' => true,
                'source'        => 'leads',
                'meta'          => ['deal_value' => $r->deal_value, 'stage' => $r->stage],
                'description'   => 'This deal is using the LGU IDS default amount of ₱4,000,000. Confirm it or update it based on the actual contract value.',
            ]))->all();
        } catch (\Throwable $e) {
            Log::warning('[CriticalActionService] pendingDefaultAmounts failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Deals where a Referrer updated the deal amount in the last $since window.
     * Admins should review to ensure the new amount is correct before commission finalises.
     * Resolves automatically after 2 days (the admin has had time to review).
     */
    private function recentReferrerAmountChanges(string $tenantId, mixed $since = null): array
    {
        try {
            $cutoff = now()->subDays(2); // Only show within 2-day review window

            $rows = DB::table('lead_history as lh')
                ->join('leads as l', 'l.id', '=', 'lh.lead_id')
                ->where('lh.tenant_id', $tenantId)
                ->whereIn('lh.category', ['financial', 'commission'])
                ->where('lh.action', 'like', 'amount updated by referrer%')
                ->where('lh.created_at', '>=', $cutoff)
                ->where('l.commission_status', 'pending') // only pending — locked/paid are finalised
                ->whereNull('l.deleted_at')
                ->select('lh.lead_id', 'lh.actor_name', 'lh.created_at', 'lh.metadata',
                         'l.name as lead_name', 'l.stage', 'l.deal_value')
                ->orderByDesc('lh.created_at')
                ->limit(10)
                ->get();

            return $rows->map(function ($r) use ($tenantId) {
                $meta      = is_string($r->metadata) ? json_decode($r->metadata, true) : (array) ($r->metadata ?? []);
                $oldAmount = $meta['old_values']['deal_value'] ?? null;
                $newAmount = $meta['new_values']['deal_value'] ?? $r->deal_value;
                $reason    = $meta['new_values']['reason']     ?? null;

                $bodyParts = [$r->actor_name . ' changed the contract value'];
                if ($oldAmount !== null) {
                    $bodyParts[] = 'from ₱' . number_format($oldAmount, 0) . ' to ₱' . number_format($newAmount, 0);
                }
                if ($reason) {
                    $bodyParts[] = '— "' . \Illuminate\Support\Str::limit($reason, 60) . '"';
                }

                return $this->make([
                    'type'          => 'referrer_amount_change',
                    'category'      => 'deal',
                    'severity'      => 'low',
                    'summary'       => 'Referrer updated deal amount: ' . $r->lead_name,
                    'actor_name'    => $r->actor_name ?? 'Referrer',
                    'actor_role'    => 'Referrer',
                    'related_label' => $r->lead_name,
                    'related_type'  => 'deal',
                    'related_id'    => $r->lead_id,
                    'occurred_at'   => $r->created_at ?? now(),
                    'action_url'    => "/tenant/{$tenantId}/deals/{$r->lead_id}",
                    'action_label'  => 'Review Deal',
                    'action_needed' => false, // informational — admin should glance, not must-act
                    'source'        => 'lead_history',
                    'description'   => implode(' ', $bodyParts) . '. Review the new amount before finalising commission.',
                    'meta'          => ['old_amount' => $oldAmount, 'new_amount' => $newAmount, 'stage' => $r->stage],
                ]);
            })->all();
        } catch (\Throwable $e) {
            Log::warning('[CriticalActionService] recentReferrerAmountChanges failed', ['tenant_id' => $tenantId, 'error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Deals stuck in the same stage for > 14 days without any activity.
     * Surfaced as medium-severity admin action — someone needs to follow up.
     */
    private function stalledDeals(string $tenantId): array
    {
        try {
            $staleThreshold = now()->subDays(14);

            $rows = DB::table('leads')
                ->where('tenant_id', $tenantId)
                ->whereNotIn('stage', ['paid', 'archived'])
                ->whereIn('status', ['active', 'expiring'])
                ->where('updated_at', '<', $staleThreshold)
                ->whereNull('deleted_at')
                ->select('id', 'name', 'stage', 'updated_at', 'reseller_name')
                ->orderBy('updated_at')
                ->limit(10)
                ->get();

            return $rows->map(function ($r) use ($tenantId) {
                $daysStalled = (int) now()->diffInDays($r->updated_at);
                return $this->make([
                    'type'          => 'deal_stalled',
                    'category'      => 'deal',
                    'severity'      => $daysStalled >= 30 ? 'high' : 'medium',
                    'summary'       => 'Deal stalled at ' . ucwords(str_replace('_', ' ', $r->stage)) . ': ' . $r->name,
                    'actor_name'    => $r->reseller_name ?? 'Referrer',
                    'actor_role'    => 'Referrer',
                    'related_label' => $r->name,
                    'related_type'  => 'deal',
                    'related_id'    => $r->id,
                    'occurred_at'   => $r->updated_at,
                    'action_url'    => "/tenant/{$tenantId}/deals/{$r->id}",
                    'action_label'  => 'Review Deal',
                    'action_needed' => true,
                    'source'        => 'leads',
                    'description'   => "This deal has been in the '{$r->stage}' stage for {$daysStalled} days with no update. Follow up with the Referrer.",
                    'meta'          => ['days_stalled' => $daysStalled, 'stage' => $r->stage],
                ]);
            })->all();
        } catch (\Throwable $e) {
            Log::warning('[CriticalActionService] stalledDeals failed', ['tenant_id' => $tenantId, 'error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Deals that have reached 'signed' or 'paid' stage but commission_status is still 'pending'.
     * Admin should review and lock/pay commission to close the deal financially.
     */
    private function commissionReviewQueue(string $tenantId): array
    {
        try {
            $rows = DB::table('leads')
                ->where('tenant_id', $tenantId)
                ->whereIn('stage', ['signed', 'paid'])
                ->where('commission_status', 'pending')
                ->whereNull('deleted_at')
                ->select('id', 'name', 'stage', 'deal_value', 'reseller_name', 'updated_at')
                ->orderByRaw("CASE stage WHEN 'paid' THEN 0 ELSE 1 END")
                ->orderBy('updated_at')
                ->limit(10)
                ->get();

            return $rows->map(function ($r) use ($tenantId) {
                $isPaid = $r->stage === 'paid';
                return $this->make([
                    'type'          => 'commission_review_pending',
                    'category'      => 'commission',
                    'severity'      => $isPaid ? 'high' : 'medium',
                    'summary'       => 'Commission review needed: ' . $r->name,
                    'actor_name'    => $r->reseller_name ?? 'Referrer',
                    'actor_role'    => 'Referrer',
                    'related_label' => $r->name,
                    'related_type'  => 'deal',
                    'related_id'    => $r->id,
                    'occurred_at'   => $r->updated_at,
                    'action_url'    => "/tenant/{$tenantId}/deals/{$r->id}",
                    'action_label'  => 'Review & Finalise',
                    'action_needed' => true,
                    'source'        => 'leads',
                    'description'   => 'Deal has reached ' . ucfirst($r->stage) . ' stage but commission is still pending. Lock or pay commission to close this deal.',
                    'meta'          => ['stage' => $r->stage, 'deal_value' => $r->deal_value],
                ]);
            })->all();
        } catch (\Throwable $e) {
            Log::warning('[CriticalActionService] commissionReviewQueue failed', ['tenant_id' => $tenantId, 'error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Surface Google Calendar integrations that haven't synced in > 24h or whose token has likely expired.
     * Helps admins spot broken integrations before tasks/deals go unsynced.
     */
    private function staleGoogleCalendarIntegrations(string $tenantId): array
    {
        if (!self::tableExists('google_calendar_integrations')) return [];

        try {
            $stale = DB::table('google_calendar_integrations')
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->where(fn($q) =>
                    $q->where('token_expires_at', '<', now())
                      ->orWhere(fn($q2) =>
                          $q2->whereNotNull('last_synced_at')
                             ->where('last_synced_at', '<', now()->subHours(24))
                      )
                )
                ->select('id', 'google_email', 'token_expires_at', 'last_synced_at', 'tenant_user_id')
                ->orderBy('token_expires_at')          // ASC — most overdue first; indexed column
                ->limit(5)
                ->get();

            return $stale->map(fn($r) => $this->make([
                'type'          => 'gcal_integration_stale',
                'category'      => 'task',
                'severity'      => 'medium',
                'summary'       => "Google Calendar integration may need reconnecting" . ($r->google_email ? " ({$r->google_email})" : ''),
                'actor_name'    => $r->google_email ?? 'Team member',
                'actor_role'    => 'Admin',
                'related_label' => 'Google Calendar',
                'related_type'  => 'integration',
                'related_id'    => $r->id,
                'occurred_at'   => $r->token_expires_at ?? now(),
                'action_url'    => "/tenant/{$tenantId}/settings",
                'action_label'  => 'Manage Integration',
                'action_needed' => true,
                'source'        => 'google_calendar_integrations',
            ]))->toArray();
        } catch (\Throwable $e) {
            Log::warning('[CriticalActionService] staleGoogleCalendarIntegrations failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    // ── New Referrer signups (invited + not yet active — admin should welcome/onboard) ──

    private function newReferrerSignups(string $tenantId): array
    {
        try {
            $rows = DB::table('resellers')
                ->where('tenant_id', $tenantId)
                ->whereNull('deleted_at')
                ->where('status', 'invited')
                ->where('created_at', '>', now()->subDays(7))
                // Exclude referrers who already have an active deal — they've been
                // onboarded into the pipeline; "send setup link" prompt is misleading.
                ->whereNotExists(fn($q) => $q
                    ->from('leads')
                    ->where('leads.tenant_id', $tenantId)
                    ->whereNull('leads.deleted_at')
                    ->whereNotIn('leads.status', ['expired', 'declined'])
                    ->whereRaw('LOWER(leads.reseller_name) = LOWER(resellers.name)')
                )
                ->select('id', 'name', 'email', 'created_at')
                ->orderByDesc('created_at')
                ->limit(5)
                ->get();

            return $rows->map(fn($r) => $this->make([
                'type'          => 'new_referrer_invited',
                'category'      => 'user',
                'severity'      => 'info',
                'summary'       => "New Referrer invited: {$r->name}",
                'actor_name'    => $r->name ?? $r->email,
                'actor_role'    => 'Referrer',
                'related_label' => $r->name ?? $r->email,
                'related_type'  => 'referrer',
                'related_id'    => $r->id,
                'occurred_at'   => $r->created_at,
                'action_url'    => "/tenant/{$tenantId}/referrers/{$r->id}",
                'action_label'  => 'View Referrer',
                'action_needed' => true,
                'source'        => 'resellers',
                'description'   => 'A new Referrer has been invited and is awaiting account activation.',
            ]))->toArray();
        } catch (\Throwable $e) {
            Log::warning('[CriticalActionService] newReferrerSignups failed', ['tenant_id' => $tenantId, 'error' => $e->getMessage()]);
            return [];
        }
    }

    // ── New partners added to deals in the last 7 days ─────────────

    private function newPartnersOnDeals(string $tenantId): array
    {
        try {
            $rows = DB::table('deal_partner_splits as dps')
                ->join('leads as l', 'l.id', '=', 'dps.deal_id')
                ->where('dps.tenant_id', $tenantId)
                ->whereNull('dps.deleted_at')
                ->whereNull('l.deleted_at')
                ->where('dps.status', '!=', 'removed')
                ->where('dps.created_at', '>', now()->subDays(7))
                ->select('dps.id', 'dps.partner_name', 'dps.partner_email', 'dps.created_at', 'l.name as deal_name', 'l.id as deal_id')
                ->orderByDesc('dps.created_at')
                ->limit(5)
                ->get();

            return $rows->map(fn($r) => $this->make([
                'type'          => 'new_partner_on_deal',
                'category'      => 'deal',
                'severity'      => 'info',
                'summary'       => "New Partner added to \"{$r->deal_name}\"",
                'actor_name'    => $r->partner_name ?? $r->partner_email,
                'actor_role'    => 'Partner',
                'related_label' => $r->deal_name,
                'related_type'  => 'deal',
                'related_id'    => $r->deal_id,
                'occurred_at'   => $r->created_at,
                'action_url'    => "/tenant/{$tenantId}/deals/{$r->deal_id}",
                'action_label'  => 'View Deal',
                'action_needed' => false,
                'source'        => 'deal_partner_splits',
            ]))->toArray();
        } catch (\Throwable $e) {
            Log::warning('[CriticalActionService] newPartnersOnDeals failed', ['tenant_id' => $tenantId, 'error' => $e->getMessage()]);
            return [];
        }
    }

    // ── DTO factory ────────────────────────────────────────────────

    /**
     * Normalises all action fields and fills in standard taxonomy defaults.
     *
     * Full taxonomy (22-phase ACTIONS spec phase 2):
     *  id, tenant_id, actor/user scope, role_visibility, source_module,
     *  subject_type/id, action_type, title, description, severity, priority_score,
     *  due_at, status, CTA label/URL, metadata, created/resolved/dismissed/expires_at
     */
    private function make(array $data): array
    {
        $occurredAt = $data['occurred_at'] instanceof \Carbon\Carbon
            ? $data['occurred_at']
            : \Carbon\Carbon::parse($data['occurred_at']);

        // ── Priority score — map from severity + type bonus ───────────────────
        // Scores align with phase-6 spec:
        //   urgent=100, high≈85-95, medium≈70-80, low≈50-60, info=30
        $severityScores = ['urgent' => 100, 'high' => 85, 'medium' => 70, 'low' => 50, 'normal' => 50, 'info' => 30];
        $typeBonus = match ($data['type'] ?? '') {
            'subscription_suspended'     => 10,
            'deal_expiring'              => 10,
            'deal_expired'               => 8,
            'payment_failed'             => 10,
            'import_failed'              => 5,
            'rollback_failed'            => 5,
            'export_failed'              => 5,
            'archive_request_pending'    => 3,
            'extension_request_pending'  => 3,
            'stage_move_request_pending' => 3,
            'commission_review_pending'  => 2,
            default                      => 0,
        };
        $priorityScore = ($severityScores[$data['severity'] ?? 'info'] ?? 30) + $typeBonus;

        // ── Dismissibility (phase 18) ─────────────────────────────────────────
        // NOT dismissible: payment/billing failure, expired deal, required update
        // overdue, import failure, security issue.
        $notDismissibleTypes = [
            'subscription_suspended', 'payment_failed', 'payment_overdue', 'deal_expired',
            'import_failed', 'rollback_failed', 'overdue_task', 'export_failed',
            'archive_request_pending', 'extension_request_pending',
            'bulk_extension_request_pending',
            'stage_move_request_pending', 'trial_ending',
        ];
        $dismissible = ! in_array($data['type'] ?? '', $notDismissibleTypes, true)
            && in_array($data['severity'] ?? 'info', ['info', 'low', 'normal'], true);

        // ── Fingerprint for deduplication (phase 7) ──────────────────────────
        $fingerprint = md5(implode(':', [
            $data['type']         ?? '',
            $data['source']       ?? '',
            $data['related_type'] ?? '',
            $data['related_id']   ?? '__agg__',
        ]));

        // ── Role visibility (phase 3 / 16) ───────────────────────────────────
        $category = $data['category'] ?? '';
        $roleVisibility = match (true) {
            $category === 'billing'  => ['owner', 'admin', 'super_admin'],
            $category === 'export'   => ['owner', 'admin', 'manager', 'super_admin'],
            $category === 'user'     => ['owner', 'admin', 'manager', 'super_admin'],
            $category === 'activity' => ['owner', 'admin', 'manager', 'super_admin'],
            default                  => ['owner', 'admin', 'manager', 'super_admin', 'referrer', 'partner'],
        };

        // Store occurred_at as ISO string — Carbon instances cannot be safely
        // serialized/deserialized through PHP's object serializer (cache).
        // Callers that need Carbon should call Carbon::parse($item['occurred_at']).
        return array_merge([
            // Guaranteed defaults so view code never needs isset() guards
            'action_label'  => 'Open',
            'action_url'    => null,
            'action_needed' => false,
            'description'   => null,
            'meta'          => [],
            'due_at'        => null,
            'status'        => 'open',
            'resolved_at'   => null,
            'dismissed_at'  => null,
            'expires_at'    => null,
        ], $data, [
            // Always-computed fields that override anything the caller passes
            'occurred_at'    => $occurredAt->toIso8601String(),
            'occurred_ago'   => $occurredAt->diffForHumans(),
            'occurred_fmt'   => $occurredAt->format('M j, Y g:i A'),
            // Taxonomy fields (phase 2)
            'priority_score' => $priorityScore,
            'dismissible'    => $dismissible,
            'fingerprint'    => $fingerprint,
            'role_visibility'=> $roleVisibility,
            // subject_type / subject_id — spec aliases for related_type / related_id
            'subject_type'   => $data['related_type'] ?? null,
            'subject_id'     => $data['related_id']   ?? null,
            // action_type — spec field that mirrors the type key
            'action_type'    => $data['type']          ?? null,
            // source_module — spec alias for source
            'source_module'  => $data['source']        ?? null,
        ]);
    }

    // ── LGU IDS — tenant slug check (cached per process) ──────────

    private static array $lguIdsTenantCache = [];

    private function isLguIdsTenant(string $tenantId): bool
    {
        if (! isset(self::$lguIdsTenantCache[$tenantId])) {
            self::$lguIdsTenantCache[$tenantId] = DB::table('tenants')
                ->where('id', $tenantId)
                ->where('slug', 'lgu-ids')
                ->exists();
        }
        return self::$lguIdsTenantCache[$tenantId];
    }

    // ── LGU IDS — Referrer deals with no notes ────────────────────

    private function resellerDealsWithNoNotes(string $tenantId, string $resellerName): array
    {
        try {
            $lower = strtolower($resellerName);

            $rows = DB::table('leads')
                ->where('tenant_id', $tenantId)
                ->whereNull('deleted_at')
                ->whereIn('status', ['active', 'expiring'])
                ->where('stage', '!=', 'paid')
                ->where(fn($q) => $q
                    ->whereRaw('LOWER(reseller_name) = ?', [$lower])
                    ->orWhereExists(fn($cs) => $cs
                        ->select(DB::raw(1))
                        ->from('commission_splits')
                        ->whereColumn('commission_splits.lead_id', 'leads.id')
                        ->whereRaw('LOWER(commission_splits.reseller_name) = ?', [$lower])
                    )
                )
                ->whereNotExists(fn($dc) => $dc
                    ->select(DB::raw(1))
                    ->from('deal_comments')
                    ->whereColumn('deal_comments.deal_id', 'leads.id')
                    ->where('deal_comments.visibility', 'shared')
                    ->whereNull('deal_comments.deleted_at')
                    ->whereNull('deal_comments.parent_comment_id')
                )
                ->whereNotExists(fn($ln) => $ln
                    ->select(DB::raw(1))
                    ->from('lead_notes')
                    ->whereColumn('lead_notes.lead_id', 'leads.id')
                )
                ->select('id', 'name', 'stage', 'status', 'days_left', 'updated_at')
                ->orderByRaw("CASE WHEN status = 'expiring' THEN 0 ELSE 1 END")
                ->orderBy('days_left')
                ->limit(5)
                ->get();

            if ($rows->isEmpty()) {
                return [];
            }

            $count = $rows->count();
            $names = $rows->take(2)->pluck('name')->join(' and ');
            $suffix = $count > 2 ? " (+" . ($count - 2) . " more)" : '';

            return [$this->make([
                'type'          => 'lgu_ids_deals_no_notes',
                'category'      => 'deal',
                'severity'      => 'medium',
                'summary'       => $count === 1
                    ? "No notes yet on: {$rows->first()->name}"
                    : "{$count} active deals have no notes yet: {$names}{$suffix}",
                'actor_name'    => 'System',
                'actor_role'    => 'System',
                'related_label' => $count === 1 ? $rows->first()->name : "{$count} deals",
                'related_type'  => 'deal',
                'related_id'    => $rows->first()->id,
                'occurred_at'   => now(),
                'action_url'    => "/reseller/{$tenantId}/deals?filter=no_notes",
                'action_label'  => 'Add Notes',
                'action_needed' => true,
                'source'        => 'leads',
                'description'   => 'Adding notes to your deals increases visibility and speeds up approvals.',
                'meta'          => ['deal_count' => $count, 'deal_ids' => $rows->pluck('id')->all()],
            ])];
        } catch (\Throwable $e) {
            Log::warning('[CriticalActionService] resellerDealsWithNoNotes failed', ['error' => $e->getMessage()]);
            return [];
        }
    }
}
