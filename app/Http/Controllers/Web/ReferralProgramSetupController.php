<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\TenantReferralProgramDraft;
use App\Services\PermissionService;
use App\Services\ReferralProgram\ReferralProgramAuditService;
use App\Services\ReferralProgram\ReferralProgramRecommendationService;
use App\Services\ReferralProgram\ReferralProgramSetupService;
use App\Support\ProtectedTenants;
use App\Support\ReferralProgramOptions;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReferralProgramSetupController extends Controller
{
    public function overview(string $tenantId)
    {
        if ($denied = $this->checkAccess($tenantId)) {
            return $denied;
        }

        $tenant = Tenant::findOrFail($tenantId);
        $setup  = app(ReferralProgramSetupService::class);

        $draft = $this->activeDraft($tenantId);

        return view('tenant.settings.referral-program.overview', [
            'tenant'    => $tenant,
            'tenantId'  => $tenantId,
            'draft'     => $draft,
            'health'    => $draft ? $setup->healthScore($draft) : null,
            'protected' => ProtectedTenants::isProtected($tenantId),
        ]);
    }

    public function wizard(Request $request, string $tenantId)
    {
        if ($denied = $this->checkAccess($tenantId)) {
            return $denied;
        }

        $tenant = Tenant::findOrFail($tenantId);
        $setup  = app(ReferralProgramSetupService::class);
        $userId = Auth::guard('tenant')->id();
        $mode   = $request->query('mode', 'quick');

        $draft       = $this->activeDraft($tenantId);
        $justStarted = false;

        if (! $draft) {
            try {
                $draft       = $setup->getOrCreateActiveDraft($tenantId, $userId, $mode);
                $justStarted = true;

                $audit = app(ReferralProgramAuditService::class);
                $audit->log($tenantId, 'draft_created', $userId, ['draft_id' => $draft->id, 'mode' => $mode]);
                if ($mode === 'quick') {
                    $audit->log($tenantId, 'quick_setup_started', $userId, ['draft_id' => $draft->id]);
                }
            } catch (\Throwable $e) {
                Log::error('Referral program setup draft could not be created', [
                    'tenant_id' => $tenantId,
                    'error'     => $e->getMessage(),
                ]);

                return redirect()
                    ->route('tenant.settings.referral-program.overview', $tenantId)
                    ->with('error', 'We could not start the setup wizard. Please try again.');
            }
        }

        $step = $request->query('step', $draft->current_step ?? ReferralProgramSetupService::IMPLEMENTED_STEPS[0]);
        if (! in_array($step, ReferralProgramSetupService::IMPLEMENTED_STEPS, true)) {
            $step = ReferralProgramSetupService::IMPLEMENTED_STEPS[0];
        }

        $industry = $draft->config['industry_goal']['industry'] ?? null;
        $recommendation = app(ReferralProgramRecommendationService::class)->recommendForIndustry($industry);

        $programType = $draft->config['program_type']['program_type'] ?? $recommendation['program_type'] ?? null;
        $pipelineStages = $draft->config['pipeline']['stages']
            ?? ReferralProgramOptions::pipelineStageTemplates()[ReferralProgramOptions::pipelineTemplateForProgramType($programType)];

        return view('tenant.settings.referral-program.wizard', [
            'tenant'           => $tenant,
            'tenantId'         => $tenantId,
            'draft'            => $draft,
            'step'             => $step,
            'stepIndex'        => array_search($step, ReferralProgramSetupService::STEPS, true),
            'steps'            => ReferralProgramSetupService::STEPS,
            'stepLabels'       => ReferralProgramSetupService::STEP_LABELS,
            'stepHelp'         => $this->stepHelp(),
            'implementedSteps' => ReferralProgramSetupService::IMPLEMENTED_STEPS,
            'health'           => $setup->healthScore($draft),
            'recommendation'   => $recommendation,
            'pipelineStages'   => $pipelineStages,
            'options'          => [
                'industries'      => ReferralProgramOptions::industries(),
                'goals'           => ReferralProgramOptions::referralGoals(),
                'programTypes'    => ReferralProgramOptions::programTypes(),
                'roles'           => ReferralProgramOptions::participantRoles(),
                'fieldDataTypes'  => ReferralProgramOptions::customFieldDataTypes(),
                'commissionTypes' => ReferralProgramOptions::commissionTypes(),
                'reassignmentModes' => ReferralProgramOptions::reassignmentModes(),
                'partnerSplitTypes' => ReferralProgramOptions::partnerSplitTypes(),
            ],
            'stepData'         => $this->stepData($draft, $step, $tenant, $recommendation, $pipelineStages),
            'justStarted'      => $justStarted,
            'protected'        => ProtectedTenants::isProtected($tenantId),
        ]);
    }

    public function updateStep(Request $request, string $tenantId, string $step)
    {
        if ($this->checkAccess($tenantId)) {
            return response()->json(['success' => false, 'message' => 'You do not have permission to edit this setup.'], 403);
        }

        if (! in_array($step, ReferralProgramSetupService::IMPLEMENTED_STEPS, true)) {
            return response()->json(['success' => false, 'message' => 'This step is not available yet.'], 422);
        }

        $draft = $this->activeDraft($tenantId);

        if (! $draft) {
            return response()->json(['success' => false, 'message' => 'No setup draft found. Please reload and start again.'], 404);
        }

        $setup = app(ReferralProgramSetupService::class);

        // LGU IDS' commission/pipeline config is locked to its dedicated pricing
        // service and pipeline-stage rules — silently ignore writes to those
        // sections instead of erroring, per the protected-workspace rules.
        if (ProtectedTenants::isProtected($tenantId) && in_array($step, ProtectedTenants::lockedConfigSteps(), true)) {
            app(ReferralProgramAuditService::class)->log(
                $tenantId,
                'lgu_ids_protected_setting_change_blocked',
                Auth::guard('tenant')->id(),
                ['draft_id' => $draft->id, 'step' => $step]
            );

            return response()->json([
                'success'        => true,
                'message'        => "This section is managed by your administrator and can't be changed here.",
                'health'         => $setup->healthScore($draft),
                'recommendation' => null,
            ]);
        }

        try {
            $data = $this->validateStep($request, $step);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Please review the highlighted fields.',
                'errors'  => $e->errors(),
            ], 422);
        }

        $setup->updateStep($draft, $step, $data);

        $userId   = Auth::guard('tenant')->id();
        $explicit = $request->boolean('_explicit');

        app(ReferralProgramAuditService::class)->log(
            $tenantId,
            $explicit ? 'step_saved' : 'autosaved',
            $userId,
            ['draft_id' => $draft->id, 'step' => $step]
        );

        $recommendation = null;
        if ($step === 'industry-goal') {
            $recommendation = app(ReferralProgramRecommendationService::class)
                ->recommendForIndustry($data['industry'] ?? null);
        }

        return response()->json([
            'success'        => true,
            'message'        => $explicit ? 'Saved.' : 'Autosaved.',
            'health'         => $setup->healthScore($draft),
            'recommendation' => $recommendation,
        ]);
    }

    public function discardDraft(string $tenantId)
    {
        if ($denied = $this->checkAccess($tenantId)) {
            return $denied;
        }

        $draft = $this->activeDraft($tenantId);

        if ($draft) {
            $userId = Auth::guard('tenant')->id();
            app(ReferralProgramAuditService::class)->log($tenantId, 'draft_discarded', $userId, ['draft_id' => $draft->id]);
            $draft->delete();
        }

        return redirect()
            ->route('tenant.settings.referral-program.overview', $tenantId)
            ->with('success', 'Draft discarded.');
    }

    private function activeDraft(string $tenantId): ?TenantReferralProgramDraft
    {
        return TenantReferralProgramDraft::where('tenant_id', $tenantId)
            ->where('status', 'draft')
            ->latest('updated_at')
            ->first();
    }

    /**
     * Returns a 403 response if the current user cannot manage the referral
     * program setup for this tenant, or null if access is allowed. Super
     * admins and tenant owners/admins always pass; managers need the
     * manage_referral_program_setup permission; members/viewers are denied.
     */
    private function checkAccess(string $tenantId): ?Response
    {
        if (Auth::guard('web')->check()) {
            return null;
        }

        $role = request()->attributes->get('_tenant_role', 'viewer');

        if (in_array($role, ['owner', 'admin'], true)) {
            return null;
        }

        if ($role === 'manager') {
            $membership = TenantMembership::where('tenant_user_id', Auth::guard('tenant')->id())
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->first();

            if ($membership && app(PermissionService::class)->can($membership, 'manage_referral_program_setup')) {
                return null;
            }
        }

        return response()->view('tenant.settings.referral-program.permission-denied', [
            'tenant'   => Tenant::find($tenantId),
            'tenantId' => $tenantId,
        ], 403);
    }

    /**
     * Pre-fills each implemented step's form with the draft's saved values,
     * falling back to sensible defaults derived from the tenant's existing
     * profile (or the industry recommendation, for Program Type).
     */
    private function stepData(TenantReferralProgramDraft $draft, string $step, Tenant $tenant, array $recommendation, array $pipelineStages = []): array
    {
        $config = $draft->config ?? [];

        return match ($step) {
            'program-basics' => array_merge([
                'program_name'        => $tenant->program_name ?: $tenant->name,
                'program_description' => $tenant->description,
                'brand_display_name'  => $tenant->name,
                'currency'            => 'PHP',
                'timezone'            => 'Asia/Manila',
                'support_email'       => $tenant->contact_email,
                'program_owner'       => $tenant->admin_name,
            ], $config['program_basics'] ?? []),

            'industry-goal' => array_merge([
                'industry'       => in_array($tenant->industry, array_keys(ReferralProgramOptions::industries()), true)
                    ? $tenant->industry
                    : null,
                'sub_industries' => $tenant->subIndustries->pluck('sub_industry')->take(3)->values()->all(),
                'referral_goal'  => null,
            ], $config['industry_goal'] ?? []),

            'program-type' => array_merge([
                'program_type' => $recommendation['program_type'] ?? 'referrer_program',
            ], $config['program_type'] ?? []),

            'participants' => array_merge([
                'roles' => ['tenant_admins', 'referrers'],
            ], $config['participants'] ?? []),

            'pipeline' => [
                'stages' => $config['pipeline']['stages'] ?? $pipelineStages,
            ],

            'fields' => [
                'fields' => $config['fields']['fields'] ?? [],
            ],

            'rewards' => array_merge([
                'commission_type'     => 'percentage_of_value',
                'company_share_pct'   => 30,
                'referrer_share_pct'  => 70,
                'default_expiry_days' => 21,
                'reassignment_mode'   => 'manual',
            ], $config['rewards'] ?? []),

            'partner-split' => array_merge([
                'allow_partners'      => in_array('partners', $config['participants']['roles'] ?? ['tenant_admins', 'referrers'], true),
                'require_approval'    => true,
                'split_type'          => 'percentage',
                'default_split_value' => 50,
                'lock_after_stage'    => null,
                'notify_partner'      => true,
            ], $config['partner_split'] ?? []),

            default => [],
        };
    }

    /**
     * Short R Bunny tips shown in the wizard sidebar for each step.
     */
    private function stepHelp(): array
    {
        return [
            'program-basics' => "Give your program a name your referrers will recognize. You can change any of this later.",
            'industry-goal'  => "Tell us your industry and main goal so we can recommend a program type that fits.",
            'program-type'   => "Not sure? The highlighted option is what we'd recommend based on your industry — you can pick a different one anytime.",
            'participants'   => "Tenant admins are always included. Add Referrers and Partners if they'll take part in this program.",
            'pipeline'       => "We've pre-filled stages based on your program type. Add, rename, reorder, or remove stages to match how deals move through your team.",
            'fields'         => "Add any extra details your team needs to capture on each deal. You can always add more fields later — existing data is never deleted.",
            'rewards'        => "Set how commission is calculated and split. Company share + referrer share should add up to 100%.",
            'partner-split'  => "If Partners are part of this program, decide how their share is calculated and when it locks in.",
        ];
    }

    private function validateStep(Request $request, string $step): array
    {
        return match ($step) {
            'program-basics' => $request->validate([
                'program_name'        => ['required', 'string', 'max:150'],
                'program_description' => ['nullable', 'string', 'max:1000'],
                'brand_display_name'  => ['nullable', 'string', 'max:150'],
                'currency'            => ['required', 'string', 'max:10'],
                'timezone'            => ['required', 'string', 'max:100'],
                'support_email'       => ['nullable', 'email', 'max:150'],
                'program_owner'       => ['nullable', 'string', 'max:150'],
            ]),
            'industry-goal' => array_merge(['sub_industries' => []], $request->validate([
                'industry'         => ['required', 'string', Rule::in(array_keys(ReferralProgramOptions::industries()))],
                'sub_industries'   => ['nullable', 'array', 'max:3'],
                'sub_industries.*' => ['string', 'max:100'],
                'referral_goal'    => ['required', 'string', Rule::in(array_keys(ReferralProgramOptions::referralGoals()))],
            ])),
            'program-type' => $request->validate([
                'program_type' => ['required', 'string', Rule::in(array_keys(ReferralProgramOptions::programTypes()))],
            ]),
            'participants' => $request->validate([
                'roles'   => ['required', 'array', 'min:1'],
                'roles.*' => ['string', Rule::in(array_keys(ReferralProgramOptions::participantRoles()))],
            ]),
            'pipeline' => $request->validate([
                'stages'             => ['required', 'array', 'min:2', 'max:10'],
                'stages.*.stage_key' => ['required', 'string', 'max:50'],
                'stages.*.name'      => ['required', 'string', 'max:100'],
                'stages.*.days'      => ['nullable', 'integer', 'min:1', 'max:365'],
                'stages.*.color'     => ['nullable', 'string', 'max:20'],
                'stages.*.is_final'  => ['nullable', 'boolean'],
                'stages.*.is_won'    => ['nullable', 'boolean'],
            ]),
            'fields' => $request->validate([
                'fields'               => ['present', 'array', 'max:20'],
                'fields.*.field_key'   => ['required', 'string', 'max:50'],
                'fields.*.field_label' => ['required', 'string', 'max:100'],
                'fields.*.data_type'   => ['required', 'string', Rule::in(array_keys(ReferralProgramOptions::customFieldDataTypes()))],
                'fields.*.is_required' => ['nullable', 'boolean'],
            ]),
            'rewards' => $request->validate([
                'commission_type'    => ['required', 'string', Rule::in(array_keys(ReferralProgramOptions::commissionTypes()))],
                'company_share_pct'  => ['required', 'numeric', 'min:0', 'max:100'],
                'referrer_share_pct' => ['required', 'numeric', 'min:0', 'max:100', function ($attribute, $value, $fail) use ($request) {
                    $company = (float) $request->input('company_share_pct', 0);
                    if (abs(($company + (float) $value) - 100) > 0.01) {
                        $fail('Company share and referrer share must add up to 100%.');
                    }
                }],
                'default_expiry_days' => ['required', 'integer', 'min:1', 'max:365'],
                'reassignment_mode'   => ['required', 'string', Rule::in(array_keys(ReferralProgramOptions::reassignmentModes()))],
            ]),
            'partner-split' => $request->validate([
                'allow_partners'      => ['required', 'boolean'],
                'require_approval'    => ['nullable', 'boolean'],
                'split_type'          => ['required', 'string', Rule::in(array_keys(ReferralProgramOptions::partnerSplitTypes()))],
                'default_split_value' => ['nullable', 'numeric', 'min:0', function ($attribute, $value, $fail) use ($request) {
                    if ($request->input('split_type') === 'percentage' && $value > 100) {
                        $fail('Percentage splits cannot exceed 100%.');
                    }
                }],
                'lock_after_stage'    => ['nullable', 'string', 'max:50'],
                'notify_partner'      => ['nullable', 'boolean'],
            ]),
            default => [],
        };
    }
}
