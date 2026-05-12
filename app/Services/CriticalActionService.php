<?php

namespace App\Services;

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
    // Severity ordering for sorting
    private const SEVERITY_ORDER = ['urgent' => 0, 'high' => 1, 'medium' => 2, 'low' => 3, 'info' => 4];

    // ── Public API ─────────────────────────────────────────────────

    /**
     * Top N actions for the dashboard widget (admin/manager view).
     */
    public function dashboardSummary(string $tenantId, int $limit = 6, bool $canSeeBilling = false): array
    {
        $all = $this->forTenant($tenantId, ['billing' => $canSeeBilling, 'limit_per_source' => 5]);
        usort($all, fn($a, $b) =>
            (self::SEVERITY_ORDER[$a['severity']] ?? 9) <=> (self::SEVERITY_ORDER[$b['severity']] ?? 9)
            ?: $b['occurred_at']->timestamp <=> $a['occurred_at']->timestamp
        );
        return array_slice($all, 0, $limit);
    }

    /**
     * Full paginated list with search/filter for master list page.
     */
    public function masterList(string $tenantId, array $filters = [], int $perPage = 25): array
    {
        $all = $this->forTenant($tenantId, [
            'billing'          => $filters['can_see_billing'] ?? false,
            'limit_per_source' => 50,
            'since'            => $filters['since'] ?? null,
            'until'            => $filters['until'] ?? null,
        ]);

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

        // Sort by severity then recency
        usort($all, fn($a, $b) =>
            (self::SEVERITY_ORDER[$a['severity']] ?? 9) <=> (self::SEVERITY_ORDER[$b['severity']] ?? 9)
            ?: $b['occurred_at']->timestamp <=> $a['occurred_at']->timestamp
        );

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
     * Reseller-scoped recent actions for their dashboard.
     * Only shows actions on the reseller's own accessible deals.
     */
    public function forReseller(string $tenantId, string $resellerName, int $limit = 8, ?string $resellerId = null): array
    {
        $sources = [
            fn() => $this->resellerExpiringDeals($tenantId, $resellerName),
            fn() => $this->resellerExtensionRequests($tenantId, $resellerName),
            fn() => $this->resellerUnreadMessages($tenantId, $resellerName),
            fn() => $this->resellerLeadHistory($tenantId, $resellerName),
            fn() => $this->resellerCommissionUpdates($tenantId, $resellerName),
        ];

        if ($resellerId) {
            $sources[] = fn() => $this->resellerImportEvents($tenantId, $resellerId);
        }

        $items = [];
        foreach ($sources as $source) {
            try {
                $items = array_merge($items, $source());
            } catch (\Throwable $e) {
                Log::warning('[CriticalActionService] Reseller source failed', ['error' => $e->getMessage()]);
            }
        }

        usort($items, fn($a, $b) =>
            (self::SEVERITY_ORDER[$a['severity']] ?? 9) <=> (self::SEVERITY_ORDER[$b['severity']] ?? 9)
            ?: $b['occurred_at']->timestamp <=> $a['occurred_at']->timestamp
        );

        return array_slice($items, 0, $limit);
    }

    /**
     * Critical actions for a Partner — only their own deals and unread messages.
     */
    public function forPartner(string $partnerId, string $tenantId, int $limit = 6): array
    {
        $actions = [];

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

        usort($actions, fn($a, $b) =>
            (self::SEVERITY_ORDER[$a['severity']] ?? 9) <=> (self::SEVERITY_ORDER[$b['severity']] ?? 9)
        );

        return array_slice($actions, 0, $limit);
    }

    // ── Tenant-level aggregation ────────────────────────────────────

    private function forTenant(string $tenantId, array $opts = []): array
    {
        $limitPer    = $opts['limit_per_source'] ?? 10;
        $since       = $opts['since'] ?? now()->subDays(30);
        $canBilling  = $opts['billing'] ?? false;

        $sources = [
            fn() => $this->expiringDeals($tenantId),
            fn() => $this->expiredDeals($tenantId),
            fn() => $this->missingReferrerDeals($tenantId),
            fn() => $this->pendingArchiveRequests($tenantId),
            fn() => $this->pendingStageMoveRequests($tenantId),
            fn() => $this->pendingExtensionRequests($tenantId),
            fn() => $this->failedRollbacks($tenantId),
            fn() => $this->recentLeadHistory($tenantId, $limitPer, $since),
            fn() => $this->importEvents($tenantId, $limitPer),
            fn() => $this->pendingInvites($tenantId),
            fn() => $this->recentAcceptedInvites($tenantId, $limitPer, $since),
            fn() => $this->unrepliedMessages($tenantId),
            fn() => $this->unreadPartnerMessages($tenantId),
            fn() => $this->recentActivityLogs($tenantId, $limitPer, $since),
            fn() => $this->pendingExportRequests($tenantId),
            fn() => $this->overdueOpenTasks($tenantId),
            fn() => $this->openRequestFormTasks($tenantId),
            fn() => $this->pendingDefaultAmounts($tenantId),
            fn() => $this->recentReferrerAmountChanges($tenantId, $since),
            fn() => $this->stalledDeals($tenantId),
            fn() => $this->commissionReviewQueue($tenantId),
        ];

        if ($canBilling) {
            $sources[] = fn() => $this->billingIssues($tenantId);
        }

        $all = [];
        foreach ($sources as $source) {
            try {
                $all = array_merge($all, $source());
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
            ->select('id', 'name', 'reseller_name', 'days_left', 'deal_value', 'updated_at')
            ->orderBy('days_left')
            ->limit(10)
            ->get();

        return $rows->map(fn($r) => $this->make([
            'type'          => 'deal_expiring',
            'category'      => 'deal',
            'severity'      => ($r->days_left ?? 21) <= 2 ? 'urgent' : 'high',
            'summary'       => "Deal expiring soon: {$r->name}",
            'actor_name'    => $r->reseller_name ?? 'Unassigned',
            'actor_role'    => 'Referrer',
            'related_label' => $r->name,
            'related_type'  => 'deal',
            'related_id'    => $r->id,
            'occurred_at'   => $r->updated_at ?? now(),
            'action_url'    => "/tenant/{$tenantId}/deals/{$r->id}",
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
            ->select('id', 'name', 'reseller_name', 'updated_at')
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get();

        return $rows->map(fn($r) => $this->make([
            'type'          => 'deal_expired',
            'category'      => 'deal',
            'severity'      => 'high',
            'summary'       => "Deal expired: {$r->name}",
            'actor_name'    => $r->reseller_name ?? 'Unassigned',
            'actor_role'    => 'Referrer',
            'related_label' => $r->name,
            'related_type'  => 'deal',
            'related_id'    => $r->id,
            'occurred_at'   => $r->updated_at ?? now(),
            'action_url'    => "/tenant/{$tenantId}/deals/{$r->id}",
            'action_needed' => true,
            'source'        => 'leads',
        ]))->toArray();
    }

    private function missingReferrerDeals(string $tenantId): array
    {
        $count = DB::table('leads')
            ->where('tenant_id', $tenantId)
            ->whereNull('reseller_name')
            ->whereIn('status', ['active', 'expiring'])
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
            'action_url'    => "/tenant/{$tenantId}/deals",
            'action_needed' => true,
            'source'        => 'leads',
        ])];
    }

    private function recentLeadHistory(string $tenantId, int $limit, $since): array
    {
        $rows = DB::table('lead_history as h')
            ->join('leads as l', 'l.id', '=', 'h.lead_id')
            ->where('l.tenant_id', $tenantId)
            ->where('h.created_at', '>', $since)
            ->whereIn('h.type', ['stage', 'assignment', 'commission'])
            ->select('h.id', 'h.action', 'h.type', 'h.reseller', 'h.date', 'h.created_at', 'l.id as lead_id', 'l.name as lead_name')
            ->orderByDesc('h.created_at')
            ->limit($limit)
            ->get();

        return $rows->map(fn($r) => $this->make([
            'type'          => 'deal_' . $r->type,
            'category'      => 'deal',
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
            ->whereIn('status', ['completed_with_warnings', 'failed', 'completed', 'previewed', 'previewing'])
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
                'previewed'               => ['high',   "{$typeLabel} ready to confirm: {$r->file_name}",       $batchBase,           'Confirm Import', true],
                'previewing'             => ['medium', "{$typeLabel} upload in progress: {$r->file_name}",      $batchBase,           'Review Upload',  true],
                'failed'                  => ['high',   "{$typeLabel} failed: {$r->file_name}",                 "{$batchBase}/report", 'View Report',   true],
                'completed_with_warnings' => ['medium', "{$typeLabel} completed with warnings: {$r->file_name}","{$batchBase}/report", 'View Report',   true],
                default                   => ['info',   "{$typeLabel} completed: {$r->file_name}",              "{$batchBase}/report", 'View Report',   false],
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
        // Invites expiring within 48 hours
        $expiring = DB::table('tenant_invitations')
            ->where('tenant_id', $tenantId)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->where('expires_at', '<', now()->addHours(48))
            ->count();

        if ($expiring === 0) return [];

        return [$this->make([
            'type'          => 'invite_expiring',
            'category'      => 'user',
            'severity'      => 'medium',
            'summary'       => "{$expiring} pending invitation" . ($expiring > 1 ? 's' : '') . " expiring within 48 hours",
            'actor_name'    => 'System',
            'actor_role'    => 'System',
            'related_label' => 'Invitations',
            'related_type'  => 'invitation',
            'related_id'    => null,
            'occurred_at'   => now(),
            'action_url'    => "/tenant/{$tenantId}/users",
            'action_needed' => true,
            'source'        => 'tenant_invitations',
        ])];
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
        // Message threads with no reply in > 24h (using existing thread/message tables)
        try {
            $count = DB::table('message_threads as t')
                ->where('t.tenant_id', $tenantId)
                ->whereExists(fn($q) => $q->select(DB::raw(1))
                    ->from('thread_messages as m')
                    ->whereColumn('m.thread_id', 't.id')
                    ->where('m.is_read', false)
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
     * Admin view: partner_threads with unread messages from partners (reseller_unread > 0).
     * These are partner messages that a Referrer (reseller) hasn't replied to.
     * Grouped as a single count-based action so admin can monitor partner engagement.
     */
    private function unreadPartnerMessages(string $tenantId): array
    {
        try {
            $count = DB::table('partner_threads')
                ->where('tenant_id', $tenantId)
                ->where('reseller_unread', '>', 0)
                ->count();

            if ($count === 0) return [];

            return [$this->make([
                'type'          => 'partner_messages_unread',
                'category'      => 'messaging',
                'severity'      => 'medium',
                'summary'       => "{$count} unread partner message" . ($count > 1 ? 's' : '') . ' waiting for Referrer response',
                'actor_name'    => 'Partners',
                'actor_role'    => 'Partner',
                'related_label' => 'Partner Messages',
                'related_type'  => 'message',
                'related_id'    => null,
                'occurred_at'   => now(),
                'action_url'    => "/tenant/{$tenantId}/messages",
                'action_label'  => 'View Messages',
                'action_needed' => true,
                'source'        => 'partner_threads',
                'description'   => 'Partners have sent messages that their Referrer has not yet replied to. Check in to ensure deals stay on track.',
            ])];
        } catch (\Throwable $e) {
            Log::warning('[CriticalActionService] unreadPartnerMessages failed', ['tenant_id' => $tenantId, 'error' => $e->getMessage()]);
            return [];
        }
    }

    private function recentActivityLogs(string $tenantId, int $limit, $since): array
    {
        try {
            $rows = DB::table('activity_logs')
                ->where('tenant_id', $tenantId)
                ->where('created_at', '>', $since)
                ->select('id', 'user_id', 'action', 'entity', 'entity_id', 'created_at')
                ->orderByDesc('created_at')
                ->limit($limit)
                ->get();

            return $rows->map(fn($r) => $this->make([
                'type'          => 'activity_' . str_replace([' ', '-'], '_', strtolower($r->action ?? 'action')),
                'category'      => 'activity',
                'severity'      => 'info',
                'summary'       => ucfirst($r->action ?? 'Action recorded'),
                'actor_name'    => 'Team member',
                'actor_role'    => 'Admin',
                'related_label' => $r->entity ?? '',
                'related_type'  => $r->entity ?? 'record',
                'related_id'    => $r->entity_id,
                'occurred_at'   => $r->created_at ?? now(),
                'action_url'    => null,
                'action_needed' => false,
                'source'        => 'activity_logs',
            ]))->toArray();
        } catch (\Throwable $e) {
            Log::warning('[CriticalActionService] recentActivityLogs failed', ['tenant_id' => $tenantId, 'error' => $e->getMessage()]);
            return [];
        }
    }

    // ── Reseller-scoped queries ────────────────────────────────────

    private function resellerExpiringDeals(string $tenantId, string $resellerName): array
    {
        $rows = DB::table('leads')
            ->where('tenant_id', $tenantId)
            ->where('reseller_name', $resellerName)
            ->where('status', 'expiring')
            ->select('id', 'name', 'days_left', 'updated_at')
            ->orderBy('days_left')
            ->limit(5)
            ->get();

        return $rows->map(fn($r) => $this->make([
            'type'          => 'deal_expiring',
            'category'      => 'deal',
            'severity'      => ($r->days_left ?? 21) <= 2 ? 'urgent' : 'high',
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
        $rows = DB::table('lead_history as h')
            ->join('leads as l', 'l.id', '=', 'h.lead_id')
            ->where('l.tenant_id', $tenantId)
            ->where('l.reseller_name', $resellerName)
            ->where('h.created_at', '>', now()->subDays(14))
            ->whereIn('h.type', ['stage', 'commission', 'assignment'])
            ->select('h.id', 'h.action', 'h.type', 'h.created_at', 'l.id as lead_id', 'l.name as lead_name')
            ->orderByDesc('h.created_at')
            ->limit(8)
            ->get();

        return $rows->map(fn($r) => $this->make([
            'type'          => 'deal_' . $r->type,
            'category'      => 'deal',
            'severity'      => 'info',
            'summary'       => $r->action,
            'actor_name'    => 'You',
            'actor_role'    => 'Referrer',
            'related_label' => $r->lead_name,
            'related_type'  => 'deal',
            'related_id'    => $r->lead_id,
            'occurred_at'   => $r->created_at ?? now(),
            'action_url'    => null,
            'action_needed' => false,
            'source'        => 'lead_history',
        ]))->toArray();
    }

    private function resellerCommissionUpdates(string $tenantId, string $resellerName): array
    {
        $rows = DB::table('leads')
            ->where('tenant_id', $tenantId)
            ->where('reseller_name', $resellerName)
            ->where('commission_status', 'locked')
            ->where('updated_at', '>', now()->subDays(7))
            ->select('id', 'name', 'commission_status', 'deal_value', 'updated_at')
            ->orderByDesc('updated_at')
            ->limit(3)
            ->get();

        return $rows->map(fn($r) => $this->make([
            'type'          => 'commission_locked',
            'category'      => 'deal',
            'severity'      => 'medium',
            'summary'       => "Commission locked — review your deal: {$r->name}",
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
        ]))->toArray();
    }

    private function resellerImportEvents(string $tenantId, string $resellerId): array
    {
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
    }

    private function pendingExportRequests(string $tenantId): array
    {
        try {
            $rows = DB::table('export_requests')
                ->where('tenant_id', $tenantId)
                ->where('status', 'pending')
                ->select('id', 'requester_type', 'requester_id', 'requester_role', 'export_type', 'is_sensitive', 'reason', 'created_at')
                ->orderByDesc('created_at')
                ->limit(10)
                ->get();

            return $rows->map(fn($r) => $this->make([
                'type'          => 'export_approval_pending',
                'category'      => 'export',
                'severity'      => $r->is_sensitive ? 'high' : 'medium',
                'summary'       => "Export approval needed: {$r->export_type} data",
                'actor_name'    => ucfirst($r->requester_role),
                'actor_role'    => ucfirst($r->requester_role),
                'related_label' => ucwords(str_replace('_', ' ', $r->export_type)) . ' export',
                'related_type'  => 'export_request',
                'related_id'    => $r->id,
                'occurred_at'   => $r->created_at ?? now(),
                'action_url'    => "/tenant/{$tenantId}/exports/{$r->id}",
                'action_label'  => 'Review Request',
                'action_needed' => true,
                'source'        => 'export_requests',
                'meta'          => ['is_sensitive' => (bool) $r->is_sensitive],
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
                ->where('r.type', 'deal_archive')
                ->where('r.status', 'pending')
                ->select(
                    'r.id', 'r.reason', 'r.created_at', 'r.requested_by_id',
                    'r.request_payload',
                    'l.id as lead_id', 'l.name as lead_name', 'l.reseller_name', 'l.stage'
                )
                ->orderBy('r.created_at')
                ->limit(10)
                ->get();

            return $rows->map(function ($r) use ($tenantId) {
                $payload = is_string($r->request_payload) ? json_decode($r->request_payload, true) : (array) ($r->request_payload ?? []);
                $referrerName = $payload['referrer_name'] ?? $r->reseller_name ?? 'Referrer';
                $reason = \Illuminate\Support\Str::limit($r->reason ?? '', 80);

                return $this->make([
                    'type'          => 'archive_request_pending',
                    'category'      => 'deal',
                    'severity'      => 'high',
                    'summary'       => "Archive request pending: {$r->lead_name}",
                    'actor_name'    => $referrerName,
                    'actor_role'    => 'Referrer',
                    'related_label' => $r->lead_name,
                    'related_type'  => 'deal',
                    'related_id'    => $r->lead_id,
                    'occurred_at'   => $r->created_at ?? now(),
                    'action_url'    => "/tenant/{$tenantId}/deals/{$r->lead_id}",
                    'action_label'  => 'Review Archive Request',
                    'action_needed' => true,
                    'source'        => 'deal_approval_requests',
                    'meta'          => ['reason' => $reason, 'stage' => $r->stage, 'approval_id' => $r->id],
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
                    'severity'      => $missingCount > 0 ? 'high' : 'normal',
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
                'action_url'    => "/tenant/{$tenantId}/deals/{$r->lead_id}",
                'action_label'  => 'Review Request',
                'action_needed' => true,
                'source'        => 'deal_assignment_extension_requests',
                'meta'          => ['status' => $r->status, 'requested_days' => $r->requested_days],
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
                    'rb.id', 'rb.status', 'rb.records_conflict', 'rb.records_failed',
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
                'action_url'    => "/tenant/{$tenantId}/imports",
                'action_label'  => 'View Imports',
                'action_needed' => true,
                'source'        => 'import_rollbacks',
                'meta'          => ['records_conflict' => $r->records_conflict, 'records_failed' => $r->records_failed],
            ]))->toArray();
        } catch (\Throwable $e) {
            Log::warning('[CriticalActionService] failedRollbacks failed', ['tenant_id' => $tenantId, 'error' => $e->getMessage()]);
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

    // ── New reseller source: extension request responses ───────────

    private function resellerExtensionRequests(string $tenantId, string $resellerName): array
    {
        try {
            $rows = DB::table('deal_assignment_extension_requests as r')
                ->join('leads as l', 'l.id', '=', 'r.deal_id')
                ->where('r.tenant_id', $tenantId)
                ->where('l.reseller_name', $resellerName)
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

    // ── New reseller source: unread partner messages ───────────────

    private function resellerUnreadMessages(string $tenantId, string $resellerName): array
    {
        try {
            $reseller = DB::table('resellers')
                ->where('tenant_id', $tenantId)
                ->where('name', $resellerName)
                ->select('id')
                ->first();

            if (!$reseller) return [];

            // Count partner_threads where this reseller has unread partner messages
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
        if (!Schema::hasTable('tasks')) return [];

        $tasks = DB::table('tasks')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->whereNotIn('status', ['completed', 'cancelled', 'archived'])
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
        if (!Schema::hasTable('tasks')) return [];

        $tasks = DB::table('tasks')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->where('status', 'open')
            ->where('category', 'request_form')
            ->where('created_at', '>', now()->subDays(3))
            ->select('id', 'title', 'requestor_name', 'created_at')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        return $tasks->map(fn($t) => $this->make([
            'type'          => 'new_request_form_task',
            'category'      => 'task',
            'severity'      => 'medium',
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
        if ($tenantId !== 'lgu-ids') return [];

        try {
            $rows = DB::table('leads')
                ->where('tenant_id', $tenantId)
                ->whereNotIn('status', ['expired', 'declined'])
                ->whereRaw("data::jsonb->>'amount_defaulted' = 'true'")
                ->whereRaw("data::jsonb->>'amount_confirmation_status' = 'pending'")
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
    private function recentReferrerAmountChanges(string $tenantId, \Carbon\Carbon $since): array
    {
        try {
            $cutoff = now()->subDays(2); // Only show within 2-day review window

            $rows = DB::table('lead_history as lh')
                ->join('leads as l', 'l.id', '=', 'lh.lead_id')
                ->where('lh.tenant_id', $tenantId)
                ->where('lh.action', 'like', '%amount updated by referrer%')
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
                    'severity'      => 'normal',
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
                    'category'      => 'deal',
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

    // ── DTO factory ────────────────────────────────────────────────

    private function make(array $data): array
    {
        $occurredAt = $data['occurred_at'] instanceof \Carbon\Carbon
            ? $data['occurred_at']
            : \Carbon\Carbon::parse($data['occurred_at']);

        return array_merge($data, [
            'occurred_at'  => $occurredAt,
            'occurred_ago' => $occurredAt->diffForHumans(),
            'occurred_fmt' => $occurredAt->format('M j, Y g:i A'),
        ]);
    }
}
