<?php

namespace App\Http\Middleware;

use App\Models\TenantLegalAgreement;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EnsureLegalAgreementsAccepted
{
    // Routes that must never be intercepted (to avoid redirect loops and break auth flows)
    private const SKIP_ROUTES = [
        'tenant.legal-agreements.accept',
        'tenant.legal-agreements.store-accept',
        'reseller.logout',
        'partner.logout',
        'partner.login',
        'partner.setup',
        'partner.setup.post',
        'partner.invite',
        'tenant.logout',
    ];

    public function handle(Request $request, Closure $next)
    {
        if ($request->routeIs(self::SKIP_ROUTES)) {
            return $next($request);
        }

        [$tenantId, $userType, $userId, $role] = $this->resolveContext($request);

        if (!$tenantId || !$userId) {
            return $next($request);
        }

        // Owners and admins manage agreements — never gate them
        if ($userType === 'tenant_user' && in_array($role, ['owner', 'admin'])) {
            return $next($request);
        }

        // Partners cannot access the tenant legal agreements page — skip for them
        if ($userType === 'partner') {
            return $next($request);
        }

        // Cache the result for 60 s per user — hasPending() fires two DB queries on
        // every authenticated page load (reseller, partner, and tenant routes).
        $cacheKey = "legal_pending:{$tenantId}:{$userType}:{$userId}";
        $hasPending = \Illuminate\Support\Facades\Cache::remember($cacheKey, 15, function () use ($tenantId, $userType, $userId, $role) {
            return $this->hasPending($tenantId, $userType, $userId, $role);
        });

        if ($hasPending) {
            // Bust the cache immediately so the acceptance page can see fresh state.
            \Illuminate\Support\Facades\Cache::forget($cacheKey);
            session()->put('legal_agreements.intended_url', $request->fullUrl());
            return redirect()->route('tenant.legal-agreements.accept', $tenantId);
        }

        return $next($request);
    }

    private function resolveContext(Request $request): array
    {
        if ($reseller = Auth::guard('reseller')->user()) {
            return [(string) $reseller->tenant_id, 'reseller', (string) $reseller->id, 'referrer'];
        }

        if ($partner = Auth::guard('partner')->user()) {
            return [(string) $partner->tenant_id, 'partner', (string) $partner->id, 'partner'];
        }

        if ($tenantUser = Auth::guard('tenant')->user()) {
            $tenantId = $request->route('tenantId');
            if (!$tenantId) return ['', '', '', ''];

            // EnsureTenantAccess already resolved and cached this — read from request attributes
            $cached = $request->attributes->get('_tenant_membership');
            $role   = is_array($cached) ? ($cached['role'] ?? null) : ($request->attributes->get('_tenant_role') ?? null);

            if (!$role) {
                $membership = DB::table('tenant_memberships')
                    ->where('tenant_user_id', $tenantUser->id)
                    ->where('tenant_id', $tenantId)
                    ->where('status', 'active')
                    ->first();
                $role = $membership?->role ?? 'member';
            }

            return [(string) $tenantId, 'tenant_user', (string) $tenantUser->id, $role];
        }

        return ['', '', '', ''];
    }

    private function hasPending(string $tenantId, string $userType, string $userId, string $role): bool
    {
        try {
            // Only acceptances made AFTER the agreement was last updated count as fresh
            $freshAcceptedIds = DB::table('tenant_legal_agreement_acceptances as a')
                ->join('tenant_legal_agreements as ag', 'a.tenant_legal_agreement_id', '=', 'ag.id')
                ->where('a.tenant_id', $tenantId)
                ->where('a.user_type', $userType)
                ->where('a.user_id', $userId)
                ->whereRaw('a.accepted_at >= ag.updated_at')
                ->pluck('a.tenant_legal_agreement_id')
                ->toArray();

            return TenantLegalAgreement::where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->where('is_required', true)
                ->when(!empty($freshAcceptedIds), fn ($q) => $q->whereNotIn('id', $freshAcceptedIds))
                ->get()
                ->filter(fn ($a) => $a->appliesToRole($role))
                ->isNotEmpty();
        } catch (\Throwable) {
            return false; // Never block access on DB error
        }
    }
}
