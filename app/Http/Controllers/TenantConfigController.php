<?php

namespace App\Http\Controllers;

use App\Models\TenantConfig;
use Illuminate\Http\Request;

class TenantConfigController extends Controller
{
    public function index()        { abort(403, 'Not exposed.'); }
    public function store(Request $request) { abort(403, 'Not exposed.'); }
    public function show(TenantConfig $tenantConfig) { abort(403, 'Not exposed.'); }

    public function update(Request $request, TenantConfig $tenantConfig)
    {
        abort_unless(\App\Services\TenantContext::isSuperAdmin(), 403, 'Super admin access required.');
        abort_if($tenantConfig->tenant_id === 'lgu-ids', 403, 'LGU IDS config is locked and cannot be modified via this endpoint.');
        abort(403, 'Not exposed.');
    }

    public function destroy(TenantConfig $tenantConfig)
    {
        abort_unless(\App\Services\TenantContext::isSuperAdmin(), 403, 'Super admin access required.');
        abort_if($tenantConfig->tenant_id === 'lgu-ids', 403, 'LGU IDS config is locked.');
        abort(403, 'Not exposed.');
    }
}
