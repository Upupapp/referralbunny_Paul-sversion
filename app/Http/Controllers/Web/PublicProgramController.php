<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Models\Tenant;
use Illuminate\Http\Request;

/**
 * Unauthenticated public-facing program pages.
 * Served at /p/{tenantSlug}/{programSlug}.
 *
 * Authorization rule: only show programs with public_visibility ∈ {public, unlisted}
 * and status ∈ {active, scheduled, paused, ended}. Archived/draft/private programs
 * return 404 — not 403 — to avoid leaking existence.
 */
class PublicProgramController extends Controller
{
    public function show(Request $request, string $tenantSlug, string $programSlug)
    {
        abort_unless(config('programs.enabled'), 404);

        $tenant = Tenant::where('slug', $tenantSlug)->firstOrFail();

        $program = Program::where('tenant_id', $tenant->id)
            ->where('slug', $programSlug)
            ->whereIn('public_visibility', ['public', 'unlisted'])
            ->whereIn('status', ['active', 'scheduled', 'paused', 'ended'])
            ->firstOrFail();

        return view('public.programs.show', compact('tenant', 'program'));
    }
}
