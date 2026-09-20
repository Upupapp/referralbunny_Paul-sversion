<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\PartnerProgramMembership;
use App\Models\Program;
use App\Models\ReferrerProgramMembership;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\TenantUser;
use App\Services\PermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Serves the program workspace shell and each workspace tab via AJAX or
 * dedicated routes. All tab content is lazy-loaded; this controller renders
 * the shell and the overview tab.
 *
 * Tabs: overview | offers | members | contracts | analytics |
 *       action-items | settings | notifications | intake |
 *       public-page | access
 */
class ProgramWorkspaceController extends Controller
{
    /** Tabs with live content. Others exist in the view as placeholders but resolve to overview. */
    private const IMPLEMENTED_TABS = ['overview', 'members', 'settings', 'offers', 'contracts', 'action-items', 'analytics', 'access', 'notifications', 'public-page', 'intake'];

    private const VALID_TABS = [
        'overview', 'offers', 'members', 'contracts', 'analytics',
        'action-items', 'settings', 'notifications', 'intake',
        'public-page', 'access',
    ];

    public function show(Request $request, string $tenantId, string $programId)
    {
        abort_unless(config('programs.enabled'), 404);

        $tenant  = Tenant::findOrFail($tenantId);
        $program = Program::forTenant($tenantId)->findOrFail($programId);

        $this->authorize('view', $program);
        if (!\App\Support\ProtectedTenants::isProtected($tenantId) && $program->status !== 'archived') {
            $request->session()->put('program_dashboard.'.$tenantId, $program->id);
        }

        $activeTab = $this->resolveTab($request->query('tab'));

        $referrerMemberships = $partnerMemberships = $offers = $tenantResellers = $tenantPartners = null;

        if ($activeTab === 'members') {
            $referrerMemberships = $program->referrerMemberships()
                ->with(['reseller:id,name,email', 'group:id,name'])
                ->latest('joined_at')
                ->paginate(20, ['*'], 'referrers');

            $partnerMemberships = $program->partnerMemberships()
                ->with(['partner:id,first_name,last_name,email', 'group:id,name'])
                ->latest('joined_at')
                ->paginate(20, ['*'], 'partners');

            $tenantResellers = \App\Models\Reseller::where('tenant_id', $tenantId)
                ->orderBy('name')->get(['id', 'name', 'email']);
            $tenantPartners = \App\Models\Partner::where('tenant_id', $tenantId)
                ->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'email']);
        }

        if ($activeTab === 'offers') {
            $offers = $program->offers()
                ->with('currentVersion')
                ->latest()
                ->paginate(20, ['*'], 'offers');
        }

        $contracts = $actionItems = $referrerMembershipsById = $partnerMembershipsById = null;
        $programReferrerMemberships = $programPartnerMemberships = null;

        if ($activeTab === 'contracts' || $activeTab === 'action-items') {
            $programReferrerMemberships = $program->referrerMemberships()
                ->whereIn('status', ['active', 'approved'])
                ->with('reseller:id,name,email')
                ->get();
            $programPartnerMemberships = $program->partnerMemberships()
                ->whereIn('status', ['active', 'approved'])
                ->with('partner:id,first_name,last_name,email')
                ->get();
        }

        $offerOptions = null;
        if ($activeTab === 'contracts') {
            // Full (unpaginated) list for the propose-contract offer picker --
            // distinct from $offers, which is paginated for the Offers tab itself.
            $offerOptions = $program->offers()->with('currentVersion')->latest()->get();

            $contracts = $program->contracts()
                ->with('offerVersion.offer')
                ->latest('proposed_at')
                ->paginate(20, ['*'], 'contracts');

            [$referrerMembershipsById, $partnerMembershipsById] = $this->batchLoadMembershipNames($contracts);
        }

        if ($activeTab === 'action-items') {
            $actionItems = $program->memberActionItems()
                ->latest('due_at')
                ->paginate(20, ['*'], 'action_items');

            [$referrerMembershipsById, $partnerMembershipsById] = $this->batchLoadMembershipNames($actionItems);
        }

        $analytics = null;
        if ($activeTab === 'analytics') {
            $analytics = app(\App\Services\Programs\ProgramPerformanceSummary::class)->compute($program);
        }

        $intakeForms = null;
        if ($activeTab === 'intake') {
            $intakeForms = $program->requestForms()->withCount('submissions')->latest()->get();
        }

        $accessRows = null;
        if ($activeTab === 'access') {
            $permissionService = new PermissionService();

            $accessRows = TenantMembership::where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->with('tenantUser:id,first_name,last_name,email,nickname')
                ->get()
                ->map(fn (TenantMembership $membership) => [
                    'membership' => $membership,
                    'can_view'   => $permissionService->can($membership, 'view_programs'),
                    'can_manage' => $permissionService->can($membership, 'manage_programs'),
                ]);
        }

        $programNotifications = $notificationRecipientsById = null;
        if ($activeTab === 'notifications') {
            $programNotifications = Notification::where('tenant_id', $tenantId)
                ->where('metadata_json->program_id', $program->id)
                ->latest('sent_at')
                ->paginate(20, ['*'], 'notifications');

            $recipientIds = $programNotifications->getCollection()
                ->where('notifiable_type', 'tenant_admin')
                ->pluck('notifiable_id')
                ->unique();

            $notificationRecipientsById = TenantUser::whereIn('id', $recipientIds)
                ->get(['id', 'first_name', 'last_name', 'nickname', 'email'])
                ->keyBy('id');
        }

        return view('tenant.programs.workspace', compact(
            'tenant', 'program', 'activeTab',
            'referrerMemberships', 'partnerMemberships', 'offers', 'offerOptions',
            'tenantResellers', 'tenantPartners',
            'contracts', 'actionItems', 'referrerMembershipsById', 'partnerMembershipsById',
            'programReferrerMemberships', 'programPartnerMemberships',
            'analytics', 'accessRows',
            'programNotifications', 'notificationRecipientsById',
            'intakeForms'
        ));
    }

    public function update(Request $request, string $tenantId, string $programId)
    {
        abort_unless(config('programs.enabled'), 404);

        $program = Program::forTenant($tenantId)->findOrFail($programId);
        $this->authorize('update', $program);

        $data = $request->validate([
            'operating_mode'             => ['sometimes', 'required', 'in:manual,automated'],
            'name'                       => ['sometimes', 'string', 'max:120'],
            'short_description'          => ['nullable', 'string', 'max:500'],
            'full_description'           => ['nullable', 'string', 'max:5000'],
            'program_type'               => ['sometimes', 'in:' . implode(',', Program::allTypes())],
            'public_visibility'          => ['sometimes', 'in:private,unlisted,public'],
            'public_cta_text'            => ['nullable', 'string', 'max:160'],
            'application_mode'           => ['sometimes', 'in:invite_only,application,both,direct,import,api'],
            'approval_mode'              => ['sometimes', 'in:manual,auto'],
            'attribution_model'          => ['sometimes', 'in:first_touch,last_touch,manual,code,link,deal_registration'],
            'attribution_window_days'    => ['sometimes', 'integer', 'min:1', 'max:365'],
            'referral_expiry_days'       => ['nullable', 'integer', 'min:1', 'max:730'],
            'duplicate_referral_policy'  => ['sometimes', 'in:reject,allow,flag'],
            'organization_uniqueness_policy' => ['sometimes', 'in:one_per_org,allow_multiple'],
            'existing_customer_policy'   => ['sometimes', 'in:reject,allow,flag'],
            'self_referral_policy'       => ['sometimes', 'in:reject,allow'],
            'timezone'                   => ['sometimes', 'timezone'],
            'default_currency'           => ['sometimes', 'string', 'size:3'],
            'starts_at'                  => ['nullable', 'date'],
            'ends_at'                    => ['nullable', 'date', 'after:starts_at'],
            'enrollment_opens_at'        => ['nullable', 'date'],
            'enrollment_closes_at'       => ['nullable', 'date', 'after:enrollment_opens_at'],
            'referral_period_opens_at'   => ['nullable', 'date'],
            'referral_period_closes_at'  => ['nullable', 'date', 'after:referral_period_opens_at'],
            'evergreen'                  => ['sometimes', 'boolean'],
        ]);

        if (isset($data['operating_mode'])
            && $data['operating_mode'] !== $program->effectiveOperatingMode()
            && !$program->canChangeOperatingMode()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'operating_mode' => 'The mode is fixed after launch or integration setup. Create a new program to use a different mode.',
            ]);
        }

        $data['updated_by'] = (string) (auth('tenant')->id() ?? auth('web')->id());

        $program->update($data);

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Program updated.');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * membership_type/membership_id on ProgramContract/MemberActionItem are
     * loose columns (literal 'referrer'/'partner' strings), not a real
     * Eloquent morphTo. Batch-fetch both membership tables by id instead of
     * resolving a display name per row, to avoid N+1.
     *
     * @param iterable $rows Rows with membership_type/membership_id columns.
     * @return array{0: Collection, 1: Collection}
     */
    private function batchLoadMembershipNames(iterable $rows): array
    {
        $referrerIds = [];
        $partnerIds  = [];

        foreach ($rows as $row) {
            if ($row->membership_type === 'referrer') {
                $referrerIds[] = $row->membership_id;
            } else {
                $partnerIds[] = $row->membership_id;
            }
        }

        $referrerMembershipsById = ReferrerProgramMembership::with('reseller:id,name,email')
            ->whereIn('id', $referrerIds)
            ->get()
            ->keyBy('id');

        $partnerMembershipsById = PartnerProgramMembership::with('partner:id,first_name,last_name,email')
            ->whereIn('id', $partnerIds)
            ->get()
            ->keyBy('id');

        return [$referrerMembershipsById, $partnerMembershipsById];
    }

    private function resolveTab(mixed $tab): string
    {
        if (is_string($tab) && in_array($tab, self::IMPLEMENTED_TABS, true)) {
            return $tab;
        }
        return 'overview';
    }
}
