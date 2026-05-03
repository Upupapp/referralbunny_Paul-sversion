<?php

namespace App\Http\Controllers;

use App\Models\BillingAuditLog;
use App\Models\Promotion;
use App\Services\PromoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PromotionController extends Controller
{
    public function __construct(private PromoService $promos) {}

    // GET /api/promotions
    public function index(Request $request): JsonResponse
    {
        $query = Promotion::with('createdBy')->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->boolean('auto_apply')) {
            $query->where('auto_apply', true);
        }

        return response()->json($query->paginate(20));
    }

    // GET /api/promotions/{promotion}
    public function show(Promotion $promotion): JsonResponse
    {
        return response()->json($promotion->load(['createdBy', 'promoCode']));
    }

    // POST /api/promotions
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'                       => 'required|string|max:200',
            'description'                => 'nullable|string',
            'promotion_type'             => 'required|in:campaign,auto_apply,launch,pilot',
            'discount_type'              => 'required|in:percentage,fixed_amount,free_months,trial_extension',
            'discount_value'             => 'required|numeric|min:0',
            'currency'                   => 'nullable|string|max:3',
            'target_scope'               => 'required|in:all,new_tenants,existing_tenants,specific_tenants,industry,sub_industry',
            'target_ids_json'            => 'nullable|array',
            'applies_to_plan_ids_json'   => 'nullable|array',
            'applies_to_billing_cycles'  => 'nullable|array',
            'valid_from'                 => 'nullable|date',
            'valid_until'                => 'nullable|date|after:valid_from',
            'auto_apply'                 => 'nullable|boolean',
            'allow_stacking'             => 'nullable|boolean',
            'max_discounts_per_invoice'  => 'nullable|integer|min:1',
            'affects_duration'           => 'nullable|in:first_invoice,first_x_months,entire_subscription,next_billing_cycle',
            'duration_months'            => 'nullable|integer|min:1',
            'promo_code_id'              => 'nullable|string|exists:promo_codes,id',
            'status'                     => 'nullable|in:draft,active',
        ]);

        $result = $this->promos->createPromotion($data, $request->user()->id);
        return response()->json($result, 201);
    }

    // PUT /api/promotions/{promotion}
    public function update(Request $request, Promotion $promotion): JsonResponse
    {
        $data = $request->validate([
            'name'            => 'sometimes|string|max:200',
            'description'     => 'nullable|string',
            'valid_until'     => 'nullable|date',
            'auto_apply'      => 'sometimes|boolean',
            'allow_stacking'  => 'sometimes|boolean',
            'status'          => 'sometimes|in:draft,active,paused,ended',
            'target_ids_json' => 'sometimes|array',
        ]);

        $promotion->update($data);

        BillingAuditLog::log('promotion_updated', [
            'entity_type'  => 'promotion',
            'entity_id'    => $promotion->id,
            'performed_by' => $request->user()->id,
            'after'        => $data,
        ]);

        return response()->json($promotion->fresh());
    }

    // DELETE /api/promotions/{promotion}
    public function destroy(Request $request, Promotion $promotion): JsonResponse
    {
        $promotion->update(['status' => 'ended']);

        BillingAuditLog::log('promotion_ended', [
            'entity_type'  => 'promotion',
            'entity_id'    => $promotion->id,
            'performed_by' => $request->user()->id,
        ]);

        return response()->json(['message' => 'Promotion ended.']);
    }

    // GET /api/promotions/auto-apply?tenant_id=&plan_id=&billing_cycle=
    public function autoApply(Request $request): JsonResponse
    {
        $request->validate([
            'tenant_id'     => 'required|string|exists:tenants,id',
            'plan_id'       => 'nullable|string',
            'billing_cycle' => 'required|in:monthly,yearly',
        ]);

        $promos = $this->promos->autoApplyPromotions(
            $request->tenant_id,
            $request->plan_id,
            $request->billing_cycle
        );

        return response()->json($promos);
    }
}
