<?php

namespace App\Services;

use App\Models\Favorite;
use App\Models\RecentSearch;
use App\Models\SavedSearch;
use App\Models\Synonym;
use Illuminate\Support\Facades\DB;

class SearchService
{
    // Entity type → display label + icon
    private const ENTITY_META = [
        'tenant'           => ['label' => 'Tenant',           'icon' => 'building'],
        'invoice'          => ['label' => 'Invoice',          'icon' => 'document'],
        'payment'          => ['label' => 'Payment',          'icon' => 'credit-card'],
        'promo_code'       => ['label' => 'Promo Code',       'icon' => 'tag'],
        'promotion'        => ['label' => 'Promotion',        'icon' => 'sparkles'],
        'approval_request' => ['label' => 'Approval',         'icon' => 'check-circle'],
        'subscription'     => ['label' => 'Subscription',     'icon' => 'refresh'],
        'lead'             => ['label' => 'Lead',             'icon' => 'user'],
        'reseller'         => ['label' => 'Reseller',         'icon' => 'users'],
        'notification'     => ['label' => 'Notification',     'icon' => 'bell'],
    ];

    // Scoped search keywords → entity type + status filter
    private const SCOPE_MAP = [
        'tenant'     => ['type' => 'tenant',           'status' => null],
        'invoice'    => ['type' => 'invoice',          'status' => null],
        'unpaid'     => ['type' => 'invoice',          'status' => 'open'],
        'overdue'    => ['type' => 'invoice',          'status' => 'past_due'],
        'payment'    => ['type' => 'payment',          'status' => null],
        'failed'     => ['type' => 'payment',          'status' => 'failed'],
        'promo'      => ['type' => 'promo_code',       'status' => null],
        'promotion'  => ['type' => 'promotion',        'status' => null],
        'approval'   => ['type' => 'approval_request', 'status' => null],
        'pending'    => ['type' => 'approval_request', 'status' => 'pending'],
        'lead'       => ['type' => 'lead',             'status' => null],
        'reseller'   => ['type' => 'reseller',         'status' => null],
    ];

    // ── Main Search ───────────────────────────────────────────

    public function search(string $query, array $filters = [], int $limit = 20, int $offset = 0): array
    {
        $query   = trim($query);
        $command = $this->parseCommand($query);
        $scoped  = $this->parseScope($query);
        $terms   = $this->expandTerms($scoped['query']);

        $dbQuery = DB::table('search_index')->where('is_deleted', false);

        // Apply scoped entity type filter
        if ($scoped['entity_type']) {
            $dbQuery->where('entity_type', $scoped['entity_type']);
        } elseif (!empty($filters['type'])) {
            $dbQuery->where('entity_type', $filters['type']);
        }

        if ($scoped['status']) {
            $dbQuery->where('status', $scoped['status']);
        } elseif (!empty($filters['status'])) {
            $dbQuery->where('status', $filters['status']);
        }

        // Date range filter
        if (!empty($filters['from'])) {
            $dbQuery->where('last_activity_at', '>=', $filters['from']);
        }
        if (!empty($filters['to'])) {
            $dbQuery->where('last_activity_at', '<=', $filters['to']);
        }

        // Tenant filter
        if (!empty($filters['tenant_id'])) {
            $dbQuery->where('tenant_id', $filters['tenant_id']);
        }

        // Text search: each term must match somewhere in searchable_text
        if (!empty($terms)) {
            $dbQuery->where(function ($q) use ($terms) {
                foreach ($terms as $term) {
                    $q->orWhereRaw('searchable_text ILIKE ?', ['%' . $term . '%']);
                }
            });
        }

        $total   = $dbQuery->count();
        $results = $dbQuery
            ->orderByRaw("CASE entity_type
                WHEN 'tenant'           THEN 1
                WHEN 'approval_request' THEN 2
                WHEN 'invoice'          THEN 3
                WHEN 'payment'          THEN 4
                WHEN 'promo_code'       THEN 5
                WHEN 'promotion'        THEN 6
                WHEN 'subscription'     THEN 7
                WHEN 'lead'             THEN 8
                WHEN 'reseller'         THEN 9
                ELSE 10 END")
            ->orderByDesc('last_activity_at')
            ->limit($limit)
            ->offset($offset)
            ->get()
            ->map(fn($row) => $this->formatResult($row, $terms))
            ->toArray();

        return [
            'results'    => $results,
            'total'      => $total,
            'query'      => $query,
            'command'    => $command,
            'scoped'     => $scoped,
            'has_index'  => DB::table('search_index')->count() > 0,
        ];
    }

    public function suggest(string $query): array
    {
        if (strlen($query) < 2) return [];

        return DB::table('search_index')
            ->where('is_deleted', false)
            ->where('searchable_text', 'ILIKE', '%' . $query . '%')
            ->select('entity_type', 'title', 'url', 'status')
            ->orderByRaw("CASE entity_type WHEN 'tenant' THEN 1 WHEN 'invoice' THEN 2 ELSE 5 END")
            ->limit(8)
            ->get()
            ->map(fn($row) => [
                'title'       => $row->title,
                'entity_type' => $row->entity_type,
                'label'       => self::ENTITY_META[$row->entity_type]['label'] ?? $row->entity_type,
                'status'      => $row->status,
                'url'         => $row->url,
            ])
            ->toArray();
    }

    // ── Command Parser ────────────────────────────────────────

    public function parseCommand(string $query): ?array
    {
        $q = strtolower(trim($query));

        $patterns = [
            '/^suspend\s+tenant\s+(.+)$/i'             => ['action' => 'suspend_tenant',   'sensitive' => true,  'confirm' => 'Suspend tenant "{target}"?'],
            '/^activate\s+tenant\s+(.+)$/i'            => ['action' => 'activate_tenant',  'sensitive' => true,  'confirm' => 'Activate tenant "{target}"?'],
            '/^show\s+unpaid\s+invoices?$/i'            => ['action' => 'filter',           'sensitive' => false, 'filter' => ['type' => 'invoice', 'status' => 'open']],
            '/^show\s+(failed|pending)\s+payments?$/i' => ['action' => 'filter',           'sensitive' => false, 'filter' => ['type' => 'payment', 'status' => '$1']],
            '/^show\s+pending\s+approvals?$/i'         => ['action' => 'filter',           'sensitive' => false, 'filter' => ['type' => 'approval_request', 'status' => 'pending']],
            '/^show\s+at[- ]risk\s+tenants?$/i'        => ['action' => 'filter',           'sensitive' => false, 'filter' => ['type' => 'tenant', 'status' => 'at_risk']],
            '/^approve\s+(.+)$/i'                      => ['action' => 'approve',          'sensitive' => true,  'confirm' => 'Approve "{target}"?'],
            '/^reject\s+(.+)$/i'                       => ['action' => 'reject',           'sensitive' => true,  'confirm' => 'Reject "{target}"?'],
            '/^create\s+promo(\s+code)?$/i'            => ['action' => 'navigate',         'sensitive' => false, 'url' => '/platform/billing?tab=2'],
            '/^create\s+tenant$/i'                     => ['action' => 'navigate',         'sensitive' => false, 'url' => '/platform/tenants/create'],
            '/^create\s+promotion$/i'                  => ['action' => 'navigate',         'sensitive' => false, 'url' => '/platform/billing?tab=3'],
        ];

        foreach ($patterns as $pattern => $meta) {
            if (preg_match($pattern, $query, $matches)) {
                return array_merge($meta, [
                    'target'  => $matches[1] ?? null,
                    'matched' => true,
                ]);
            }
        }

        return null;
    }

    // ── Recent & Saved Searches ───────────────────────────────

    public function logSearch(int $userId, string $query, int $resultCount): void
    {
        try {
            RecentSearch::create([
                'user_id'      => $userId,
                'query'        => $query,
                'result_count' => $resultCount,
            ]);

            $toDelete = RecentSearch::where('user_id', $userId)
                ->orderByDesc('created_at')
                ->skip(20)
                ->take(100)
                ->pluck('id');

            if ($toDelete->isNotEmpty()) {
                RecentSearch::whereIn('id', $toDelete)->delete();
            }
        } catch (\Throwable) {
            // recent_searches table may not exist on this deployment
        }
    }

    public function getRecent(int $userId): array
    {
        try {
            return RecentSearch::where('user_id', $userId)
                ->orderByDesc('created_at')
                ->limit(8)
                ->get(['query', 'result_count', 'created_at'])
                ->toArray();
        } catch (\Throwable) {
            return [];
        }
    }

    public function saveSearch(int $userId, string $name, string $query, array $filters, bool $pinned = false): SavedSearch
    {
        // May throw if saved_searches table does not exist on this deployment;
        // the controller wraps this in a try/catch and returns a 500 with context.
        return SavedSearch::create([
            'user_id'      => $userId,
            'name'         => $name,
            'query'        => $query,
            'filters_json' => $filters,
            'is_pinned'    => $pinned,
        ]);
    }

    public function getSavedSearches(int $userId): array
    {
        try {
            return SavedSearch::where('user_id', $userId)
                ->orderByDesc('is_pinned')
                ->orderByDesc('created_at')
                ->get()
                ->toArray();
        } catch (\Throwable) {
            // saved_searches table may not exist on this deployment
            return [];
        }
    }

    // ── Favorites ─────────────────────────────────────────────

    public function addFavorite(int $userId, string $entityType, string $entityId, ?string $label, ?string $url): Favorite
    {
        return Favorite::firstOrCreate(
            ['user_id' => $userId, 'entity_type' => $entityType, 'entity_id' => $entityId],
            ['label' => $label, 'url' => $url]
        );
    }

    public function removeFavorite(int $userId, string $entityType, string $entityId): void
    {
        Favorite::where('user_id', $userId)->where('entity_type', $entityType)->where('entity_id', $entityId)->delete();
    }

    public function getFavorites(int $userId): array
    {
        return Favorite::where('user_id', $userId)->orderByDesc('created_at')->get()->toArray();
    }

    // ── Quick Actions ─────────────────────────────────────────

    public function getQuickActions(string $entityType, string $status): array
    {
        $all = [
            'tenant' => [
                ['label' => 'View',    'action' => 'view',            'sensitive' => false, 'icon' => 'eye'],
                ['label' => 'Message', 'action' => 'message_tenant',  'sensitive' => false, 'icon' => 'chat'],
                ['label' => $status === 'active' ? 'Suspend' : 'Activate',
                           'action' => $status === 'active' ? 'suspend_tenant' : 'activate_tenant',
                           'sensitive' => true, 'icon' => $status === 'active' ? 'ban' : 'check'],
            ],
            'invoice' => [
                ['label' => 'View',      'action' => 'view',           'sensitive' => false, 'icon' => 'eye'],
                ['label' => 'Mark Paid', 'action' => 'mark_paid',      'sensitive' => true,  'icon' => 'check'],
                ['label' => 'Waive',     'action' => 'waive_invoice',  'sensitive' => true,  'icon' => 'x'],
            ],
            'promo_code' => [
                ['label' => 'View',    'action' => 'view',         'sensitive' => false, 'icon' => 'eye'],
                ['label' => 'Disable', 'action' => 'disable_promo','sensitive' => true,  'icon' => 'ban'],
            ],
            'approval_request' => [
                ['label' => 'Approve', 'action' => 'approve', 'sensitive' => true, 'icon' => 'check'],
                ['label' => 'Reject',  'action' => 'reject',  'sensitive' => true, 'icon' => 'x'],
            ],
        ];

        return $all[$entityType] ?? [['label' => 'View', 'action' => 'view', 'sensitive' => false, 'icon' => 'eye']];
    }

    // ── Private helpers ───────────────────────────────────────

    private function parseScope(string $query): array
    {
        // Detect "scope:value" pattern e.g. "tenant: ABC", "invoice: unpaid"
        if (preg_match('/^(\w+)\s*:\s*(.+)$/i', $query, $m)) {
            $scope = strtolower($m[1]);
            $rest  = trim($m[2]);
            $map   = self::SCOPE_MAP[$scope] ?? null;
            return [
                'entity_type' => $map['type'] ?? null,
                'status'      => $map['status'] ?? null,
                'query'       => $rest,
            ];
        }

        // Detect shorthand scopes e.g. "unpaid invoices", "failed payments"
        foreach (self::SCOPE_MAP as $keyword => $map) {
            if (stripos($query, $keyword) !== false) {
                $rest = trim(str_ireplace($keyword, '', $query));
                return [
                    'entity_type' => $map['type'],
                    'status'      => $map['status'],
                    'query'       => $rest ?: $query,
                ];
            }
        }

        return ['entity_type' => null, 'status' => null, 'query' => $query];
    }

    private function expandTerms(string $query): array
    {
        $terms = array_filter(explode(' ', strtolower(trim($query))));
        if (empty($terms)) return [];

        // Add synonym expansions
        $synonyms = Synonym::whereIn('term', $terms)->orWhereIn('synonym', $terms)->get();
        foreach ($synonyms as $syn) {
            if (in_array($syn->term, $terms)) $terms[] = $syn->synonym;
            if (in_array($syn->synonym, $terms)) $terms[] = $syn->term;
        }

        return array_unique(array_values($terms));
    }

    private function formatResult(object $row, array $terms): array
    {
        $meta = self::ENTITY_META[$row->entity_type] ?? ['label' => $row->entity_type, 'icon' => 'document'];

        // Highlight matched terms in title
        $highlightedTitle = $row->title;
        foreach ($terms as $term) {
            $highlightedTitle = preg_replace('/(' . preg_quote($term, '/') . ')/i', '<mark>$1</mark>', $highlightedTitle);
        }

        return [
            'id'              => $row->entity_id,
            'entity_type'     => $row->entity_type,
            'entity_label'    => $meta['label'],
            'entity_icon'     => $meta['icon'],
            'title'           => $row->title,
            'title_highlight' => $highlightedTitle,
            'description'     => $row->description,
            'status'          => $row->status,
            'url'             => $row->url,
            'tenant_id'       => $row->tenant_id,
            'relationships'   => json_decode($row->relationships_json ?? '{}', true),
            'last_activity'   => $row->last_activity_at,
            'quick_actions'   => $this->getQuickActions($row->entity_type, $row->status ?? ''),
            'tags'            => $row->tags ?? [],
        ];
    }
}
