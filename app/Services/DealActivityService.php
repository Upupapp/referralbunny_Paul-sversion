<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\LeadHistory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * DealActivityService
 *
 * Central service for recording all deal-related activity into lead_history.
 * Safe to call from controllers, services, jobs, and listeners.
 * Never throws — activity recording must not break the main business flow.
 */
class DealActivityService
{
    // ── Core record ───────────────────────────────────────────────────────────

    public function record(Lead $lead, string $action, string $type, array $options = []): void
    {
        try {
            [$actorName, $actorRole] = $this->resolveActor();

            LeadHistory::create([
                'lead_id'    => $lead->id,
                'tenant_id'  => $lead->tenant_id,
                'action'     => $action,
                'type'       => $type,
                'category'   => $options['category']   ?? $type,
                'reseller'   => $options['reseller']   ?? null,
                'actor_name' => $options['actor_name'] ?? $actorName,
                'actor_role' => $options['actor_role'] ?? $actorRole,
                'old_values' => isset($options['old_values']) ? $options['old_values'] : null,
                'new_values' => isset($options['new_values']) ? $options['new_values'] : null,
                'metadata'   => isset($options['metadata'])   ? $options['metadata']   : null,
                'date'       => now()->toDateString(),
            ]);
        } catch (\Throwable) {
            // Silently swallow — activity must never break the business flow
        }
    }

    // ── Partner events ────────────────────────────────────────────────────────

    /**
     * Record that a partner split was added to a deal.
     */
    public function partnerSplitAdded(Lead $lead, array $split): void
    {
        $shareDisplay = $this->formatShare($split);
        $name         = $split['partner_name']  ?? 'Unknown';
        $email        = $split['partner_email'] ?? '';

        $this->record(
            $lead,
            "Partner added: {$name}" . ($email ? " ({$email})" : '') . " — split: {$shareDisplay}",
            'partner',
            [
                'category'   => 'partner',
                'new_values' => [
                    'partner_name'      => $name,
                    'partner_email'     => $email,
                    'split_share_value' => $split['split_share_value'] ?? null,
                    'split_share_type'  => $split['split_share_type']  ?? 'percentage',
                    'display'           => $shareDisplay,
                ],
            ]
        );
    }

    /**
     * Record that a partner split was changed (old → new).
     */
    public function partnerSplitUpdated(Lead $lead, array $oldSplit, array $newSplit): void
    {
        $oldDisplay = $this->formatShare($oldSplit);
        $newDisplay = $this->formatShare($newSplit);
        $name       = $newSplit['partner_name'] ?? $oldSplit['partner_name'] ?? 'Unknown';

        $this->record(
            $lead,
            "Partner split changed for {$name}: {$oldDisplay} \u{2192} {$newDisplay}",
            'partner',
            [
                'category'   => 'partner',
                'old_values' => [
                    'partner_name'      => $oldSplit['partner_name']      ?? null,
                    'partner_email'     => $oldSplit['partner_email']     ?? null,
                    'split_share_value' => $oldSplit['split_share_value'] ?? null,
                    'split_share_type'  => $oldSplit['split_share_type']  ?? null,
                    'display'           => $oldDisplay,
                ],
                'new_values' => [
                    'partner_name'      => $newSplit['partner_name']      ?? null,
                    'partner_email'     => $newSplit['partner_email']     ?? null,
                    'split_share_value' => $newSplit['split_share_value'] ?? null,
                    'split_share_type'  => $newSplit['split_share_type']  ?? null,
                    'display'           => $newDisplay,
                ],
            ]
        );
    }

    /**
     * Record that a partner split was removed from a deal.
     */
    public function partnerSplitRemoved(Lead $lead, array $split): void
    {
        $shareDisplay = $this->formatShare($split);
        $name         = $split['partner_name']  ?? 'Unknown';
        $email        = $split['partner_email'] ?? '';

        $this->record(
            $lead,
            "Partner removed: {$name}" . ($email ? " ({$email})" : '') . " — had {$shareDisplay} split",
            'partner',
            [
                'category'   => 'partner',
                'old_values' => [
                    'partner_name'      => $name,
                    'partner_email'     => $email,
                    'split_share_value' => $split['split_share_value'] ?? null,
                    'split_share_type'  => $split['split_share_type']  ?? null,
                    'display'           => $shareDisplay,
                ],
            ]
        );
    }

    // ── Financial events ──────────────────────────────────────────────────────

    /**
     * Record a financial breakdown change with full before/after values.
     */
    public function financialBreakdownChanged(
        Lead   $lead,
        array  $old,
        array  $new,
        string $actorRole = 'Admin'
    ): void {
        $oldDv = (float) ($old['deal_value']    ?? 0);
        $newDv = (float) ($new['deal_value']    ?? 0);
        $oldAa = (float) ($old['added_amount']  ?? 0);
        $newAa = (float) ($new['added_amount']  ?? 0);
        $oldBc = (float) ($old['base_cost']     ?? 0);
        $newBc = (float) ($new['base_cost']     ?? 0);

        $parts = [];
        if (abs($newDv - $oldDv) > 0.01) {
            $parts[] = "Contract Value ₱" . number_format($oldDv, 0) . " → ₱" . number_format($newDv, 0);
        }
        if (abs($newBc - $oldBc) > 0.01) {
            $parts[] = "Base Cost ₱" . number_format($oldBc, 0) . " → ₱" . number_format($newBc, 0);
        }
        if (abs($newAa - $oldAa) > 0.01) {
            $parts[] = "Added Amount ₱" . number_format($oldAa, 0) . " → ₱" . number_format($newAa, 0);
        }

        $action = empty($parts)
            ? "Financial breakdown updated by {$actorRole}"
            : "Financial breakdown updated — " . implode('; ', $parts);

        $this->record(
            $lead,
            $action,
            'financial',
            [
                'category'   => 'financial',
                'actor_role' => $actorRole,
                'old_values' => array_merge($old, [
                    'company_share'   => round($oldAa * \App\Services\CommissionCalculationService::COMPANY_SHARE_RATE, 2),
                    'commission_pool' => round($oldAa * \App\Services\CommissionCalculationService::COMMISSION_POOL_RATE, 2),
                ]),
                'new_values' => array_merge($new, [
                    'company_share'   => round($newAa * \App\Services\CommissionCalculationService::COMPANY_SHARE_RATE, 2),
                    'commission_pool' => round($newAa * \App\Services\CommissionCalculationService::COMMISSION_POOL_RATE, 2),
                ]),
            ]
        );
    }

    // ── Stage events ──────────────────────────────────────────────────────────

    public function stageMoved(Lead $lead, string $fromStage, string $toStage, ?string $note = null): void
    {
        $fromLabel = $this->stageLabel($fromStage);
        $toLabel   = $this->stageLabel($toStage);
        $action    = "Stage moved: {$fromLabel} \u{2192} {$toLabel}" . ($note ? " — {$note}" : '');

        $this->record($lead, $action, 'stage', [
            'category'   => 'stage',
            'old_values' => ['stage' => $fromStage, 'stage_label' => $fromLabel],
            'new_values' => ['stage' => $toStage,   'stage_label' => $toLabel],
        ]);
    }

    public function commissionLocked(Lead $lead, float $commPool): void
    {
        $this->record(
            $lead,
            "Commission locked at ₱" . number_format($commPool, 2) . " (deal moved to Signed)",
            'commission',
            [
                'category'   => 'commission',
                'new_values' => ['commission_pool' => $commPool, 'status' => 'locked'],
            ]
        );
    }

    public function commissionPaid(Lead $lead, float $commPool): void
    {
        $this->record(
            $lead,
            "Commission marked as paid — pool ₱" . number_format($commPool, 2),
            'commission',
            [
                'category'   => 'commission',
                'new_values' => ['commission_pool' => $commPool, 'status' => 'paid'],
            ]
        );
    }

    // ── Assignment events ─────────────────────────────────────────────────────

    public function referrerReassigned(Lead $lead, string $oldName, string $newName): void
    {
        $this->record(
            $lead,
            "Referrer reassigned: {$oldName} \u{2192} {$newName}",
            'assignment',
            [
                'category'   => 'assignment',
                'reseller'   => $newName,
                'old_values' => ['referrer_name' => $oldName],
                'new_values' => ['referrer_name' => $newName],
            ]
        );
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Resolve the current actor's display name and role from any auth guard.
     * Returns a [name, role] pair.
     */
    public function resolveActor(): array
    {
        if (Auth::guard('tenant')->check()) {
            $u = Auth::guard('tenant')->user();
            return [$u->name ?? $u->email ?? 'Tenant Admin', 'Tenant Admin'];
        }
        if (Auth::guard('web')->check()) {
            $u = Auth::guard('web')->user();
            return [$u->name ?? $u->email ?? 'Super Admin', 'Super Admin'];
        }
        if (Auth::guard('reseller')->check()) {
            $u = Auth::guard('reseller')->user();
            return [$u->name ?? 'Referrer', 'Referrer'];
        }
        if (Auth::guard('partner')->check()) {
            $u = Auth::guard('partner')->user();
            return [$u->name ?? 'Partner', 'Partner'];
        }
        return ['System', 'System'];
    }

    private function formatShare(array $split): string
    {
        $v = (float) ($split['split_share_value'] ?? 0);
        $t = $split['split_share_type'] ?? 'percentage';
        return $t === 'percentage'
            ? $v . '%'
            : '₱' . number_format($v, 2);
    }

    private function stageLabel(string $stage): string
    {
        return match ($stage) {
            'introduction'  => 'Introduction',
            'presentation'  => 'Presentation',
            'contract_sent' => 'Contract Sent',
            'signed'        => 'Signed',
            'paid'          => 'Paid',
            default         => ucwords(str_replace('_', ' ', $stage)),
        };
    }
}
