<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Models\ReferrerProgramMembership;
use App\Models\Tenant;
use Illuminate\Http\Request;

/**
 * Referrer (reseller guard) portal — program browsing, detail view, and
 * membership management. Read-only: referrers join via invite links or
 * self-application; they never mutate Program records directly.
 */
class ReferrerProgramController extends Controller
{
    // ── Program list visible to the authenticated referrer ────────────────────

    public function index(Request $request, string $tenantId)
    {
        abort_unless(config('programs.enabled'), 404);

        $referrer = auth('reseller')->user();
        $this->ensureBelongsToTenant($referrer, $tenantId);

        $tenant = Tenant::findOrFail($tenantId);

        // Programs this referrer is enrolled in
        $memberships = ReferrerProgramMembership::with('program')
            ->where('reseller_id', $referrer->id)
            ->whereHas('program', fn($q) => $q->where('tenant_id', $tenantId)->visible())
            ->get();

        // Programs that are public/unlisted and open for enrollment
        $openPrograms = Program::forTenant($tenantId)
            ->whereIn('public_visibility', ['public', 'unlisted'])
            ->whereIn('status', ['active', 'scheduled'])
            ->whereNotIn('id', $memberships->pluck('program_id'))
            ->get();

        return view('reseller.programs.index', compact(
            'tenant', 'memberships', 'openPrograms'
        ));
    }

    // ── Program detail for an enrolled referrer ───────────────────────────────

    public function show(Request $request, string $tenantId, string $programId)
    {
        abort_unless(config('programs.enabled'), 404);

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
