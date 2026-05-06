<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

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
    public function forReseller(string $tenantId, string $resellerName, int $limit = 8): array
    {
        $items = array_merge(
            $this->resellerExpiringDeals($tenantId, $resellerName),
            $this->resellerLeadHistory($tenantId, $resellerName),
            $this->resellerCommissionUpdates($tenantId, $resellerName),
        );

        usort($items, fn($a, $b) =>
            (self::SEVERITY_ORDER[$a['severity']] ?? 9) <=> (self::SEVERITY_ORDER[$b['severity']] ?? 9)
            ?: $b['occurred_at']->timestamp <=> $a['occurred_at']->timestamp
        );

        return array_slice($items, 0, $limit);
    }

    // ── Tenant-level aggregation ────────────────────────────────────

    private function forTenant(string $tenantId, array $opts = []): array
    {
        $limitPer = $opts['limit_per_source'] ?? 10;
        $since    = $opts['since'] ?? now()->subDays(30);

        $sources = [
            fn() => $this->expiringDeals($tenantId),
            fn() => $this->expiredDeals($tenantId),
            fn() => $this->missingReferrerDeals($tenantId),
            fn() => $this->recentLeadHistory($tenantId, $limitPer, $since),
            fn() => $this->importEvents($tenantId, $limitPer),
            fn() => $this->pendingInvites($tenantId),
            fn() => $this->recentAcceptedInvites($tenantId, $limitPer, $since),
            fn() => $this->unrepliedMessages($tenantId),
            fn() => $this->recentActivityLogs($tenantId, $limitPer, $since),
        ];

        $all = [];
        foreach ($sources as $source) {
            try {
                $all = array_merge($all, $source());
            } catch (\Throwable) {
                // one failed source never breaks the whole widget
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
            ->whereIn('status', ['completed_with_warnings', 'failed', 'completed'])
            ->where('created_at', '>', now()->subDays(14))
            ->select('id', 'status', 'file_name', 'import_type', 'failed_rows', 'failed_rows as fr',
                     'successful_rows', 'total_rows', 'imported_by_id', 'imported_by_role', 'created_at', 'completed_at')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        return $rows->map(fn($r) => $this->make([
            'type'          => 'import_' . $r->status,
            'category'      => 'import',
            'severity'      => $r->status === 'failed' ? 'high' : ($r->failed_rows > 0 ? 'medium' : 'info'),
            'summary'       => match($r->status) {
                'failed'                  => "Import failed: {$r->file_name}",
                'completed_with_warnings' => "Import completed with warnings: {$r->file_name}",
                default                   => "Import completed: {$r->file_name}",
            },
            'actor_name'    => ucfirst($r->imported_by_role ?? 'Admin'),
            'actor_role'    => ucfirst($r->imported_by_role ?? 'Admin'),
            'related_label' => $r->file_name,
            'related_type'  => 'import',
            'related_id'    => $r->id,
            'occurred_at'   => $r->completed_at ?? $r->created_at ?? now(),
            'action_url'    => "/tenant/{$tenantId}/imports",
            'action_needed' => in_array($r->status, ['failed', 'completed_with_warnings']),
            'source'        => 'import_batches',
        ]))->toArray();
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
        } catch (\Throwable) {
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
        } catch (\Throwable) {
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
            'action_url'    => null,
            'action_needed' => true,
            'source'        => 'leads',
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
            'severity'      => 'info',
            'summary'       => "Commission locked: {$r->name}",
            'actor_name'    => 'System',
            'actor_role'    => 'System',
            'related_label' => $r->name,
            'related_type'  => 'deal',
            'related_id'    => $r->id,
            'occurred_at'   => $r->updated_at ?? now(),
            'action_url'    => null,
            'action_needed' => false,
            'source'        => 'leads',
        ]))->toArray();
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
