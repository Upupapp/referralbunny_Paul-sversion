<?php

namespace App\Services;

use App\Models\ApprovalRequest;
use App\Models\PromoCode;
use App\Models\Promotion;
use Illuminate\Support\Facades\DB;
use App\Models\Plan;
use Illuminate\Support\Collection;

class ApprovalService
{
    public function __construct(private NotificationService $notifications) {}

    public function request(
        string $type,
        string $referenceId,
        string $referenceType,
        int    $requestedBy,
        array  $requestData,
        ?string $notes = null
    ): ApprovalRequest {
        $approval = ApprovalRequest::create([
            'request_type'       => $type,
            'reference_id'       => $referenceId,
            'reference_type'     => $referenceType,
            'requested_by'       => $requestedBy,
            'required_permission'=> 'approve_pricing',
            'status'             => 'pending',
            'notes'              => $notes,
            'request_data'       => $requestData,
        ]);

        $this->notifications->send(
            category:  'billing',
            type:      'action_required',
            priority:  'high',
            message:   "Approval required: {$this->describeType($type)} — reference #{$referenceId}.",
            actionUrl: "/platform/billing/approvals/{$approval->id}",
            channel:   'in_app',
            metadata:  ['approval_id' => $approval->id, 'type' => $type],
        );

        return $approval;
    }

    public function approve(ApprovalRequest $approval, int $approvedBy, ?string $notes = null): void
    {
        DB::transaction(function () use ($approval, $approvedBy, $notes) {
            $approval->update([
                'status'         => 'approved',
                'approved_by'    => $approvedBy,
                'approved_at'    => now(),
                'reviewer_notes' => $notes,
            ]);

            // Execute the pending change atomically with the status update
            $this->executeApprovedChange($approval);
        });

        $this->notifications->send(
            category:  'billing',
            type:      'info',
            priority:  'medium',
            message:   "Approval request #{$approval->id} has been approved.",
            actionUrl: "/platform/billing/approvals/{$approval->id}",
            channel:   'in_app',
            metadata:  ['approval_id' => $approval->id],
        );
    }

    public function reject(ApprovalRequest $approval, int $rejectedBy, ?string $notes = null): void
    {
        DB::transaction(function () use ($approval, $rejectedBy, $notes) {
            $approval->update([
                'status'         => 'rejected',
                'rejected_by'    => $rejectedBy,
                'rejected_at'    => now(),
                'reviewer_notes' => $notes,
            ]);

            // Mark the reference object as inactive/rejected
            $this->markReferenceRejected($approval);
        });

        $this->notifications->send(
            category:  'billing',
            type:      'warning',
            priority:  'medium',
            message:   "Approval request #{$approval->id} has been rejected.",
            actionUrl: "/platform/billing/approvals/{$approval->id}",
            channel:   'in_app',
            metadata:  ['approval_id' => $approval->id],
        );
    }

    public function getQueue(): Collection
    {
        return ApprovalRequest::with(['requestedBy', 'approvedBy'])
            ->where('status', 'pending')
            ->orderByDesc('created_at')
            ->limit(200)
            ->get();
    }

    public function getHistory(): Collection
    {
        return ApprovalRequest::with(['requestedBy', 'approvedBy'])
            ->whereIn('status', ['approved', 'rejected'])
            ->orderByDesc(DB::raw('COALESCE(approved_at, rejected_at)'))
            ->limit(100)
            ->get();
    }

    private function executeApprovedChange(ApprovalRequest $approval): void
    {
        $data = $approval->request_data;

        match ($approval->reference_type) {
            'promo_code'  => PromoCode::where('id', $approval->reference_id)->update(['status' => 'active']),
            'promotion'   => Promotion::where('id', $approval->reference_id)->update(['status' => 'active']),
            'plan'        => $this->executePlanPriceChange($approval),
            default       => null,
        };
    }

    private function markReferenceRejected(ApprovalRequest $approval): void
    {
        match ($approval->reference_type) {
            'promo_code' => PromoCode::where('id', $approval->reference_id)->update(['status' => 'inactive']),
            'promotion'  => Promotion::where('id', $approval->reference_id)->update(['status' => 'draft']),
            default      => null,
        };
    }

    private function executePlanPriceChange(ApprovalRequest $approval): void
    {
        $data = $approval->request_data;
        $plan = Plan::find($approval->reference_id);
        if (!$plan) return;

        $plan->update([
            'price_monthly' => $data['new_price_monthly'] ?? $plan->price_monthly,
            'price_yearly'  => $data['new_price_yearly']  ?? $plan->price_yearly,
        ]);

        \App\Models\PricingHistory::create([
            'plan_id'             => $plan->id,
            'old_price_monthly'   => $data['old_price_monthly'],
            'new_price_monthly'   => $data['new_price_monthly'],
            'old_price_yearly'    => $data['old_price_yearly'],
            'new_price_yearly'    => $data['new_price_yearly'],
            'currency'            => $data['currency'] ?? 'PHP',
            'update_rule'         => $data['update_rule'] ?? 'new_subscriptions_only',
            'change_reason'       => $data['change_reason'] ?? 'Approved via approval workflow',
            'changed_by'          => $approval->approved_by,
            'approval_request_id' => $approval->id,
        ]);
    }

    private function describeType(string $type): string
    {
        return match ($type) {
            'price_change'                => 'Plan price change',
            'promo_high_discount'         => 'High-value promo code',
            'promo_unlimited'             => 'Unlimited promo code',
            'promo_all_tenants'           => 'All-tenant promo',
            'price_decrease'              => 'Price decrease (>20%)',
            'existing_tenant_price_change'=> 'Existing tenant price change',
            'custom_enterprise_pricing'   => 'Custom enterprise pricing',
            'free_plan_modification'      => 'Free plan modification',
            default                       => $type,
        };
    }
}
