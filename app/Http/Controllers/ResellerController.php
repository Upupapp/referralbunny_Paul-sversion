<?php

namespace App\Http\Controllers;

use App\Mail\ResellerInvitation;
use App\Models\ActivityLog;
use App\Models\Reseller;
use App\Services\DealReferrerAssignmentService;
use App\Services\NotificationDispatchService;
use App\Services\ReferrerInvitationDeduplicationService;
use App\Services\TenantRoleService;
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
        $query = Reseller::orderByRaw("CASE
                WHEN status IN ('active','nda_signed') THEN 0
                WHEN status = 'invited'               THEN 1
                ELSE 2
            END")
            ->orderBy('name', 'asc');

        // Always scope to the active tenant; super admins may override via tenant_id param
        $tenantId = TenantContext::id();
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        } elseif ($request->filled('tenant_id') && TenantContext::isSuperAdmin()) {
            $query->where('tenant_id', $request->tenant_id);
        } elseif (!TenantContext::isSuperAdmin()) {
            abort(403, 'Tenant context required.');
        }

        $isTenantAdmin = $this->callerIsTenantAdmin();
        $perPage       = min(200, max(1, (int) $request->get('per_page', 50)));
        $paginated     = $query->paginate($perPage);

        $resellers = $paginated->getCollection()->map(
            fn ($r) => $this->applyAnonymityMask($r, $isTenantAdmin)
        );

        return response()->json([
            'data'      => $resellers->values(),
            'total'     => $paginated->total(),
            'page'      => $paginated->currentPage(),
            'per_page'  => $paginated->perPage(),
            'last_page' => $paginated->lastPage(),
        ]);
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

        // ── Block inviting existing admins/managers (they are referrers by default) ──
        $tenantUser = \App\Models\TenantUser::whereRaw('lower(email) = ?', [$normalizedEmail])->first();
        if ($tenantUser) {
            $adminMembership = \App\Models\TenantMembership::where('tenant_id', $tenantId)
                ->where('tenant_user_id', $tenantUser->id)
                ->whereIn('role', ['owner', 'admin', 'manager'])
                ->where('status', 'active')
                ->first();
            if ($adminMembership) {
                return response()->json([
                    'message'    => ucfirst($adminMembership->role) . 's are automatically Referrers in this workspace — no separate invite needed.',
                    'error_code' => 'already_team_admin',
                    'role'       => $adminMembership->role,
                ], 409);
            }
        }

        // ── Explicit duplicate check ──────────────────────────────────────
        $existing = Reseller::where('tenant_id', $tenantId)
            ->whereRaw('LOWER(email) = ?', [$normalizedEmail])
            ->first();

        if ($existing) {
            if ($existing->status === 'deactivated') {
                // Reinvite: reset to fresh invited state and resend invitation email.
                $setupToken = Str::random(64);
                $existing->update([
                    'name'                 => $data['name'],
                    'phone'                => $data['phone'] ?? $existing->phone,
                    'territory'            => $data['territory'] ?? $existing->territory,
                    'setup_token'          => $setupToken,
                    'status'               => 'invited',
                    'password'             => null,
                    'joined_date'          => null,
                    'linked_tenant_user_id'=> Auth::guard('tenant')->id(),
                ]);
                $existing = $existing->fresh();

                $tenantName = DB::table('tenants')->where('id', $tenantId)->value('name') ?? 'Referral Bunny';
                $setupUrl   = url('/reseller/setup?token=' . $setupToken);
                $emailStatus = 'sent';
                try {
                    Mail::send(new ResellerInvitation(
                        resellerName:  $existing->name,
                        resellerEmail: $existing->email,
                        tenantName:    $tenantName,
                        setupUrl:      $setupUrl,
                    ));
                } catch (\Throwable $e) {
                    $emailStatus = 'failed';
                    Log::warning("Reseller reinvite email failed for {$existing->email}: {$e->getMessage()}");
                }

                $response = $existing->toArray();
                $response['email_delivery_status'] = $emailStatus;
                $response['reinvited'] = true;
                return response()->json($response, 201);
            }

            if ($existing->status === 'invited') {
                return response()->json([
                    'message'     => 'A pending invitation already exists for this email. You can resend it from the Referrers list.',
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

        // Capture the authenticated tenant user as the inviter so they get
        // notified when the referrer completes setup (InviteAcceptedEvent).
        $actingUser = \Illuminate\Support\Facades\Auth::guard('tenant')->user();
        if ($actingUser) {
            $data['linked_tenant_user_id'] = $actingUser->id;
        }

        $reseller   = Reseller::create($data);
        $tenantName = DB::table('tenants')->where('id', $tenantId)->value('name') ?? 'Referral Bunny';
        $setupUrl   = url('/reseller/setup?token=' . $setupToken);

        // ── Send invitation email (non-blocking) ──────────────────────────
        $emailStatus = \App\Services\EmailLogger::send(
            mailable:       new ResellerInvitation(
                resellerName:  $data['name'],
                resellerEmail: $normalizedEmail,
                tenantName:    $tenantName,
                setupUrl:      $setupUrl,
            ),
            recipientEmail: $normalizedEmail,
            recipientType:  'reseller',
            emailKey:       "reseller_invite.{$reseller->id}",
            subject:        "You've been invited as a Referrer for {$tenantName}",
            tenantId:       $tenantId,
        ) ? 'sent' : 'failed';

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

        // ── Notify tenant admins ───────────────────────────────────────────
        try {
            $needsReview = $activeDealsCount > 0;
            app(NotificationDispatchService::class)->dispatchToTenantAdmins(
                tenantId:     $tenantId,
                category:     'reseller_referrer',
                priority:     $needsReview ? 'high' : 'normal',
                title:        'Referrer deactivated',
                body:         "{$reseller->name} has been deactivated."
                              . ($needsReview ? " {$activeDealsCount} active deal(s) may need reassignment." : ''),
                actionUrl:    "/tenant/{$tenantId}/referrers",
                actionLabel:  'View Referrers',
                dedupeSuffix: "deactivate:{$reseller->id}",
            );
        } catch (\Throwable) {}

        return response()->json([
            'success'            => true,
            'message'            => 'Referrer deactivated. Access to this tenant has been removed.',
            'active_deals_count' => $activeDealsCount,
            'needs_review'       => $activeDealsCount > 0,
        ]);
    }

    public function sendInvite(Request $request, Reseller $reseller): JsonResponse
    {
        if (!$this->callerIsTenantAdmin()) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        $tenantId = TenantContext::id() ?? $request->input('tenant_id');
        if (!$tenantId || $reseller->tenant_id !== $tenantId) {
            return response()->json(['error' => 'Referrer not found in this tenant.'], 404);
        }

        if ($reseller->status !== 'invited') {
            return response()->json(['message' => 'Referrer is already active and does not need an invite.'], 422);
        }

        // Generate a setup token if one doesn't exist yet (import-created resellers have none)
        $setupToken = $reseller->setup_token ?: Str::random(64);
        if (!$reseller->setup_token) {
            $reseller->update(['setup_token' => $setupToken]);
        }

        $tenantName = DB::table('tenants')->where('id', $tenantId)->value('name') ?? 'Referral Bunny';
        $setupUrl   = url('/reseller/setup?token=' . $setupToken);

        try {
            Mail::send(new ResellerInvitation(
                resellerName:  $reseller->name,
                resellerEmail: $reseller->email,
                tenantName:    $tenantName,
                setupUrl:      $setupUrl,
            ));
            return response()->json(['success' => true, 'message' => 'Invitation sent to ' . $reseller->email . '.']);
        } catch (\Throwable $e) {
            Log::warning("sendInvite failed for {$reseller->email}: {$e->getMessage()}");
            return response()->json(['message' => 'Referrer record is ready but email delivery failed. Check mail configuration.'], 500);
        }
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
     *
     * Returns activated Referrers for deal assignment dropdown.
     * Includes Admin/Manager users who ALSO hold Referrer role (linked reseller record).
     * Excludes Admins/Managers who do NOT have Referrer role.
     * Tenant-scoped. Never crosses tenant boundaries.
     */
    public function activatedOptions(Request $request): JsonResponse
    {
        $tenantId = TenantContext::id() ?? $request->query('tenant_id');
        if (!$tenantId) {
            return response()->json([]);
        }

        $search    = $request->filled('search') ? $request->query('search') : null;
        $referrers = app(DealReferrerAssignmentService::class)
            ->getActivatedReferrersForTenant($tenantId, $search);

        return response()->json($referrers->values());
    }

    /**
     * GET /api/resellers/check-email
     *
     * Classify a referrer email before assignment:
     * active_referrer | pending_referrer | active_admin_manager | new_referrer | invalid_email
     *
     * Used by deal creation form to show the correct prompt when a manual
     * email matches an existing Admin/Manager without Referrer role.
     */
    public function checkEmail(Request $request): JsonResponse
    {
        $tenantId = TenantContext::id() ?? $request->query('tenant_id');
        $email    = $request->query('email', '');

        if (!$tenantId || !$email) {
            return response()->json(['status' => 'invalid_email', 'existing_roles' => []]);
        }

        $result = app(DealReferrerAssignmentService::class)
            ->classifyReferrerEmail($tenantId, $email);

        return response()->json($result);
    }

    /**
     * POST /api/resellers/add-referrer-role/{tenantUserId}
     *
     * Add Referrer role to an existing Tenant Admin or Tenant Manager.
     * Creates a Reseller record linked to their TenantUser account.
     * Status is set to 'active' immediately (no account setup invite needed).
     * Sends role-added in-app notification instead of setup email.
     * Does NOT remove their existing Admin/Manager role.
     */
    public function addReferrerRole(Request $request, string $tenantUserId): JsonResponse
    {
        $tenantId = TenantContext::id() ?? $request->input('tenant_id');
        if (!$tenantId) {
            return response()->json(['error' => 'No tenant context.'], 403);
        }

        if (!$this->callerIsTenantAdmin()) {
            return response()->json(['error' => 'Only Tenant Admins can add Referrer role.'], 403);
        }

        $data = $request->validate([
            'deal_id' => 'nullable|string',
        ]);

        [$actorId] = $this->resolveActor();

        try {
            $result = app(TenantRoleService::class)->addReferrerRoleToTenantUser(
                tenantId:     $tenantId,
                tenantUserId: $tenantUserId,
                actorId:      $actorId,
                actorRole:    'admin',
                dealId:       $data['deal_id'] ?? null,
            );

            return response()->json([
                'success'         => true,
                'reseller'        => $result['reseller'],
                'created'         => $result['created'],
                'already_referrer'=> $result['already_referrer'],
                'message'         => $result['already_referrer']
                    ? 'This user already has Referrer role in this tenant.'
                    : 'Referrer role added successfully. No account setup email sent — user already has access.',
            ], $result['created'] ? 201 : 200);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        } catch (\Throwable $e) {
            Log::error('addReferrerRole failed: ' . $e->getMessage(), [
                'tenant_user_id' => $tenantUserId,
                'tenant_id'      => $tenantId,
            ]);
            return response()->json(['error' => 'Could not add Referrer role.'], 500);
        }
    }

    private function resolveActor(): array
    {
        $user = Auth::guard('tenant')->user() ?? Auth::guard('web')->user();
        return [$user?->id ?? 'unknown'];
    }

    public function summary(Request $request): JsonResponse
    {
        $tenantId = TenantContext::id() ?? $request->query('tenant_id');
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
