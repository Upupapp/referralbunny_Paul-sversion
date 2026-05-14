<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Services\PricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PricingController extends Controller
{
    public function __construct(private PricingService $pricing) {}

    // GET /api/pricing/plans
    public function plans(): JsonResponse
    {
        $plans = Plan::where('is_active', true)
            ->orderByRaw("CASE name WHEN 'Free' THEN 1 WHEN 'Basic' THEN 2 WHEN 'Starter' THEN 3 WHEN 'Growth' THEN 4 WHEN 'Pro' THEN 5 WHEN 'Max' THEN 6 WHEN 'Enterprise' THEN 7 ELSE 8 END")
            ->orderByDesc('price_monthly')
            ->get()
            ->unique('name')   // deduplicate — keep first (highest price) per name
            ->values();

        return response()->json($plans);
    }

    // GET /api/pricing/plans/{plan}
    public function show(Plan $plan): JsonResponse
    {
        return response()->json($plan);
    }

    // PUT /api/pricing/plans/{plan}/price
    public function updatePrice(Request $request, Plan $plan): JsonResponse
    {
        $data = $request->validate([
            'price_monthly' => 'sometimes|numeric|min:0',
            'price_yearly'  => 'sometimes|numeric|min:0',
            'update_rule'   => 'required|in:new_subscriptions_only,next_billing_cycle,immediate_proration,grandfather',
            'change_reason' => 'nullable|string|max:500',
        ]);

        $result = $this->pricing->updatePlanPrice($plan, $data, $request->user()->id);
        return response()->json($result);
    }

    // PUT /api/pricing/plans/{plan}
    public function update(Request $request, Plan $plan): JsonResponse
    {
        $data = $request->validate([
            'name'                  => 'sometimes|string|max:100',
            'description'           => 'nullable|string',
            'is_active'             => 'sometimes|boolean',
            'plan_limits_json'      => 'sometimes|array',
            'plan_features_json'    => 'sometimes|array',
            'billing_cycle_options' => 'sometimes|array',
        ]);

        $updated = $this->pricing->updatePlanMeta($plan, $data, $request->user()->id);
        return response()->json($updated);
    }

    // GET /api/pricing/history
    // GET /api/pricing/history?plan_id=xxx
    public function history(Request $request): JsonResponse
    {
        return response()->json($this->pricing->getPricingHistory($request->plan_id));
    }
}
