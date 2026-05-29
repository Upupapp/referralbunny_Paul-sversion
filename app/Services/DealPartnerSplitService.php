<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\DealPartnerSplit;
use App\Models\Lead;
use App\Models\Notification;
use App\Models\Partner;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Manages partner split share allocations on deals.
 *
 * Rules:
 * - Every split must have a partner name + email.
 * - Partner can be active, pending invite, or provisional.
 * - Deduplication: one active split per tenant+deal+email.
 * - Tenant-scoped: never cross-tenant lookups.
 */
class DealPartnerSplitService
{
    /**
     * Create or update a partner split on a deal.
     * Automatically links to existing Partner/contact if found.
     *
     * @return DealPartnerSplit
     */
    public function upsert(
        string  $tenantId,
        string  $dealId,
        string  $partnerName,
        string  $partnerEmail,
        float   $splitValue,
        string  $splitType    = 'percentage',
        string  $currency     = 'PHP',
        string  $source       = 'manual',
        string  $actorId      = 'system',
        ?string $existingSplitId = null
    ): DealPartnerSplit {
        $email         = strtolower(trim($partnerEmail));
        $emailProvided = !empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL);

        if (empty($partnerName)) {
            throw new \InvalidArgumentException('Partner name is required for a split share.');
        }
        // Email is optional — only validate format when one is actually supplied
        if (!empty($email) && !$emailProvided) {
            throw new \InvalidArgumentException('The partner email address is not valid.');
        }

        // If updating, load existing record
        if ($existingSplitId) {
            $split = DealPartnerSplit::where('id', $existingSplitId)
                ->where('tenant_id', $tenantId)
                ->where('deal_id', $dealId)
                ->firstOrFail();
        } else {
            $split = null;

            if ($emailProvided) {
                // Prevent duplicate active splits for same email on same deal
                $split = DealPartnerSplit::where('tenant_id', $tenantId)
                    ->where('deal_id', $dealId)
                    ->where('partner_email', $email)
                    ->whereNull('deleted_at')
                    ->where('status', '!=', 'removed')
                    ->first();
            }

            if (!$split) {
                $split = new DealPartnerSplit();
                $split->id        = (string) Str::uuid();
                $split->tenant_id = $tenantId;
                $split->deal_id   = $dealId;
            }
        }

        // Resolve partner user / contact — only when email is provided
        $partnerUserId    = null;
        $partnerContactId = null;
        $status           = 'provisional';
        if ($emailProvided) {
            [$partnerUserId, $partnerContactId, $status] = $this->resolvePartner($tenantId, $email);
        }

        $split->fill([
            'partner_name'        => $partnerName,
            'partner_email'       => $emailProvided ? $email : '',  // NOT NULL column — store empty string when no email
            'split_share_value'   => $splitValue,
            'split_share_type'    => $splitType,
            'currency'            => $currency,
            'status'              => $status,
            'source'              => $source,
            'partner_user_id'     => $partnerUserId,
            'partner_contact_id'  => $partnerContactId,
            'created_by_user_id'  => $split->created_by_user_id ?? $actorId,
            'updated_by_user_id'  => $actorId,
        ]);

        $split->save();

        $this->audit($tenantId, $dealId, $split->id, 'partner_split_created', $actorId, [
            'partner_email'    => substr($email, 0, 3) . '***',
            'split_share'      => $splitValue,
            'split_type'       => $splitType,
        ]);

        return $split->fresh();
    }

    /**
     * Soft-remove a partner split.
     */
    public function remove(
        string $tenantId,
        string $splitId,
        string $actorId = 'system'
    ): void {
        $split = DealPartnerSplit::where('id', $splitId)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        $split->update([
            'status'             => 'removed',
            'removed_at'         => now(),
            'deleted_at'         => now(),
            'updated_by_user_id' => $actorId,
        ]);

        $this->audit($tenantId, $split->deal_id, $splitId, 'partner_split_removed', $actorId);
    }

    /**
     * Get all active partner splits for a deal.
     */
    public function getForDeal(string $tenantId, string $dealId): \Illuminate\Support\Collection
    {
        return DealPartnerSplit::where('tenant_id', $tenantId)
            ->where('deal_id', $dealId)
            ->whereNull('deleted_at')
            ->where('status', '!=', 'removed')
            ->orderBy('created_at')
            ->get()
            ->map(fn($s) => [
                'id'               => $s->id,
                'partner_name'     => $s->partner_name,
                'partner_email'    => $s->partner_email,
                'split_share_value'=> (float) $s->split_share_value,
                'split_share_type' => $s->split_share_type,
                'currency'         => $s->currency,
                'status'           => $s->status,
                'status_label'     => $s->status_label,
                'display_share'    => $s->display_share,
                'partner_user_id'  => $s->partner_user_id,
                'source'           => $s->source,
                'created_at'       => $s->created_at?->toIso8601String(),
            ]);
    }

    /**
     * Compute total partner split share for a deal (percentage).
     */
    public function totalPercentage(string $tenantId, string $dealId): float
    {
        return (float) DealPartnerSplit::where('tenant_id', $tenantId)
            ->where('deal_id', $dealId)
            ->where('split_share_type', 'percentage')
            ->whereNull('deleted_at')
            ->where('status', '!=', 'removed')
            ->sum('split_share_value');
    }

    // ── Private helpers ───────────────────────────────────────────────────

    /**
     * Resolve partner user and contact for given email within tenant.
     * Returns [partner_user_id|null, partner_contact_id|null, status].
     */
    private function resolvePartner(string $tenantId, string $email): array
    {
        // Active Partner in partner_users
        $partnerUser = Partner::where('tenant_id', $tenantId)
            ->where('email', $email)
            ->whereIn('status', ['active', 'invited'])
            ->first();

        if ($partnerUser) {
            $status = $partnerUser->status === 'active' ? 'active' : 'pending_invite';
            return [$partnerUser->id, null, $status];
        }

        // Contact with partner function
        $contact = DB::table('contacts')
            ->where('tenant_id', $tenantId)
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        if ($contact) {
            return [null, $contact->id, 'provisional'];
        }

        return [null, null, 'provisional'];
    }

    private function audit(string $tenantId, string $dealId, string $splitId, string $event, string $actorId, array $extra = []): void
    {
        try {
            ActivityLog::create([
                'id'        => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'user_id'   => $actorId,
                'action'    => $event,
                'entity'    => 'deal_partner_split',
                'entity_id' => $splitId,
                'metadata'  => array_merge([
                    'deal_id'   => $dealId,
                    'timestamp' => now()->toIso8601String(),
                ], $extra),
            ]);
        } catch (\Throwable) {}
    }
}
