<?php

namespace App\Http\Controllers;

use App\Models\Reseller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ResellerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Reseller::orderBy('performance_score', 'desc');

        if ($request->filled('tenant_id')) {
            $query->where('tenant_id', $request->tenant_id);
        }

        return response()->json($query->get());
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
        ]);

        $reseller = Reseller::create($data);
        return response()->json($reseller, 201);
    }

    public function show(Reseller $reseller): JsonResponse
    {
        return response()->json($reseller);
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
