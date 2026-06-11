<?php

namespace App\Services;

use App\Models\DealAssignmentExtensionRequest;
use App\Models\LeadHistory;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DealExtensionDisplayService
{
    public function getSummary(string $dealId, string $tenantId, string $viewerRole = 'referrer'): array
    {
        $summaries = $this->getSummariesForDeals([$dealId], $tenantId, $viewerRole);
        return $summaries[$dealId] ?? $this->emptyResult();
    }

    /**
     * Batched variant of getSummary() for list views — runs a fixed number of
     * queries (2-4) regardless of how many deal IDs are passed in.
     *
     * @param string[] $dealIds
     * @return array<string, array> summary array keyed by deal_id
     */
    public function getSummariesForDeals(array $dealIds, string $tenantId, string $viewerRole = 'referrer'): array
    {
        $dealIds = array_values(array_unique(array_filter($dealIds)));
        if (empty($dealIds)) {
            return [];
        }

        try {
            $approvedByDeal = DealAssignmentExtensionRequest::where('tenant_id', $tenantId)
                ->whereIn('deal_id', $dealIds)
                ->where('status', 'approved')
                ->orderBy('reviewed_at')
                ->get(['deal_id', 'approved_days', 'approved_new_expiry_at', 'reviewed_at', 'reviewed_by_user_id', 'admin_note'])
                ->groupBy('deal_id');

            $pendingCounts = DealAssignmentExtensionRequest::where('tenant_id', $tenantId)
                ->whereIn('deal_id', $dealIds)
                ->whereIn('status', ['pending_review', 'clarification_requested'])
                ->selectRaw('deal_id, COUNT(*) as cnt')
                ->groupBy('deal_id')
                ->pluck('cnt', 'deal_id');

            $reviewerNames = [];
            if (in_array($viewerRole, ['admin', 'manager'])) {
                $reviewerIds = $approvedByDeal
                    ->map(fn($rows) => $rows->last()?->reviewed_by_user_id)
                    ->filter()
                    ->unique()
                    ->values();

                if ($reviewerIds->isNotEmpty()) {
                    $reviewerNames = DB::table('tenant_users')
                        ->whereIn('id', $reviewerIds)
                        ->pluck('full_name', 'id')
                        ->all();

                    $missing = $reviewerIds->diff(array_keys($reviewerNames));
                    if ($missing->isNotEmpty()) {
                        $reviewerNames += DB::table('users')
                            ->whereIn('id', $missing)
                            ->pluck('name', 'id')
                            ->all();
                    }
                }
            }

            // Deals reactivated via direct admin bulk-extend (no formal
            // DealAssignmentExtensionRequest row) record their extension as a
            // 'deal_extended' lead_history entry with an extension_days metadata
            // field — pick those up too so the badge reflects all extension paths.
            $directExtensionsByDeal = LeadHistory::where('tenant_id', $tenantId)
                ->whereIn('lead_id', $dealIds)
                ->where('type', 'deal_extended')
                ->whereNotNull('metadata')
                ->orderBy('created_at')
                ->get(['lead_id', 'metadata', 'actor_name', 'created_at'])
                ->filter(fn($row) => (int) (($row->metadata ?? [])['extension_days'] ?? 0) > 0)
                ->groupBy('lead_id');

            $summaries = [];
            foreach ($dealIds as $dealId) {
                $summaries[$dealId] = $this->buildSummary(
                    $approvedByDeal->get($dealId, collect()),
                    (int) ($pendingCounts[$dealId] ?? 0),
                    $viewerRole,
                    $reviewerNames,
                    $directExtensionsByDeal->get($dealId, collect())
                );
            }

            return $summaries;
        } catch (\Throwable $e) {
            Log::warning('[DealExtensionDisplayService] getSummariesForDeals failed', [
                'tenant_id' => $tenantId,
                'deal_ids'  => $dealIds,
                'error'     => $e->getMessage(),
            ]);
            return array_fill_keys($dealIds, $this->emptyResult());
        }
    }

    /**
     * Maps tenant membership / guard roles onto the three roles the extension
     * badge component understands (admin, manager, referrer). Owners and the
     * super-admin (web guard) are treated as admins; any other tenant role
     * (member, viewer, etc.) falls back to a neutral "viewer" role that shows
     * the badge without a review CTA or reviewer note.
     */
    public static function normalizeViewerRole(?string $role): string
    {
        return match ($role) {
            'owner', 'super_admin' => 'admin',
            'admin', 'manager', 'referrer' => $role,
            default => 'viewer',
        };
    }

    private function buildSummary($approved, int $pendingCount, string $viewerRole, array $reviewerNames, $directExtensions = null): array
    {
        $directExtensions = $directExtensions ?? collect();

        $directDays   = (int) $directExtensions->sum(fn($row) => (int) (($row->metadata ?? [])['extension_days'] ?? 0));
        $totalDays    = (int) $approved->sum('approved_days') + $directDays;
        $eventCount   = $approved->count() + $directExtensions->count();
        $latest       = $approved->last();
        $latestDirect = $directExtensions->last();

        $hasExtension = $eventCount > 0;
        $hasPending   = $pendingCount > 0;

        if (!$hasExtension && !$hasPending) {
            return $this->emptyResult();
        }

        $status = match(true) {
            $hasExtension && $hasPending => 'both',
            $hasExtension                => 'approved',
            $hasPending                  => 'pending',
            default                      => 'none',
        };

        // When both a formal approval and a direct admin extension exist, the
        // most recent of the two drives the "latest" display fields below.
        $latestAt       = $latest?->reviewed_at ? Carbon::parse($latest->reviewed_at) : null;
        $latestDirectAt = $latestDirect?->created_at ? Carbon::parse($latestDirect->created_at) : null;
        $latestIsDirect = $latestDirectAt && (!$latestAt || $latestDirectAt->gt($latestAt));

        $latestDays = $latestIsDirect
            ? (int) (($latestDirect->metadata ?? [])['extension_days'] ?? 0)
            : (int) ($latest?->approved_days ?? 0);

        $reviewerName = null;
        if (in_array($viewerRole, ['admin', 'manager'])) {
            if ($latestIsDirect) {
                $reviewerName = $latestDirect->actor_name ?: null;
            } elseif ($latest?->reviewed_by_user_id) {
                $reviewerName = $reviewerNames[$latest->reviewed_by_user_id] ?? null;
            }
        }

        $newDeadline = null;
        if (!$latestIsDirect && $latest?->approved_new_expiry_at) {
            try {
                $newDeadline = Carbon::parse($latest->approved_new_expiry_at)
                    ->timezone('Asia/Manila')
                    ->format('M j, Y');
            } catch (\Throwable) {}
        }

        $latestApprovedAt = null;
        $latestAtForDisplay = $latestIsDirect ? $latestDirectAt : $latestAt;
        if ($latestAtForDisplay) {
            try {
                $latestApprovedAt = $latestAtForDisplay->copy()->timezone('Asia/Manila')->format('M j, Y');
            } catch (\Throwable) {}
        }

        $tooltipItems = [];
        if ($hasExtension) {
            if ($eventCount === 1) {
                $tooltipItems[] = ['label' => 'Extended', 'value' => '+' . $totalDays . ' day' . ($totalDays !== 1 ? 's' : '')];
            } else {
                $tooltipItems[] = ['label' => 'Total extension', 'value' => '+' . $totalDays . ' days (' . $eventCount . ' extensions)'];
                $tooltipItems[] = ['label' => 'Latest extension', 'value' => '+' . $latestDays . ' day' . ($latestDays !== 1 ? 's' : '')];
            }
            if ($newDeadline) {
                $tooltipItems[] = ['label' => 'New deadline', 'value' => $newDeadline];
            }
            if ($latestApprovedAt) {
                $tooltipItems[] = ['label' => $latestIsDirect ? 'Extended on' : 'Approved on', 'value' => $latestApprovedAt];
            }
            if ($reviewerName) {
                $tooltipItems[] = ['label' => $latestIsDirect ? 'Extended by' : 'Approved by', 'value' => $reviewerName];
            }
            if (!$latestIsDirect && $latest?->admin_note && in_array($viewerRole, ['referrer', 'admin', 'manager'])) {
                $tooltipItems[] = ['label' => 'Reviewer note', 'value' => Str::limit((string) $latest->admin_note, 120)];
            }
        }
        if ($hasPending) {
            $tooltipItems[] = ['label' => 'Pending requests', 'value' => $pendingCount . ' awaiting review'];
        }

        $label      = $hasExtension
            ? 'Extended +' . $totalDays . ' day' . ($totalDays !== 1 ? 's' : '')
            : 'Extension pending';
        $shortLabel = $hasExtension ? '+' . $totalDays . 'd' : 'Pending';

        return [
            'has_extension'      => $hasExtension,
            'total_days'         => $totalDays,
            'latest_days'        => $latestDays,
            'approved_count'     => $eventCount,
            'pending_count'      => $pendingCount,
            'label'              => $label,
            'short_label'        => $shortLabel,
            'new_deadline'       => $newDeadline,
            'latest_approved_at' => $latestApprovedAt,
            'reviewer_name'      => $reviewerName,
            'tooltip_items'      => $tooltipItems,
            'status'             => $status,
        ];
    }

    private function emptyResult(): array
    {
        return [
            'has_extension'      => false,
            'total_days'         => 0,
            'latest_days'        => 0,
            'approved_count'     => 0,
            'pending_count'      => 0,
            'label'              => '',
            'short_label'        => '',
            'new_deadline'       => null,
            'latest_approved_at' => null,
            'reviewer_name'      => null,
            'tooltip_items'      => [],
            'status'             => 'none',
        ];
    }
}
