<?php

namespace App\Services;

use App\Models\DealAssignmentExtensionRequest;
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

            $summaries = [];
            foreach ($dealIds as $dealId) {
                $summaries[$dealId] = $this->buildSummary(
                    $approvedByDeal->get($dealId, collect()),
                    (int) ($pendingCounts[$dealId] ?? 0),
                    $viewerRole,
                    $reviewerNames
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

    private function buildSummary($approved, int $pendingCount, string $viewerRole, array $reviewerNames): array
    {
        $totalDays     = (int) $approved->sum('approved_days');
        $approvedCount = $approved->count();
        $latest        = $approved->last();

        $hasExtension = $approvedCount > 0;
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

        $reviewerName = null;
        if (in_array($viewerRole, ['admin', 'manager']) && $latest?->reviewed_by_user_id) {
            $reviewerName = $reviewerNames[$latest->reviewed_by_user_id] ?? null;
        }

        $newDeadline = null;
        if ($latest?->approved_new_expiry_at) {
            try {
                $newDeadline = Carbon::parse($latest->approved_new_expiry_at)
                    ->timezone('Asia/Manila')
                    ->format('M j, Y');
            } catch (\Throwable) {}
        }

        $latestApprovedAt = null;
        if ($latest?->reviewed_at) {
            try {
                $latestApprovedAt = Carbon::parse($latest->reviewed_at)
                    ->timezone('Asia/Manila')
                    ->format('M j, Y');
            } catch (\Throwable) {}
        }

        $tooltipItems = [];
        if ($hasExtension) {
            if ($approvedCount === 1) {
                $tooltipItems[] = ['label' => 'Extended', 'value' => '+' . $totalDays . ' day' . ($totalDays !== 1 ? 's' : '')];
            } else {
                $tooltipItems[] = ['label' => 'Total extension', 'value' => '+' . $totalDays . ' days (' . $approvedCount . ' approvals)'];
                $tooltipItems[] = ['label' => 'Latest approval', 'value' => '+' . ((int) ($latest->approved_days ?? 0)) . ' days'];
            }
            if ($newDeadline) {
                $tooltipItems[] = ['label' => 'New deadline', 'value' => $newDeadline];
            }
            if ($latestApprovedAt) {
                $tooltipItems[] = ['label' => 'Approved on', 'value' => $latestApprovedAt];
            }
            if ($reviewerName) {
                $tooltipItems[] = ['label' => 'Approved by', 'value' => $reviewerName];
            }
            if ($latest?->admin_note && in_array($viewerRole, ['referrer', 'admin', 'manager'])) {
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
            'latest_days'        => (int) ($latest?->approved_days ?? 0),
            'approved_count'     => $approvedCount,
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
