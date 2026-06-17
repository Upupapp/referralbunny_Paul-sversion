<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Models\Tenant;
use Illuminate\Http\Request;

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
    private const IMPLEMENTED_TABS = ['overview'];

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

        $activeTab = $this->resolveTab($request->query('tab'));

        return view('tenant.programs.workspace', compact(
            'tenant', 'program', 'activeTab'
        ));
    }

    public function update(Request $request, string $tenantId, string $programId)
    {
        abort_unless(config('programs.enabled'), 404);

        $program = Program::forTenant($tenantId)->findOrFail($programId);
        $this->authorize('update', $program);

        $data = $request->validate([
            'name'                       => ['sometimes', 'string', 'max:120'],
            'short_description'          => ['nullable', 'string', 'max:500'],
            'full_description'           => ['nullable', 'string', 'max:5000'],
            'program_type'               => ['sometimes', 'in:' . implode(',', Program::allTypes())],
            'public_visibility'          => ['sometimes', 'in:private,unlisted,public'],
            'application_mode'           => ['sometimes', 'in:invite_only,application,both,direct,import,api'],
            'approval_mode'              => ['sometimes', 'in:manual,auto'],
            'attribution_model'          => ['sometimes', 'in:first_touch,last_touch,manual,code,link,deal_registration'],
            'attribution_window_days'    => ['sometimes', 'integer', 'min:1', 'max:365'],
            'referral_expiry_days'       => ['nullable', 'integer', 'min:1', 'max:730'],
            'duplicate_referral_policy'  => ['sometimes', 'in:reject,allow,flag'],
            'existing_customer_policy'   => ['sometimes', 'in:reject,allow,flag'],
            'self_referral_policy'       => ['sometimes', 'in:reject,allow'],
            'timezone'                   => ['sometimes', 'timezone'],
            'default_currency'           => ['sometimes', 'string', 'size:3'],
            'starts_at'                  => ['nullable', 'date'],
            'ends_at'                    => ['nullable', 'date', 'after:starts_at'],
            'evergreen'                  => ['sometimes', 'boolean'],
        ]);

        $data['updated_by'] = (string) (auth('tenant')->id() ?? auth('web')->id());

        $program->update($data);

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Program updated.');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function resolveTab(mixed $tab): string
    {
        if (is_string($tab) && in_array($tab, self::IMPLEMENTED_TABS, true)) {
            return $tab;
        }
        return 'overview';
    }
}
