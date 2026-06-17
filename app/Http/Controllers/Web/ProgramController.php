<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Models\Tenant;
use App\Services\Programs\ProgramLifecycleService;
use Illuminate\Http\Request;

class ProgramController extends Controller
{
    public function __construct(private ProgramLifecycleService $lifecycle) {}

    // ── Index ─────────────────────────────────────────────────────────────────

    public function index(Request $request, string $tenantId)
    {
        abort_unless(config('programs.enabled'), 404);

        $this->authorize('viewAny', Program::class);

        $tenant = Tenant::findOrFail($tenantId);

        $programs = Program::forTenant($tenantId)
            ->visible()
            ->orderByDesc('created_at')
            ->get();

        return view('tenant.programs.index', compact('tenant', 'programs'));
    }

    // ── Create / Store ────────────────────────────────────────────────────────

    public function create(Request $request, string $tenantId)
    {
        abort_unless(config('programs.enabled'), 404);

        $tenant = Tenant::findOrFail($tenantId);
        $this->authorize('create', Program::class);

        return view('tenant.programs.create', [
            'tenant'   => $tenant,
            'statuses' => Program::allStatuses(),
            'types'    => Program::allTypes(),
        ]);
    }

    public function store(Request $request, string $tenantId)
    {
        abort_unless(config('programs.enabled'), 404);

        $tenant = Tenant::findOrFail($tenantId);
        $this->authorize('create', Program::class);

        $data = $request->validate([
            'name'              => ['required', 'string', 'max:120'],
            'program_type'      => ['required', 'in:' . implode(',', Program::allTypes())],
            'short_description' => ['nullable', 'string', 'max:500'],
            'public_visibility' => ['nullable', 'in:private,unlisted,public'],
            'application_mode'  => ['nullable', 'in:invite_only,application,both,direct,import,api'],
            'timezone'          => ['nullable', 'timezone'],
            'default_currency'  => ['nullable', 'string', 'size:3'],
            'starts_at'         => ['nullable', 'date'],
            'ends_at'           => ['nullable', 'date', 'after:starts_at'],
            'evergreen'         => ['nullable', 'boolean'],
        ]);

        $cap = config('programs.max_programs_per_tenant', 25);
        $active = Program::forTenant($tenantId)->whereNotIn('status', ['archived'])->count();
        if ($active >= $cap) {
            return back()->withErrors(['name' => "You have reached the maximum of {$cap} active programs."]);
        }

        $program = Program::create([
            ...$data,
            'tenant_id'  => $tenantId,   // always from route, never from request
            'status'     => 'draft',
            'created_by' => (string) (auth('tenant')->id() ?? auth('web')->id()),
        ]);

        return redirect()
            ->route('tenant.programs.workspace', [$tenantId, $program->id])
            ->with('success', 'Program created. Configure it in the workspace below.');
    }

    // ── Lifecycle Actions ─────────────────────────────────────────────────────

    public function launch(Request $request, string $tenantId, string $programId)
    {
        abort_unless(config('programs.enabled'), 404);

        $program = Program::forTenant($tenantId)->findOrFail($programId);
        $this->authorize('launch', $program);

        $actorId = auth('tenant')->id() ?? auth('web')->id();
        $guard   = auth('tenant')->check() ? 'tenant' : 'web';

        try {
            $this->lifecycle->transition($program, 'active', $actorId, $guard);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['lifecycle' => $e->getMessage()]);
        }

        return back()->with('success', 'Program launched.');
    }

    public function pause(Request $request, string $tenantId, string $programId)
    {
        abort_unless(config('programs.enabled'), 404);

        $program = Program::forTenant($tenantId)->findOrFail($programId);
        $this->authorize('pause', $program);

        $actorId = auth('tenant')->id() ?? auth('web')->id();
        $guard   = auth('tenant')->check() ? 'tenant' : 'web';

        try {
            $this->lifecycle->transition($program, 'paused', $actorId, $guard);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['lifecycle' => $e->getMessage()]);
        }

        return back()->with('success', 'Program paused.');
    }

    public function end(Request $request, string $tenantId, string $programId)
    {
        abort_unless(config('programs.enabled'), 404);

        $program = Program::forTenant($tenantId)->findOrFail($programId);
        $this->authorize('end', $program);

        $actorId = auth('tenant')->id() ?? auth('web')->id();
        $guard   = auth('tenant')->check() ? 'tenant' : 'web';

        try {
            $this->lifecycle->transition($program, 'ended', $actorId, $guard);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['lifecycle' => $e->getMessage()]);
        }

        return back()->with('success', 'Program ended.');
    }

    public function archive(Request $request, string $tenantId, string $programId)
    {
        abort_unless(config('programs.enabled'), 404);

        $program = Program::forTenant($tenantId)->findOrFail($programId);
        $this->authorize('archive', $program);

        $actorId = auth('tenant')->id() ?? auth('web')->id();
        $guard   = auth('tenant')->check() ? 'tenant' : 'web';

        try {
            $this->lifecycle->transition($program, 'archived', $actorId, $guard);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['lifecycle' => $e->getMessage()]);
        }

        return redirect()
            ->route('tenant.programs.index', $tenantId)
            ->with('success', 'Program archived.');
    }

    public function destroy(Request $request, string $tenantId, string $programId)
    {
        abort_unless(config('programs.enabled'), 404);

        $program = Program::forTenant($tenantId)->findOrFail($programId);
        $this->authorize('delete', $program);

        $actorId = auth('tenant')->id() ?? auth('web')->id();
        $guard   = auth('tenant')->check() ? 'tenant' : 'web';

        try {
            $this->lifecycle->delete($program, $actorId, $guard);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['delete' => $e->getMessage()]);
        }

        return redirect()
            ->route('tenant.programs.index', $tenantId)
            ->with('success', 'Program deleted.');
    }
}
