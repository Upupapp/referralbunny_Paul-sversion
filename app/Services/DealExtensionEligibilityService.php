<?php

namespace App\Services;

use App\Models\DealAssignmentExtensionRequest;
use App\Models\Lead;
use App\Models\Reseller;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Centralised eligibility check for deal extension requests.
 *
 * All checks are server-side. UI may use this result to show reasons,
 * but the service layer must re-check before writing anything.
 */
class DealExtensionEligibilityService
{
    /**
     * Check a single deal for a given reseller.
     *
     * Returns ['eligible' => bool, 'reason' => string|null]
     */
    public function checkForReseller(Lead $deal, Reseller $reseller): array
    {
        // Must belong to same tenant
        if ($deal->tenant_id !== $reseller->tenant_id) {
            return $this->ineligible('This deal belongs to another tenant.');
        }

        // Deal must be assigned to this reseller
        $assignedName = strtolower(trim($deal->reseller_name ?? ''));
        $resellerName = strtolower(trim($reseller->name ?? ''));
        if ($assignedName !== $resellerName) {
            return $this->ineligible('You are not assigned to this deal.');
        }

        // Deal must not be archived
        if ($deal->status === 'archived' || !empty($deal->deleted_at)) {
            return $this->ineligible('This deal is archived.');
        }

        // Deal must not be completed/paid (commission finalised)
        if (in_array($deal->commission_status, ['locked', 'paid'])) {
            return $this->ineligible('This deal is already paid or commission is locked.');
        }

        // Deal must have a stage with days_left (active deadline)
        if ($deal->days_left === null) {
            return $this->ineligible('This deal has no active deadline.');
        }

        // Deal must be in an extendable status
        if (!in_array($deal->status, ['active', 'expiring', 'expired'])) {
            return $this->ineligible('This deal is not eligible for extension in its current stage.');
        }

        // No duplicate pending extension request from this reseller for this deal
        $duplicate = DealAssignmentExtensionRequest::where('tenant_id', $deal->tenant_id)
            ->where('deal_id', $deal->id)
            ->where('requested_by_user_id', $reseller->id)
            ->where('status', 'pending_review')
            ->exists();

        if ($duplicate) {
            return $this->ineligible('You already have a pending extension request for this deal.');
        }

        return ['eligible' => true, 'reason' => null];
    }

    /**
     * Check a collection of deals and return eligibility for each.
     *
     * Returns Collection of ['deal_id' => ..., 'deal' => Lead, 'eligible' => bool, 'reason' => string|null]
     */
    public function checkManyForReseller(Collection $deals, Reseller $reseller): Collection
    {
        // Pre-load all pending requests for this reseller to avoid N+1
        $pendingDealIds = DealAssignmentExtensionRequest::where('tenant_id', $reseller->tenant_id)
            ->where('requested_by_user_id', $reseller->id)
            ->where('status', 'pending_review')
            ->pluck('deal_id')
            ->flip()
            ->all();

        return $deals->map(function (Lead $deal) use ($reseller, $pendingDealIds) {
            if ($deal->tenant_id !== $reseller->tenant_id) {
                return $this->row($deal, false, 'This deal belongs to another tenant.');
            }

            $assignedName = strtolower(trim($deal->reseller_name ?? ''));
            $resellerName = strtolower(trim($reseller->name ?? ''));
            if ($assignedName !== $resellerName) {
                return $this->row($deal, false, 'You are not assigned to this deal.');
            }

            if ($deal->status === 'archived' || !empty($deal->deleted_at)) {
                return $this->row($deal, false, 'This deal is archived.');
            }

            if (in_array($deal->commission_status, ['locked', 'paid'])) {
                return $this->row($deal, false, 'This deal is already paid or commission is locked.');
            }

            if ($deal->days_left === null) {
                return $this->row($deal, false, 'This deal has no active deadline.');
            }

            if (!in_array($deal->status, ['active', 'expiring', 'expired'])) {
                return $this->row($deal, false, 'This deal is not eligible for extension in its current stage.');
            }

            if (isset($pendingDealIds[$deal->id])) {
                return $this->row($deal, false, 'You already have a pending extension request for this deal.');
            }

            return $this->row($deal, true, null);
        });
    }

    /**
     * Get eligible deals assigned to a reseller (for the wizard step 1 list).
     *
     * Returns all deals with eligibility attached, ordered by urgency.
     */
    public function getEligibleDealsForReseller(Reseller $reseller, string $tenantId): Collection
    {
        if ($reseller->tenant_id !== $tenantId) {
            return collect();
        }

        $deals = Lead::where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->whereRaw('LOWER(reseller_name) = ?', [strtolower($reseller->name)])
            ->whereIn('status', ['active', 'expiring', 'expired'])
            ->whereNotIn('commission_status', ['locked', 'paid'])
            ->whereNotNull('days_left')
            ->select('id', 'name', 'stage', 'status', 'days_left', 'reseller_name', 'commission_status', 'updated_at')
            ->orderBy('days_left')
            ->get();

        return $this->checkManyForReseller($deals, $reseller);
    }

    // ── Private helpers ───────────────────────────────────────────

    private function ineligible(string $reason): array
    {
        return ['eligible' => false, 'reason' => $reason];
    }

    private function row(Lead $deal, bool $eligible, ?string $reason): array
    {
        return [
            'deal_id'  => $deal->id,
            'deal'     => $deal,
            'eligible' => $eligible,
            'reason'   => $reason,
        ];
    }
}
