<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\PromoCode;
use App\Models\Subscription;
use App\Services\PromoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PromoCodeController extends Controller
{
    public function __construct(private PromoService $promos) {}

    // GET /api/promo-codes
    public function index(Request $request): JsonResponse
    {
        $query = PromoCode::withCount('redemptions')->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return response()->json($query->paginate(20));
    }

    // GET /api/promo-codes/{promoCode}
    public function show(PromoCode $promoCode): JsonResponse
    {
        return response()->json($promoCode->load(['redemptions.tenant', 'attempts']));
    }

    // POST /api/promo-codes
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code'                      => 'nullable|string|max:50|unique:promo_codes,code',
            'name'                      => 'required|string|max:200',
            'description'               => 'nullable|string',
            'discount_type'             => 'required|in:percentage,fixed_amount,free_months,trial_extension',
            'discount_value'            => 'required|numeric|min:0',
            'currency'                  => 'nullable|string|max:3',
            'max_redemptions'           => 'nullable|integer|min:1',
            'valid_from'                => 'nullable|date',
            'valid_until'               => 'nullable|date|after:valid_from',
            'applies_to_plan_ids_json'  => 'nullable|array',
            'applies_to_billing_cycle'  => 'nullable|in:monthly,yearly,both',
            'eligibility_rules_json'    => 'nullable|array',
            'allow_stacking'            => 'nullable|boolean',
        ]);

        $result = $this->promos->createPromoCode($data, $request->user()->id);
        return response()->json($result, 201);
    }

    // PUT /api/promo-codes/{promoCode}
    public function update(Request $request, PromoCode $promoCode): JsonResponse
    {
        $data = $request->validate([
            'name'                      => 'sometimes|string|max:200',
            'description'               => 'nullable|string',
            'valid_until'               => 'nullable|date',
            'max_redemptions'           => 'nullable|integer|min:1',
            'applies_to_plan_ids_json'  => 'sometimes|array',
            'applies_to_billing_cycle'  => 'sometimes|in:monthly,yearly,both',
            'eligibility_rules_json'    => 'sometimes|array',
            'allow_stacking'            => 'sometimes|boolean',
            'status'                    => 'sometimes|in:active,inactive',
        ]);

        $promoCode->update($data);

        \App\Models\BillingAuditLog::log('promo_code_updated', [
            'entity_type'  => 'promo_code',
            'entity_id'    => $promoCode->id,
            'performed_by' => $request->user()->id,
            'after'        => $data,
        ]);

        return response()->json($promoCode->fresh());
    }

    // DELETE /api/promo-codes/{promoCode}
    public function destroy(Request $request, PromoCode $promoCode): JsonResponse
    {
        $promoCode->update(['status' => 'inactive']);

        \App\Models\BillingAuditLog::log('promo_code_disabled', [
            'entity_type'  => 'promo_code',
            'entity_id'    => $promoCode->id,
            'performed_by' => $request->user()->id,
        ]);

        return response()->json(['message' => 'Promo code disabled.']);
    }

    // POST /api/promo-codes/validate
    public function validate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code'          => 'required|string',
            'tenant_id'     => 'required|string|exists:tenants,id',
            'plan_id'       => 'nullable|string|exists:plans,id',
            'billing_cycle' => 'required|in:monthly,yearly',
        ]);

        $result = $this->promos->validate($data['code'], $data['tenant_id'], $data['plan_id'] ?? null, $data['billing_cycle']);
        $statusCode = $result['valid'] ? 200 : 422;
        return response()->json($result, $statusCode);
    }

    // POST /api/promo-codes/apply
    public function apply(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code'            => 'required|string',
            'tenant_id'       => 'required|string|exists:tenants,id',
            'subscription_id' => 'required|string|exists:subscriptions,id',
            'invoice_id'      => 'required|string|exists:invoices,id',
        ]);

        $validResult = $this->promos->validate(
            $data['code'],
            $data['tenant_id'],
            null,
            Subscription::find($data['subscription_id'])->billing_cycle
        );

        if (!$validResult['valid']) {
            return response()->json($validResult, 422);
        }

        $redemption = $this->promos->apply(
            $validResult['promo_code'],
            Subscription::find($data['subscription_id']),
            Invoice::find($data['invoice_id']),
            $request->user()->id
        );

        return response()->json($redemption->load('promoCode'), 201);
    }

    // GET /api/promo-codes/performance
    public function performance(): JsonResponse
    {
        return response()->json($this->promos->getPerformance());
    }
}
