<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\TenantAccessRequest;
use App\Models\TenantMembership;
use App\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TenantAuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $user = TenantUser::whereRaw('lower(email) = ?', [strtolower($request->email)])->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if ($user->status !== 'active') {
            return response()->json([
                'message' => 'Your account access is currently suspended. Please contact your tenant admin.',
                'code'    => 'account_suspended',
            ], 403);
        }

        $memberships = $this->buildMembershipsPayload($user);
        $token       = $user->createToken('tenant-api-token')->plainTextToken;

        return response()->json([
            'user'        => $this->userPayload($user),
            'token'       => $token,
            'memberships' => $memberships,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'user'        => $this->userPayload($user),
            'memberships' => $this->buildMembershipsPayload($user),
        ]);
    }

    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'first_name'         => 'required|string|max:100',
            'last_name'          => 'required|string|max:100',
            'email'              => 'required|email|max:255',
            'password'           => 'required|string|min:8|confirmed',
            'tenant_name'        => 'required|string|max:255',
            'industry'           => 'required|string|max:255',
            'country'            => 'required|string|max:100',
            'timezone'           => 'required|string|max:100',
            'preferred_currency' => 'required|string|max:3',
            'website'            => 'nullable|url|max:255',
            'sub_industries'     => 'nullable|array|max:3',
        ]);

        $email = strtolower($request->email);

        if (TenantUser::whereRaw('lower(email) = ?', [$email])->exists()) {
            throw ValidationException::withMessages([
                'email' => ['This email address is already registered.'],
            ]);
        }

        $slug     = Str::slug($request->tenant_name);
        $tenantId = $slug . '-' . Str::random(6);

        [$tenant, $user, $membership] = DB::transaction(function () use ($request, $tenantId, $email) {
            $t = Tenant::create([
                'id'                 => $tenantId,
                'name'               => $request->tenant_name,
                'slug'               => $tenantId,
                'program_name'       => $request->tenant_name . ' Referral Program',
                'industry'           => $request->industry,
                'status'             => 'trial',
                'country'            => $request->country,
                'timezone'           => $request->timezone,
                'preferred_currency' => $request->preferred_currency,
                'website'            => $request->website,
                'admin_name'         => $request->first_name . ' ' . $request->last_name,
                'admin_email'        => $email,
            ]);

            $u = TenantUser::create([
                'first_name' => $request->first_name,
                'last_name'  => $request->last_name,
                'email'      => $email,
                'password'   => Hash::make($request->password),
                'status'     => 'active',
            ]);

            $m = TenantMembership::create([
                'tenant_id'                 => $t->id,
                'tenant_user_id'            => $u->id,
                'role'                      => 'owner',
                'status'                    => 'active',
                'joined_by_invitation'      => false,
                'password_review_completed' => true,
                'setup_completed'           => false,
            ]);

            return [$t, $u, $m];
        });

        $token = $user->createToken('tenant-api-token')->plainTextToken;

        return response()->json([
            'user'        => $this->userPayload($user),
            'token'       => $token,
            'memberships' => [[
                'id'                        => $membership->id,
                'tenant_id'                 => $tenant->id,
                'tenant_name'               => $tenant->name,
                'tenant_slug'               => $tenant->slug,
                'role'                      => 'owner',
                'status'                    => 'active',
                'setup_completed'           => false,
                'joined_by_invitation'      => false,
                'password_review_completed' => true,
            ]],
        ], 201);
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'current_password' => 'required|string',
            'password'         => 'required|string|min:8|confirmed',
        ]);

        if (! Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Current password is incorrect.'],
            ]);
        }

        $user->update(['password' => Hash::make($request->password)]);
        $user->memberships()->update(['password_review_completed' => true]);

        return response()->json(['message' => 'Password updated successfully.']);
    }

    public function markPasswordReviewed(Request $request): JsonResponse
    {
        $user     = $request->user();
        $tenantId = $request->input('tenant_id');

        $query = $user->memberships();
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }
        $query->update(['password_review_completed' => true]);

        return response()->json(['message' => 'Password review complete.']);
    }

    public function joinRequest(Request $request): JsonResponse
    {
        $request->validate([
            'first_name'     => 'required|string|max:100',
            'last_name'      => 'required|string|max:100',
            'email'          => 'required|email|max:255',
            'tenant_id'      => 'nullable|string',
            'requested_role' => 'nullable|in:admin,member,viewer',
            'reason'         => 'nullable|string|max:500',
        ]);

        $tenant = Tenant::where('id', $request->tenant_id)
            ->orWhere('slug', $request->tenant_id)
            ->first();

        if (! $tenant) {
            return response()->json(['message' => 'Tenant workspace not found.'], 404);
        }

        TenantAccessRequest::create([
            'tenant_id'      => $tenant->id,
            'email'          => strtolower($request->email),
            'first_name'     => $request->first_name,
            'last_name'      => $request->last_name,
            'requested_role' => $request->requested_role ?? 'member',
            'reason'         => $request->reason,
            'status'         => 'pending',
        ]);

        return response()->json([
            'message'     => 'Access request sent successfully.',
            'tenant_name' => $tenant->name,
        ], 201);
    }

    public function lookupTenant(Request $request): JsonResponse
    {
        $request->validate(['query' => 'required|string|min:2|max:100']);

        $tenants = Tenant::where('status', 'active')
            ->where(function ($q) use ($request) {
                $term = $request->query('query');
                $q->whereRaw('lower(name) like ?', ['%' . strtolower($term) . '%'])
                  ->orWhereRaw('lower(slug) like ?', ['%' . strtolower($term) . '%']);
            })
            ->select(['id', 'name', 'slug', 'industry'])
            ->limit(5)
            ->get();

        return response()->json(['tenants' => $tenants]);
    }

    // ── Helpers ───────────────────────────────────────────────

    private function userPayload(TenantUser $user): array
    {
        return [
            'id'         => $user->id,
            'first_name' => $user->first_name,
            'last_name'  => $user->last_name,
            'email'      => $user->email,
            'status'     => $user->status,
        ];
    }

    private function buildMembershipsPayload(TenantUser $user): array
    {
        return $user->memberships()
            ->with('tenant')
            ->where('status', 'active')
            ->get()
            ->map(fn ($m) => [
                'id'                        => $m->id,
                'tenant_id'                 => $m->tenant_id,
                'tenant_name'               => $m->tenant?->name,
                'tenant_slug'               => $m->tenant?->slug,
                'role'                      => $m->role,
                'status'                    => $m->status,
                'setup_completed'           => $m->setup_completed,
                'joined_by_invitation'      => $m->joined_by_invitation,
                'password_review_completed' => $m->password_review_completed,
                'last_accessed_at'          => $m->last_accessed_at,
            ])
            ->values()
            ->all();
    }
}
