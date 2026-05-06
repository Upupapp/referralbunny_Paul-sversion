<?php

namespace App\Http\Controllers;

use App\Mail\ResellerInvitation;
use App\Models\ActivityLog;
use App\Models\Reseller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Services\TenantContext;
use Illuminate\Validation\Rule;

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
        $tenantId = $request->input('tenant_id');

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

        $normalizedEmail = strtolower(trim($data['email']));

        // ── Explicit duplicate check (before anything is created) ─────────
        $existing = Reseller::where('tenant_id', $tenantId)
            ->whereRaw('LOWER(email) = ?', [$normalizedEmail])
            ->first();

        if ($existing) {
            if ($existing->status === 'deactivated') {
                return response()->json([
                    'message'     => 'This email belongs to a deactivated Referrer. Reactivate them instead.',
                    'error_code'  => 'referrer_deactivated',
                    'reseller_id' => $existing->id,
                ], 409);
            }
            if ($existing->status === 'invited') {
                return response()->json([
                    'message'     => 'A pending invitation already exists for this email address. You can resend it from the Referrers list.',
                    'error_code'  => 'pending_invite_exists',
                    'reseller_id' => $existing->id,
                    'can_resend'  => true,
                ], 409);
            }
            return response()->json([
                'message'     => 'This email is already an active Referrer for this tenant.',
                'error_code'  => 'referrer_already_active',
                'reseller_id' => $existing->id,
            ], 409);
        }

        // ── Plan limit check ─────────────────────────────────────────────
        $planService = app(\App\Services\TenantPlanService::class);
        $limitCheck  = $planService->canInviteReferrer($tenantId);
        if (!$limitCheck['allowed']) {
            return response()->json([
                'message'        => $limitCheck['reason'],
                'error_code'     => 'plan_limit_exceeded',
                'current'        => $limitCheck['current'],
                'max'            => $limitCheck['max'],
                'upgrade_prompt' => true,
            ], 422);
        }

        // ── Create the reseller record ────────────────────────────────────
        $setupToken      = Str::random(64);
        $data['email']   = $normalizedEmail;
        $data['setup_token'] = $setupToken;
        $data['status']      = 'invited';

        $reseller   = Reseller::create($data);
        $tenantName = DB::table('tenants')->where('id', $tenantId)->value('name') ?? 'Referral Bunny';
        $setupUrl   = url('/reseller/setup?token=' . $setupToken);

        // ── Send invitation email (non-blocking) ──────────────────────────
        $emailStatus = 'sent';
        try {
            Mail::send(new ResellerInvitation(
                resellerName:  $data['name'],
                resellerEmail: $normalizedEmail,
                tenantName:    $tenantName,
                setupUrl:      $setupUrl,
            ));
        } catch (\Throwable $e) {
            $emailStatus = 'failed';
            Log::warning("Reseller invite email failed for {$normalizedEmail}: {$e->getMessage()}", [
                'reseller_id' => $reseller->id,
                'tenant_id'   => $tenantId,
            ]);
        }

        $response = $reseller->toArray();
        $response['email_delivery_status'] = $emailStatus;

        if ($emailStatus === 'failed') {
            $response['message'] = 'Referrer invitation created, but email delivery failed. You can resend it from the Referrers list.';
        }

        return response()->json($response, 201);
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
        // Hard delete is intentionally blocked — use the deactivate endpoint instead.
        // This preserves historical deals, commissions, messages, and audit logs.
        return response()->json([
            'message'    => 'Direct deletion is not allowed. Use the deactivate endpoint to remove Referrer access.',
            'error_code' => 'use_deactivate_endpoint',
        ], 405);
    }

    /**
     * Deactivate a Referrer (requires double authentication).
     * Soft-deactivates: sets status = deactivated, preserves all history.
     */
    public function deactivate(Request $request, Reseller $reseller): JsonResponse
    {
        if (!$this->callerIsTenantAdmin()) {
            return response()->json(['error' => 'You do not have permission to deactivate Referrers.'], 403);
        }

        // Resolve tenant from auth context
        $tenantId = TenantContext::id() ?? $request->input('tenant_id');
        if (!$tenantId || $reseller->tenant_id !== $tenantId) {
            return response()->json(['error' => 'Referrer not found in this tenant.'], 404);
        }

        if ($reseller->status === 'deactivated') {
            return response()->json(['error' => 'This Referrer is already deactivated.'], 422);
        }

        $data = $request->validate([
            'confirmation' => ['required', 'string', Rule::in(['DEACTIVATE REFERRER'])],
            'reason'       => 'required|string|min:5|max:500',
            'password'     => 'required|string',
        ]);

        // ── Verify caller password (double authentication) ────────────────
        $actor = Auth::guard('tenant')->user() ?? Auth::guard('web')->user();
        if (!$actor) {
            return response()->json(['error' => 'Authentication required.'], 401);
        }

        if (!Hash::check($data['password'], $actor->password)) {
            // Log failed double-auth attempt
            $this->auditDeactivation($tenantId, $reseller, $actor->id ?? 'unknown', 'double_auth_failed', $data['reason']);
            return response()->json(['error' => 'Double authentication failed. No changes were made.'], 401);
        }

        // ── Count active deals for critical action metadata ────────────────
        $activeDealsCount   = DB::table('leads')->where('tenant_id', $tenantId)->where('reseller_name', $reseller->name)->whereIn('status', ['active', 'expiring'])->count();
        $pendingDealsCount  = DB::table('leads')->where('tenant_id', $tenantId)->where('reseller_name', $reseller->name)->where('status', 'pending')->count();
        $totalDealsCount    = DB::table('leads')->where('tenant_id', $tenantId)->where('reseller_name', $reseller->name)->count();

        // ── Deactivate ─────────────────────────────────────────────────────
        try {
            DB::table('resellers')->where('id', $reseller->id)->update([
                'status'     => 'deactivated',
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Reseller deactivation DB update failed', [
                'reseller_id' => $reseller->id,
                'tenant_id'   => $tenantId,
                'error'       => $e->getMessage(),
            ]);
            return response()->json([
                'error' => 'Deactivation failed: ' . $e->getMessage(),
            ], 500);
        }

        // ── Audit log ──────────────────────────────────────────────────────
        $this->auditDeactivation($tenantId, $reseller, $actor->id ?? 'unknown', 'deactivated', $data['reason'], [
            'active_deals_count'  => $activeDealsCount,
            'pending_deals_count' => $pendingDealsCount,
            'total_deals_count'   => $totalDealsCount,
            'double_auth_verified'=> true,
        ]);

        return response()->json([
            'success'            => true,
            'message'            => 'Referrer deactivated. Access to this tenant has been removed.',
            'active_deals_count' => $activeDealsCount,
            'needs_review'       => $activeDealsCount > 0,
        ]);
    }

    private function auditDeactivation(string $tenantId, Reseller $reseller, string $actorId, string $event, string $reason, array $extra = []): void
    {
        try {
            ActivityLog::create([
                'id'        => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'user_id'   => $actorId,
                'action'    => 'referrer_' . $event,
                'entity'    => 'reseller',
                'entity_id' => $reseller->id,
                'metadata'  => json_encode(array_merge([
                    'referrer_name'  => $reseller->name,
                    'referrer_email' => $reseller->email,
                    'reason'         => $reason,
                    'timestamp'      => now()->toIso8601String(),
                ], $extra)),
            ]);
        } catch (\Throwable) {
            // Never crash on audit failure
        }
    }

    /**
     * GET /api/resellers/activated-options
     * Returns only activated (active/nda_signed) resellers for deal assignment.
     */
    public function activatedOptions(Request $request): JsonResponse
    {
        $tenantId = TenantContext::id();
        if (!$tenantId) {
            return response()->json([]);
        }

        $query = Reseller::where('tenant_id', $tenantId)
            ->whereIn('status', ['active', 'nda_signed'])
            ->whereNotNull('email')
            ->orderBy('name');

        if ($request->filled('search')) {
            $q = '%' . strtolower($request->search) . '%';
            $query->where(function ($qb) use ($q) {
                $qb->whereRaw('LOWER(name) LIKE ?', [$q])
                   ->orWhereRaw('LOWER(email) LIKE ?', [$q]);
            });
        }

        $resellers = $query->get()->map(fn($r) => [
            'id'           => $r->id,
            'name'         => $r->name,
            'email'        => $r->email,
            'status'       => $r->status,
            'display_name' => $r->name . ' — ' . $r->email,
        ]);

        return response()->json($resellers);
    }

    public function summary(Request $request): JsonResponse
    {
        $tenantId = $request->query('tenant_id');
        if (!$tenantId) {
            return response()->json(['error' => 'tenant_id required'], 400);
        }

        $counts = [
            'total'       => Reseller::where('tenant_id', $tenantId)->count(),
            'active'      => Reseller::where('tenant_id', $tenantId)->whereIn('status', ['active', 'nda_signed'])->count(),
            'invited'     => Reseller::where('tenant_id', $tenantId)->where('status', 'invited')->count(),
            'no_email'    => Reseller::where('tenant_id', $tenantId)->whereNull('email')->count(),
            'no_password' => Reseller::where('tenant_id', $tenantId)->whereNull('password')->count(),
        ];

        return response()->json($counts);
    }
}
