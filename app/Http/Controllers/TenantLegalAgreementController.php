<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\TenantLegalAgreement;
use App\Models\TenantLegalAgreementAcceptance;
use App\Services\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TenantLegalAgreementController extends Controller
{
    // ── API: List ────────────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        // Always derive from authenticated session; query param is a hint for SA context-switching only
        $tenantId = TenantContext::id() ?? $request->query('tenant_id');
        if (!$tenantId) return response()->json(['error' => 'tenant_id required'], 422);

        $agreements = TenantLegalAgreement::where('tenant_id', $tenantId)
            ->orderBy('display_order')
            ->orderBy('created_at')
            ->withCount('acceptances')
            ->get()
            ->map(fn($a) => array_merge($a->toArray(), [
                'effective_date' => $a->effective_date?->format('Y-m-d'),
            ]));

        return response()->json($agreements);
    }

    // ── API: Store ────────────────────────────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $tenantId = $request->input('tenant_id');
        $this->authorizeAdminAccess($tenantId);

        $data = $request->validate([
            'tenant_id'          => 'required|string',
            'type'               => 'required|in:nda,non_compete,confidentiality,custom',
            'title'              => 'required|string|max:200',
            'content'            => 'required|string',
            'applicable_roles'   => 'nullable|array',
            'applicable_roles.*' => 'string|in:owner,admin,manager,referrer,partner,member,viewer',
            'is_required'        => 'boolean',
            'is_active'          => 'boolean',
            'version'            => 'nullable|string|max:50',
            'effective_date'     => 'nullable|date',
            'display_order'      => 'nullable|integer',
        ]);

        $agreement = TenantLegalAgreement::create([
            'tenant_id'        => $data['tenant_id'],
            'type'             => $data['type'],
            'title'            => $data['title'],
            'content'          => $data['content'],
            'applicable_roles' => $data['applicable_roles'] ?? null,
            'is_required'      => $data['is_required'] ?? true,
            'is_active'        => $data['is_active'] ?? true,
            'version'          => $data['version'] ?? null,
            'effective_date'   => $data['effective_date'] ?? null,
            'display_order'    => $data['display_order'] ?? 0,
        ]);

        return response()->json(
            array_merge($agreement->loadCount('acceptances')->toArray(), [
                'effective_date' => $agreement->effective_date?->format('Y-m-d'),
            ]),
            201
        );
    }

    // ── API: Update ───────────────────────────────────────────────────────────

    public function update(Request $request, string $id): JsonResponse
    {
        $agreement = TenantLegalAgreement::findOrFail($id);
        $this->authorizeAdminAccess($agreement->tenant_id);

        $data = $request->validate([
            'type'               => 'sometimes|in:nda,non_compete,confidentiality,custom',
            'title'              => 'sometimes|required|string|max:200',
            'content'            => 'sometimes|required|string',
            'applicable_roles'   => 'nullable|array',
            'applicable_roles.*' => 'string|in:owner,admin,manager,referrer,partner,member,viewer',
            'is_required'        => 'boolean',
            'is_active'          => 'boolean',
            'version'            => 'nullable|string|max:50',
            'effective_date'     => 'nullable|date',
            'display_order'      => 'nullable|integer',
        ]);

        $agreement->update($data);

        return response()->json(
            array_merge($agreement->loadCount('acceptances')->toArray(), [
                'effective_date' => $agreement->fresh()->effective_date?->format('Y-m-d'),
            ])
        );
    }

    // ── API: Destroy ──────────────────────────────────────────────────────────

    public function destroy(string $id): JsonResponse
    {
        $agreement = TenantLegalAgreement::findOrFail($id);
        $this->authorizeAdminAccess($agreement->tenant_id);
        $agreement->delete();
        return response()->json(['success' => true]);
    }

    // ── API: Pending check (agreements not yet accepted by a given user) ──────

    public function pending(Request $request): JsonResponse
    {
        $tenantId = $request->query('tenant_id');
        $userType = $request->query('user_type', 'tenant_user');
        $userId   = $request->query('user_id');
        $role     = $request->query('role', '');

        if (!$tenantId || !$userId) {
            return response()->json(['error' => 'tenant_id and user_id required'], 422);
        }

        $accepted = TenantLegalAgreementAcceptance::where('tenant_id', $tenantId)
            ->where('user_type', $userType)
            ->where('user_id', $userId)
            ->pluck('tenant_legal_agreement_id')
            ->toArray();

        $agreements = TenantLegalAgreement::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where('is_required', true)
            ->when(!empty($accepted), fn($q) => $q->whereNotIn('id', $accepted))
            ->orderBy('display_order')
            ->get()
            ->filter(fn($a) => $a->appliesToRole($role))
            ->values();

        return response()->json($agreements);
    }

    // ── API: Record single acceptance ─────────────────────────────────────────

    public function accept(Request $request, string $id): JsonResponse
    {
        $agreement = TenantLegalAgreement::findOrFail($id);

        $data = $request->validate([
            'user_type' => 'required|in:tenant_user,reseller',
            'user_id'   => 'required|string',
            'user_role' => 'nullable|string',
        ]);

        TenantLegalAgreementAcceptance::updateOrCreate(
            [
                'tenant_legal_agreement_id' => $agreement->id,
                'user_type'                 => $data['user_type'],
                'user_id'                   => $data['user_id'],
            ],
            [
                'tenant_id'   => $agreement->tenant_id,
                'user_role'   => $data['user_role'] ?? null,
                'accepted_at' => now(),
                'ip_address'  => $request->ip(),
                'user_agent'  => substr($request->userAgent() ?? '', 0, 500),
            ]
        );

        return response()->json(['success' => true, 'accepted_at' => now()->toISOString()]);
    }

    // ── Web: Acceptance page (shown after invite accept / referrer setup) ─────

    public function showAccept(Request $request, string $tenantId)
    {
        try {
            $tenant = Tenant::findOrFail($tenantId);

            [$userType, $userId, $role] = $this->resolveUserContext($tenantId);

            if (!$userId) {
                return redirect()->route('tenant.login');
            }

            // Owners and admins are never gated — they create agreements, not sign them
            if ($userType === 'tenant_user' && in_array($role, ['owner', 'admin'])) {
                return $this->afterAcceptRedirect($tenantId, $userType);
            }

            $pendingAgreements = $this->loadPending($tenantId, $userType, $userId, $role);

            if ($pendingAgreements->isEmpty()) {
                return $this->afterAcceptRedirect($tenantId, $userType);
            }

            return view('tenant.legal-agreements.accept', [
                'tenant'     => $tenant,
                'tenantId'   => $tenantId,
                'agreements' => $pendingAgreements,
                'userType'   => $userType,
                'userId'     => $userId,
                'role'       => $role,
            ]);
        } catch (\Throwable $e) {
            try {
                \Illuminate\Support\Facades\Log::error('showAccept failed', [
                    'tenant_id' => $tenantId,
                    'error'     => $e->getMessage(),
                    'trace'     => $e->getTraceAsString(),
                ]);
            } catch (\Throwable) {}
            // Absolute safe fallback — never show a 500
            return redirect()->to('/');
        }
    }

    // ── Web: Store batch acceptance and redirect ───────────────────────────────

    public function storeAccept(Request $request, string $tenantId)
    {
        $data = $request->validate([
            'agreement_ids'   => 'required|array|min:1',
            'agreement_ids.*' => 'required|string',
            'user_type'       => 'required|in:tenant_user,reseller,partner',
            'user_id'         => 'required|string',
            'user_role'       => 'nullable|string',
        ]);

        foreach ($data['agreement_ids'] as $agreementId) {
            $agreement = TenantLegalAgreement::where('id', $agreementId)
                ->where('tenant_id', $tenantId)
                ->first();
            if (!$agreement) continue;

            TenantLegalAgreementAcceptance::updateOrCreate(
                [
                    'tenant_legal_agreement_id' => $agreement->id,
                    'user_type'                 => $data['user_type'],
                    'user_id'                   => $data['user_id'],
                ],
                [
                    'tenant_id'   => $tenantId,
                    'user_role'   => $data['user_role'] ?? null,
                    'accepted_at' => now(),
                    'ip_address'  => $request->ip(),
                    'user_agent'  => substr($request->userAgent() ?? '', 0, 500),
                ]
            );
        }

        return $this->afterAcceptRedirect($tenantId, $data['user_type']);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function loadPending(string $tenantId, string $userType, string $userId, string $role)
    {
        // Fresh acceptances: accepted AFTER the agreement was last updated
        try {
            $freshAcceptedIds = DB::table('tenant_legal_agreement_acceptances as a')
                ->join('tenant_legal_agreements as ag', 'a.tenant_legal_agreement_id', '=', 'ag.id')
                ->where('a.tenant_id', $tenantId)
                ->where('a.user_type', $userType)
                ->where('a.user_id', $userId)
                ->whereRaw('a.accepted_at >= ag.updated_at')
                ->pluck('a.tenant_legal_agreement_id')
                ->toArray();
        } catch (\Throwable) {
            // Fallback: use all acceptances (no staleness check)
            $freshAcceptedIds = TenantLegalAgreementAcceptance::where('tenant_id', $tenantId)
                ->where('user_type', $userType)
                ->where('user_id', $userId)
                ->pluck('tenant_legal_agreement_id')
                ->toArray();
        }

        return TenantLegalAgreement::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where('is_required', true)
            ->when(!empty($freshAcceptedIds), fn ($q) => $q->whereNotIn('id', $freshAcceptedIds))
            ->orderBy('display_order')
            ->get()
            ->filter(fn ($a) => $a->appliesToRole($role))
            ->values();
    }

    private function resolveUserContext(string $tenantId): array
    {
        if ($user = Auth::guard('tenant')->user()) {
            $membership = DB::table('tenant_memberships')
                ->where('tenant_user_id', $user->id)
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->first();
            return ['tenant_user', (string) $user->id, $membership?->role ?? 'member'];
        }
        if ($reseller = Auth::guard('reseller')->user()) {
            return ['reseller', (string) $reseller->id, 'referrer'];
        }
        if ($partner = Auth::guard('partner')->user()) {
            return ['partner', (string) $partner->id, 'partner'];
        }
        return ['', '', ''];
    }

    private function afterAcceptRedirect(string $tenantId, string $userType)
    {
        $msg = 'Thank you for accepting the agreements. You may now continue.';

        // Return to where the user was trying to go before the middleware intercepted
        if ($intendedUrl = session()->pull('legal_agreements.intended_url')) {
            return redirect()->to($intendedUrl)->with('success', $msg);
        }

        if ($userType === 'reseller') {
            $reseller = Auth::guard('reseller')->user();
            return redirect()->route('reseller.dashboard', $reseller?->tenant_id ?? $tenantId)
                ->with('success', $msg);
        }

        if ($userType === 'partner') {
            return redirect()->route('partner.dashboard')->with('success', $msg);
        }

        return redirect()->route('tenant.dashboard', $tenantId)->with('success', $msg);
    }

    private function authorizeAdminAccess(?string $tenantId): void
    {
        if (!$tenantId) abort(422, 'tenant_id required');
        if ($userId = Auth::guard('tenant')->id()) {
            $isAdmin = DB::table('tenant_memberships')
                ->where('tenant_user_id', $userId)
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->whereIn('role', ['owner', 'admin'])
                ->exists();
            if (!$isAdmin) abort(403, 'Only Owners and Admins can manage legal agreements.');
        }
    }

    // ── Public static helper used by invitation + setup controllers ───────────

    public static function hasPending(string $tenantId, string $userType, string $userId, string $role): bool
    {
        try {
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
