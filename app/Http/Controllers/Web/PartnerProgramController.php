<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\PartnerProgramMembership;
use App\Models\Program;
use App\Models\Tenant;
use Illuminate\Http\Request;

/**
 * Partner (partner guard) portal — program browsing and detail.
 * Partners are auto-enrolled when added; they never self-enroll.
 * tenantId is always derived from auth — not from the URL.
 */
class PartnerProgramController extends Controller
{
    // ── Programs the authenticated partner belongs to ─────────────────────────

    public function index(Request $request)
    {
        abort_unless(config('programs.enabled'), 404);

        $partner  = auth('partner')->user();
        $tenantId = $partner->tenant_id;
        $tenant   = Tenant::findOrFail($tenantId);

        $memberships = PartnerProgramMembership::with('program')
            ->where('partner_id', $partner->id)
            ->whereHas('program', fn($q) => $q->where('tenant_id', $tenantId)->visible())
            ->get();

        return view('partner.programs.index', compact('tenant', 'memberships'));
    }

    // ── Program detail for an enrolled partner ────────────────────────────────

    public function show(Request $request, string $programId)
    {
        abort_unless(config('programs.enabled'), 404);

        $partner  = auth('partner')->user();
        $tenantId = $partner->tenant_id;
        $tenant   = Tenant::findOrFail($tenantId);

        $program = Program::forTenant($tenantId)->findOrFail($programId);

        // Partners only see programs they're enrolled in
        $membership = PartnerProgramMembership::where('partner_id', $partner->id)
            ->where('program_id', $programId)
            ->firstOrFail();

        return view('partner.programs.show', compact(
            'tenant', 'program', 'membership'
        ));
    }
}
