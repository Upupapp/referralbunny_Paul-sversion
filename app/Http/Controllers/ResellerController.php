<?php

namespace App\Http\Controllers;

use App\Models\Reseller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ResellerController extends Controller
{
    /**
     * Determine whether the current request comes from a tenant admin (web or
     * sanctum super-admin guard) rather than a reseller-level caller.
     *
     * Tenant admins always receive unmasked data.
     * Other resellers receive masked data for anonymous resellers.
     */
    private function callerIsTenantAdmin(): bool
    {
        // Web session (Blade tenant admin) or super-admin sanctum token
        return Auth::guard('web')->check()
            || Auth::guard('tenant')->check()
            || (Auth::guard('sanctum')->check() && Auth::guard('sanctum')->user() instanceof \App\Models\User);
    }

    private function applyAnonymityMask(Reseller $reseller, bool $isTenantAdmin): array
    {
        if (!$isTenantAdmin && $reseller->is_anonymous) {
            return $reseller->toAnonymousArray();
        }

        return $reseller->toArray();
    }

    public function index(Request $request): JsonResponse
    {
        $query = Reseller::orderBy('performance_score', 'desc');

        if ($request->filled('tenant_id')) {
            $query->where('tenant_id', $request->tenant_id);
        }

        $isTenantAdmin = $this->callerIsTenantAdmin();

        $resellers = $query->get()->map(
            fn ($r) => $this->applyAnonymityMask($r, $isTenantAdmin)
        );

        return response()->json($resellers);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tenant_id'         => 'required|string|exists:tenants,id',
            'name'              => 'required|string',
            'email'             => 'required|email',
            'status'            => 'nullable|in:invited,active,nda_signed',
            'phone'             => 'nullable|string',
            'territory'         => 'nullable|string',
            'assigned_leads'    => 'nullable|integer',
            'closed_value'      => 'nullable|numeric',
            'performance_score' => 'nullable|integer',
            'joined_date'       => 'nullable|date',
            'is_anonymous'      => 'nullable|boolean',
        ]);

        $reseller = Reseller::create($data);
        return response()->json($reseller, 201);
    }

    public function show(Request $request, Reseller $reseller): JsonResponse
    {
        $data = $this->applyAnonymityMask($reseller, $this->callerIsTenantAdmin());
        return response()->json($data);
    }

    public function update(Request $request, Reseller $reseller): JsonResponse
    {
        $data = $request->validate([
            'name'              => 'sometimes|string',
            'email'             => 'sometimes|email',
            'status'            => 'sometimes|in:invited,active,nda_signed',
            'phone'             => 'nullable|string',
            'territory'         => 'nullable|string',
            'assigned_leads'    => 'sometimes|integer',
            'closed_value'      => 'sometimes|numeric',
            'performance_score' => 'sometimes|integer',
            'is_anonymous'      => 'sometimes|boolean',
        ]);

        $reseller->update($data);
        return response()->json($reseller);
    }

    public function destroy(Reseller $reseller): JsonResponse
    {
        $reseller->delete();
        return response()->json(['message' => 'Reseller deleted.']);
    }
}
