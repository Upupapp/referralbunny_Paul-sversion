<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Models\ReferrerProgramMembership;
use App\Models\Tenant;
use App\Support\ProtectedTenants;
use Illuminate\Http\Request;

/**
 * Referrer (reseller guard) portal — program browsing, detail view, and
 * membership management. Read-only: referrers join via invite links or
 * self-application; they never mutate Program records directly.
 *
 * This controller never goes through ProgramPolicy (see that class's
 * doc-block — referrers/partners use their own portal controllers instead),
 * so the ProtectedTenants check below is this controller's own copy of that
 * guardrail, not an authorize() call.
 */
class ReferrerProgramController extends Controller
{
    // ── Program list visible to the authenticated referrer ────────────────────

    public function index(Request $request, string $tenantId)
    {
        abort_unless(config('programs.enabled'), 404);
        abort_if(ProtectedTenants::isProtected($tenantId), 404);

        $referrer = auth('reseller')->user();
        $this->ensureBelongsToTenant($referrer, $tenantId);

        $tenant = Tenant::findOrFail($tenantId);

        // Programs this referrer is enrolled in
        $memberships = ReferrerProgramMembership::with('program.offers.currentVersion')
            ->where('reseller_id', $referrer->id)
            ->whereHas('program', fn($q) => $q->where('tenant_id', $tenantId)->visible())
            ->get();

        // Programs that are public/unlisted and open for enrollment
        $openPrograms = Program::forTenant($tenantId)
            ->whereIn('public_visibility', ['public', 'unlisted'])
            ->whereIn('status', ['active', 'scheduled'])
            ->whereNotIn('id', $memberships->pluck('program_id'))
            ->get();

        $cards=app(\App\Services\Programs\ReferrerProgramCards::class)->build($memberships,$tenantId,$referrer->id);
        return view('reseller.programs.index', compact(
            'tenant', 'memberships', 'openPrograms', 'cards'
        ));
    }

    // ── Program detail for an enrolled referrer ───────────────────────────────

    public function show(Request $request, string $tenantId, string $programId)
    {
        abort_unless(config('programs.enabled'), 404);
        abort_if(ProtectedTenants::isProtected($tenantId), 404);

        $referrer = auth('reseller')->user();
        $this->ensureBelongsToTenant($referrer, $tenantId);

        $tenant  = Tenant::findOrFail($tenantId);
        $program = Program::forTenant($tenantId)->findOrFail($programId);

        // Must be enrolled or program must be publicly visible
        $membership = ReferrerProgramMembership::where('reseller_id', $referrer->id)
            ->where('program_id', $programId)
            ->first();

        if (!$membership && !$program->isPubliclyVisible()) {
            abort(404);
        }

        if ($membership && in_array($membership->status, ['active','approved'])) {
            $request->session()->put('referrer_program.'.$tenantId.'.'.$referrer->id, $program->id);
        }

        return view('reseller.programs.show', compact(
            'tenant', 'program', 'membership'
        ));
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function ensureBelongsToTenant(mixed $referrer, string $tenantId): void
    {
        if (!$referrer || $referrer->tenant_id !== $tenantId) {
            abort(403);
        }
    }
}
