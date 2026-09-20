<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Reseller;
use App\Models\TenantMembership;
use App\Models\TenantUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PortalViewController extends Controller
{
    public function switchView(Request $request)
    {
        $data = $request->validate(['view' => 'required|in:admin,referrer', 'tenant_id' => 'nullable|string']);
        abort_if(Auth::guard('web')->check() || Auth::guard('partner')->check(), 403);

        if ($data['view'] === 'referrer') {
            if ($referrer = Auth::guard('reseller')->user()) {
                abort_unless(in_array($referrer->status, ['active', 'nda_signed']), 403);
                return redirect()->route('reseller.dashboard', $referrer->tenant_id);
            }
            $user = Auth::guard('tenant')->user();
            abort_unless($user && $user->status === 'active', 403);
            $memberships = TenantMembership::where('tenant_user_id', $user->id)->where('status', 'active');
            if (!empty($data['tenant_id'])) $memberships->where('tenant_id', $data['tenant_id']);
            $membership = $memberships->firstOrFail();
            $referrer = DB::transaction(function () use ($user, $membership) {
                // Lock the identity so concurrent switch requests cannot create duplicate profiles.
                TenantUser::whereKey($user->id)->lockForUpdate()->firstOrFail();
                $existing = Reseller::withTrashed()->where('linked_tenant_user_id', $user->id)
                    ->where('tenant_id', $membership->tenant_id)->first();
                if ($existing) {
                    abort_if($existing->trashed() || !in_array($existing->status, ['active', 'nda_signed']), 403);
                    return $existing;
                }
                // A company's owner may refer for another company; preserve that linked profile.
                $linked = Reseller::where('linked_tenant_user_id', $user->id)
                    ->whereIn('status', ['active', 'nda_signed'])->orderBy('id')->first();
                if ($linked) return $linked;
                // Never attach an unrelated account based only on its email address.
                abort_if(Reseller::withTrashed()->where('tenant_id', $membership->tenant_id)
                    ->whereRaw('LOWER(email) = ?', [strtolower($user->email)])->exists(), 409,
                    'An existing referrer profile must be linked to your account before switching.');
                return Reseller::create([
                    'tenant_id' => $membership->tenant_id,
                    'linked_tenant_user_id' => $user->id,
                    'name' => trim($user->first_name.' '.$user->last_name),
                    'email' => strtolower($user->email), 'status' => 'active',
                    'is_anonymous' => false, 'assigned_leads' => 0,
                    'closed_value' => 0, 'performance_score' => 0,
                    'joined_date' => now()->toDateString(),
                ]);
            });
            Auth::guard('tenant')->logout();
            Auth::guard('reseller')->login($referrer);
            $request->session()->forget(['url.intended', 'legal_agreements.intended_url']);
            $request->session()->regenerate();
            return redirect()->route('reseller.dashboard', $referrer->tenant_id);
        }

        $user = Auth::guard('tenant')->user();
        if (!$user) {
            $referrer = Auth::guard('reseller')->user();
            abort_unless($referrer && in_array($referrer->status, ['active', 'nda_signed']), 403);
            $user = $referrer->linked_tenant_user_id ? TenantUser::find($referrer->linked_tenant_user_id) : null;
            if (!$user) return redirect()->route('portal.my-programs');
        }
        abort_unless($user->status === 'active', 403);
        $memberships = TenantMembership::where('tenant_user_id', $user->id)->where('status', 'active')->get();
        if ($memberships->isEmpty()) return redirect()->route('portal.my-programs');
        Auth::guard('reseller')->logout();
        Auth::guard('tenant')->login($user);
        $request->session()->forget(['url.intended', 'legal_agreements.intended_url']);
        $request->session()->regenerate();
        return $memberships->count() === 1
            ? redirect()->route('tenant.dashboard', $memberships->first()->tenant_id)
            : redirect()->route('tenant.select-workspace');
    }

    public function myPrograms()
    {
        return view('auth.my-programs');
    }
}
