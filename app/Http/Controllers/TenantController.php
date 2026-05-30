<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\TenantConfig;
use App\Models\TenantSubIndustry;
use App\Services\BillingService;
use App\Services\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class TenantController extends Controller
{
    public function index(): JsonResponse
    {
        abort_unless(TenantContext::isSuperAdmin(), 403, 'Only platform admins can manage tenants.');
        $tenants = Tenant::with(['config', 'subIndustries'])->orderBy('created_at', 'asc')->paginate(25);
        return response()->json($tenants);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless(TenantContext::isSuperAdmin(), 403, 'Only platform admins can manage tenants.');
        $data = $request->validate([
            'name'           => 'required|string|max:255',
            'program_name'   => 'required|string|max:255',
            'description'    => 'nullable|string',
            'accent_color'   => ['nullable', 'string', 'max:7', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'admin_name'     => 'nullable|string',
            'admin_email'    => 'required|email',
            'industry'       => 'nullable|string',
            'status'         => 'nullable|in:active,trial,inactive',
            'sub_industries' => 'nullable|array',
            'config'         => 'nullable|array',
            'config.fields'  => 'nullable|array|max:50',
            'config.stages'  => 'nullable|array|max:20',
        ]);

        $slug = Str::slug($data['name']) . '-' . time();

        $tenant = Tenant::create([
            'id'           => $slug,
            'name'         => $data['name'],
            'slug'         => $slug,
            'program_name' => $data['program_name'],
            'description'  => $data['description'] ?? null,
            'accent_color' => $data['accent_color'] ?? '#FF8A3D',
            'admin_name'   => $data['admin_name'] ?? null,
            'admin_email'  => $data['admin_email'],
            'industry'     => $data['industry'] ?? null,
            'status'       => $data['status'] ?? 'trial',
        ]);

        if (!empty($data['config'])) {
            $commissionPayload = $data['config']['commission'] ?? [];
            // LOCKED: lgu-ids commission rates must never be overridden
            if ($tenant->id === 'lgu-ids') {
                unset($commissionPayload['commission_pool_rate'], $commissionPayload['company_share_rate']);
            }
            TenantConfig::create([
                'tenant_id'                 => $tenant->id,
                'lead_type'                 => $data['config']['leadType'] ?? 'custom',
                'lead_label'                => $data['config']['leadLabel'] ?? 'Lead',
                'fields'                    => $data['config']['fields'] ?? [],
                'stages'                    => $data['config']['stages'] ?? [],
                'commission'                => $commissionPayload,
                'primary_identifier_fields' => $data['config']['primaryIdentifierFields'] ?? [],
            ]);
        }

        if (!empty($data['sub_industries'])) {
            foreach ($data['sub_industries'] as $sub) {
                TenantSubIndustry::create(['tenant_id' => $tenant->id, 'sub_industry' => $sub]);
            }
        }

        // Auto-start 15-day trial
        try {
            app(BillingService::class)->startTrial($tenant);
        } catch (\Throwable $e) {
            // Non-fatal — trial can be started manually
        }

        return response()->json($tenant->load(['config', 'subIndustries']), 201);
    }

    public function show(Tenant $tenant): JsonResponse
    {
        abort_unless(TenantContext::isSuperAdmin(), 403, 'Only platform admins can manage tenants.');
        return response()->json($tenant->load(['config', 'subIndustries', 'resellers']));
    }

    public function update(Request $request, Tenant $tenant): JsonResponse
    {
        abort_unless(TenantContext::isSuperAdmin(), 403, 'Only platform admins can manage tenants.');
        $data = $request->validate([
            'name'         => 'sometimes|string|max:255',
            'program_name' => 'sometimes|string|max:255',
            'description'  => 'nullable|string',
            'accent_color' => ['nullable', 'string', 'max:7', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'admin_name'   => 'nullable|string',
            'admin_email'  => 'sometimes|email',
            'industry'     => 'nullable|string',
            'status'       => 'nullable|in:active,trial,inactive',
        ]);

        $tenant->update($data);
        return response()->json($tenant->fresh(['config', 'subIndustries']));
    }

    public function destroy(Tenant $tenant): JsonResponse
    {
        abort_unless(TenantContext::isSuperAdmin(), 403, 'Only platform admins can manage tenants.');
        abort_if($tenant->id === 'lgu-ids', 403, 'The LGU IDS tenant is protected and cannot be deleted.');
        $tenant->delete();
        return response()->json(['message' => 'Tenant deleted.']);
    }

    public function suspend(Tenant $tenant): JsonResponse
    {
        abort_unless(TenantContext::isSuperAdmin(), 403, 'Only platform admins can manage tenants.');
        $tenant->update(['status' => 'inactive']);
        return response()->json(['message' => 'Tenant suspended.', 'tenant' => $tenant]);
    }

    public function activate(Tenant $tenant): JsonResponse
    {
        abort_unless(TenantContext::isSuperAdmin(), 403, 'Only platform admins can manage tenants.');
        $tenant->update(['status' => 'active']);
        return response()->json(['message' => 'Tenant activated.', 'tenant' => $tenant]);
    }
}
