<?php

namespace App\Http\Controllers;

use App\Models\Reseller;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ResellerPortalController extends Controller
{
    private function reseller(): Reseller
    {
        $reseller = Auth::guard('reseller')->user();
        if ($reseller instanceof Reseller) {
            return $reseller;
        }
        // Super admin accessing reseller portal — not supported directly.
        // Super admins should use the tenant admin portal instead.
        abort(403, 'Reseller portal requires reseller authentication.');
    }

    public function dashboard($tenantId)
    {
        $reseller = $this->reseller();
        $tenant   = Tenant::findOrFail($tenantId);

        $leads = DB::table('leads')
            ->where('tenant_id', $tenantId)
            ->where('reseller_name', $reseller->name)
            ->orderByDesc('created_at')
            ->get();

        $stats = [
            'total'      => $leads->count(),
            'active'     => $leads->whereIn('status', ['active', 'expiring'])->count(),
            'expiring'   => $leads->where('status', 'expiring')->count(),
            'paid'       => $leads->where('stage', 'paid')->count(),
            'pipeline'   => $leads->sum('deal_value'),
            'conversion' => $leads->count() > 0
                ? round($leads->where('stage', 'paid')->count() / $leads->count() * 100)
                : 0,
        ];

        $recentLeads = $leads->take(6);

        return view('reseller.dashboard', compact('reseller', 'tenant', 'stats', 'recentLeads'));
    }

    public function deals($tenantId)
    {
        $reseller = $this->reseller();
        $tenant   = Tenant::findOrFail($tenantId);
        return view('reseller.deals.index', compact('reseller', 'tenant'));
    }

    public function commission($tenantId)
    {
        $reseller = $this->reseller();
        $tenant   = Tenant::findOrFail($tenantId);

        $leads = DB::table('leads')
            ->where('tenant_id', $tenantId)
            ->where('reseller_name', $reseller->name)
            ->get();

        $commissionStats = [
            'pending' => $leads->where('commission_status', 'pending')->sum('deal_value'),
            'locked'  => $leads->where('commission_status', 'locked')->sum('deal_value'),
            'paid'    => $leads->where('commission_status', 'paid')->sum('deal_value'),
        ];

        return view('reseller.commission', compact('reseller', 'tenant', 'leads', 'commissionStats'));
    }

    public function profile($tenantId)
    {
        $reseller = $this->reseller();
        $tenant   = Tenant::findOrFail($tenantId);
        return view('reseller.profile', compact('reseller', 'tenant'));
    }
}
