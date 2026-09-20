<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\NotificationDispatchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class TenantSignupWebController extends Controller
{
    public function showBuild()
    {
        // Redirect to dashboard if already logged in
        if (Auth::guard('tenant')->check()) {
            $tenantId = DB::table('tenant_memberships')
                ->where('tenant_user_id', Auth::guard('tenant')->id())
                ->where('status', 'active')
                ->value('tenant_id');
            if ($tenantId) return redirect()->route('tenant.dashboard', $tenantId);
        }

        return view('auth.build-program');
    }

    public function build(Request $request)
    {
        $data = $request->validate([
            'first_name'         => 'required|string|max:100',
            'last_name'          => 'required|string|max:100',
            'email'              => 'required|email|unique:tenant_users,email',
            'password'           => ['required', 'confirmed', Password::min(8)],
            'workspace_name'     => 'required|string|max:255',
            'industry'           => 'nullable|string|max:100',
            'sub_industries'     => 'nullable|array|max:3',
            'sub_industries.*'   => 'string|max:100',
            'country'            => 'nullable|string|max:100',
            'timezone'           => 'nullable|string|max:100',
            'preferred_currency' => 'nullable|string|max:10',
            'lead_label'         => 'nullable|string|max:50',
            'value_label'        => 'nullable|string|max:50',
            'commission_type'    => 'nullable|string|in:percentage_of_value,fixed_amount,placement_fee',
            'company_share_pct'  => 'nullable|numeric|min:0|max:100',
            'referrer_share_pct' => 'nullable|numeric|min:0|max:100',
            'pipeline_stages'    => 'nullable|array|max:10',
            'pipeline_stages.*.key'   => 'required_with:pipeline_stages|string|max:50',
            'pipeline_stages.*.name'  => 'required_with:pipeline_stages|string|max:100',
            'pipeline_stages.*.days'  => 'nullable|integer|min:1|max:365',
            'pipeline_stages.*.color' => 'nullable|string|max:20',
            'pipeline_stages.*.is_final' => 'nullable|boolean',
            'pipeline_stages.*.is_won' => 'nullable|boolean',
            'terms'              => 'accepted',
        ]);

        // Company signup needs only account details. Program settings can be edited later.
        $data['industry'] = $data['industry'] ?? 'Other';
        $data['country'] = $data['country'] ?? 'Philippines';
        $data['timezone'] = $data['timezone'] ?? 'Asia/Manila';
        $data['preferred_currency'] = $data['preferred_currency'] ?? 'PHP';
        $data['pipeline_stages'] = $data['pipeline_stages'] ?? [
            ['key' => 'new', 'name' => 'New', 'days' => 7, 'color' => '#9CA3AF'],
            ['key' => 'in-progress', 'name' => 'In Progress', 'days' => 14, 'color' => '#3B82F6'],
            ['key' => 'closed-won', 'name' => 'Closed Won', 'color' => '#10B981', 'is_final' => true, 'is_won' => true],
            ['key' => 'closed-lost', 'name' => 'Closed Lost', 'color' => '#EF4444', 'is_final' => true],
        ];

        // Generate a URL-safe tenant ID from the workspace name
        $base     = Str::slug($data['workspace_name']);
        $tenantId = $base . '-' . Str::lower(Str::random(6));

        // Ensure uniqueness
        while (DB::table('tenants')->where('id', $tenantId)->exists()) {
            $tenantId = $base . '-' . Str::lower(Str::random(6));
        }

        $userId = (string) Str::uuid();
        $signedInReferrer = Auth::guard('reseller')->user();

        DB::transaction(function () use ($data, $tenantId, $userId, $signedInReferrer) {
            $now = now();

            DB::table('tenant_users')->insert([
                'id'         => $userId,
                'first_name' => $data['first_name'],
                'last_name'  => $data['last_name'],
                'email'      => $data['email'],
                'password'   => Hash::make($data['password']),
                'status'     => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // Creating a company from My Programs links the already authenticated identity.
            if ($signedInReferrer && !$signedInReferrer->linked_tenant_user_id
                && strtolower($signedInReferrer->email) === strtolower($data['email'])
                && in_array($signedInReferrer->status, ['active', 'nda_signed'])) {
                DB::table('resellers')->where('id', $signedInReferrer->id)
                    ->whereNull('linked_tenant_user_id')->update(['linked_tenant_user_id' => $userId]);
            }

            DB::table('tenants')->insert([
                'id'                 => $tenantId,
                'name'               => $data['workspace_name'],
                'program_name'       => $data['workspace_name'],
                'industry'           => $data['industry'],
                'country'            => $data['country'],
                'timezone'           => $data['timezone'],
                'preferred_currency' => $data['preferred_currency'],
                'status'             => 'active',
                'created_at'         => $now,
                'updated_at'         => $now,
            ]);

            DB::table('tenant_memberships')->insert([
                'id'             => (string) Str::uuid(),
                'tenant_id'      => $tenantId,
                'tenant_user_id' => $userId,
                'role'           => 'owner',
                'status'         => 'active',
                'created_at'     => $now,
                'updated_at'     => $now,
            ]);

            DB::table('tenant_program_configs')->insert([
                'tenant_id'           => $tenantId,
                'industry'            => $data['industry'],
                'sub_industries'      => json_encode($data['sub_industries'] ?? []),
                'lead_label'          => $data['lead_label'] ?? 'Deal',
                'value_label'         => $data['value_label'] ?? 'Deal Value',
                'commission_type'     => $data['commission_type'] ?? 'percentage_of_value',
                'company_share_pct'   => $data['company_share_pct'] ?? 30,
                'referrer_share_pct'  => $data['referrer_share_pct'] ?? 70,
                'default_expiry_days' => 21,
                'reassignment_mode'   => 'manual',
                'onboarding_complete' => true,
                'template_applied'    => $data['industry'],
                'created_at'          => $now,
                'updated_at'          => $now,
            ]);

            $usedKeys = [];
            foreach ($data['pipeline_stages'] ?? [] as $pos => $stage) {
                $baseKey = Str::slug($stage['key'] ?? $stage['name']) ?: 'stage';
                $key = $baseKey;
                $counter = 2;
                while (in_array($key, $usedKeys, true)) {
                    $key = "{$baseKey}-{$counter}";
                    $counter++;
                }
                $usedKeys[] = $key;
                DB::table('tenant_pipeline_stages')->insert([
                    'id'        => (string) Str::uuid(),
                    'tenant_id' => $tenantId,
                    'stage_key' => $key,
                    'name'      => $stage['name'],
                    'position'  => $pos,
                    'days_limit'=> isset($stage['days']) ? (int) $stage['days'] : null,
                    'color'     => $stage['color'] ?? '#9CA3AF',
                    'is_final'  => (bool) ($stage['is_final'] ?? false),
                    'is_won'    => (bool) ($stage['is_won'] ?? false),
                    'created_at'=> $now,
                    'updated_at'=> $now,
                ]);
            }
        });

        // Log in as tenant user — regenerate session to prevent fixation
        foreach (['web', 'reseller', 'partner'] as $guard) Auth::guard($guard)->logout();
        Auth::guard('tenant')->loginUsingId($userId, false);
        request()->session()->regenerate();

        // Notify super admins of the new tenant signup
        try {
            app(NotificationDispatchService::class)->dispatchToSuperAdmins(
                category:     'system',
                priority:     'normal',
                title:        "New workspace created: {$data['workspace_name']}",
                body:         "{$data['first_name']} {$data['last_name']} ({$data['email']}) just created a new workspace.",
                actionUrl:    url("/platform/tenants/{$tenantId}"),
                actionLabel:  'View Tenant',
                dedupeSuffix: $tenantId,
                metadata:     [
                    'tenant_id'    => $tenantId,
                    'tenant_name'  => $data['workspace_name'],
                    'owner_name'   => "{$data['first_name']} {$data['last_name']}",
                    'owner_email'  => $data['email'],
                    'industry'     => $data['industry'],
                    'country'      => $data['country'],
                ],
            );
        } catch (\Throwable) {}

        // Support API clients and redirect the signup form straight to the dashboard.
        if (request()->expectsJson()) {
            return response()->json([
                'success'  => true,
                'redirect' => route('tenant.dashboard', $tenantId),
                'message'  => "Welcome! Your workspace \"{$data['workspace_name']}\" is ready.",
            ]);
        }

        return redirect()->route('tenant.dashboard', $tenantId)
            ->with('success', "Welcome! Your workspace \"{$data['workspace_name']}\" is ready.");
    }

    public function showJoin()
    {
        return view('auth.join-program');
    }

    /** Handle invite token web redirect (for links like /tenant/invite/{token}) */
    public function showInvite(string $token)
    {
        // Check reseller setup token first
        $reseller = DB::table('resellers')->where('setup_token', $token)->first();
        if ($reseller) {
            return redirect()->route('reseller.setup', ['token' => $token]);
        }

        // Check tenant invitation token — delegate to the canonical invite acceptance flow
        $invite = DB::table('tenant_invitations')->where('token', $token)->first();

        if (!$invite) {
            return redirect()->route('tenant.login')
                ->withErrors(['email' => 'This invite link is invalid or does not exist.']);
        }

        if ($invite->status === 'accepted') {
            return redirect()->route('tenant.login')
                ->with('success', 'This invitation has already been accepted. Please sign in.');
        }

        if ($invite->status === 'revoked' || ($invite->expires_at && now()->gt($invite->expires_at))) {
            // Reuse the canonical expired-invite view (token is just used for display context)
            return view('auth.tenant-invite-expired', ['invitation' => null]);
        }

        // Valid invite — hand off to the canonical acceptance route
        return redirect()->route('tenant.accept-invite.show', $token);
    }
}
