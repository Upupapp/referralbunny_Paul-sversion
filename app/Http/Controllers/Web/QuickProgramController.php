<?php
namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\{Program, ProgramConnection, Tenant, TenantMembership};
use App\Services\QuickProgram\{QuickProgramService, WebsiteAnalyzer};
use App\Support\ProtectedTenants;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class QuickProgramController extends Controller
{
    public function __construct(private QuickProgramService $setup, private WebsiteAnalyzer $websites) {}

    private function access(string $tenantId): void
    {
        abort_unless(config('programs.enabled') && !ProtectedTenants::isProtected($tenantId), 404);
        $user = auth('tenant')->user();
        abort_unless($user && $user->status === 'active', 403);
        abort_unless(TenantMembership::where('tenant_id', $tenantId)->where('tenant_user_id', $user->id)
            ->where('status', 'active')->whereIn('role', ['owner', 'admin'])->exists(), 403);
    }

    public function status(string $tenantId)
    {
        $this->access($tenantId);
        return response()->json(['needed' => !$this->setup->hasProgram($tenantId), 'draft' => $this->setup->draft($tenantId)?->config['quick_start'] ?? null]);
    }

    public function analyze(Request $request, string $tenantId)
    {
        $this->access($tenantId);
        $data = $request->validate(['website' => 'required|string|max:255']);
        return response()->json($this->websites->analyze($data['website']));
    }

    private function data(Request $request): array
    {
        $data = $request->validate([
            'website' => 'required|string|max:255', 'name' => 'required|string|max:120',
            'pricing_model' => 'required|in:subscription,one_time,usage,unknown',
            'price' => 'nullable|numeric|min:0|max:100000000',
            'currency' => ['required', Rule::in(['PHP','USD','EUR','GBP','AUD','SGD','CAD'])],
            'option' => 'required|in:first,recurring,custom',
            'reward_model' => 'required|in:percentage,fixed', 'reward_value' => 'required|numeric|min:0.01|max:1000000',
            'reward_scope' => 'required|in:first_payment,recurring',
            'duration_months' => 'required|integer|min:1|max:24',
            'hold_days' => 'required|integer|min:0|max:90',
        ]);
        $data['website'] = $this->websites->normalize($data['website']);
        if ($data['reward_model'] === 'percentage' && $data['reward_value'] > 100) {
            throw \Illuminate\Validation\ValidationException::withMessages(['reward_value' => 'Percentage cannot exceed 100.']);
        }
        return $data;
    }

    public function save(Request $request, string $tenantId)
    {
        $this->access($tenantId);
        abort_if($this->setup->hasProgram($tenantId), 409, 'A program has already been created.');
        $this->setup->save($tenantId, (string) auth('tenant')->id(), $this->data($request));
        return response()->json(['saved' => true]);
    }

    public function publish(Request $request, string $tenantId)
    {
        $this->access($tenantId);
        $request->validate(['confirmed' => 'accepted']);
        $program = $this->setup->publish($tenantId, (string) auth('tenant')->id(), $this->data($request));
        return response()->json(['redirect' => route('tenant.quick-program.connection', [$tenantId, $program->id])]);
    }

    public function installationStatus(string $tenantId, string $programId)
    {
        $this->access($tenantId);
        Program::forTenant($tenantId)->findOrFail($programId);
        $connection = ProgramConnection::where('tenant_id', $tenantId)->where('program_id', $programId)->firstOrFail();
        return response()->json([
            'installed' => (bool) $connection->snippet_installed_at,
            'installed_at' => $connection->snippet_installed_at?->toIso8601String(),
            'last_seen_at' => $connection->snippet_last_seen_at?->toIso8601String(),
            'origin' => $connection->snippet_origin,
            'origins' => app(\App\Services\QuickProgram\TrackingOrigins::class)->forConnection($connection),
            'payment_status' => $connection->status,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function updateTrackingOrigins(Request $request, string $tenantId, string $programId)
    {
        $this->access($tenantId);
        Program::forTenant($tenantId)->findOrFail($programId);
        $connection = ProgramConnection::where('tenant_id', $tenantId)->where('program_id', $programId)->firstOrFail();
        $data = $request->validate(['origins' => 'required|array|min:1|max:5', 'origins.*' => 'required|string|max:255']);
        $service = app(\App\Services\QuickProgram\TrackingOrigins::class);
        $origins = array_values(array_unique(array_map(fn ($origin) => $service->normalize($origin), $data['origins'])));
        $changes = ['tracking_origins' => $origins];
        if ($connection->snippet_origin && !in_array($connection->snippet_origin, $origins, true)) {
            $changes += ['snippet_installed_at' => null, 'snippet_last_seen_at' => null, 'snippet_origin' => null];
        }
        $connection->update($changes);
        return $this->installationStatus($tenantId, $programId);
    }

    public function connection(string $tenantId, string $programId)
    {
        $this->access($tenantId);
        $tenant = Tenant::findOrFail($tenantId);
        $program = Program::forTenant($tenantId)->findOrFail($programId);
        $connection = ProgramConnection::where('tenant_id', $tenantId)->where('program_id', $programId)->firstOrFail();
        $events = DB::table('program_conversion_events')->where('connection_id', $connection->id)->latest('created_at')->limit(20)->get();
        return response()->view('tenant.programs.connection', compact('tenant', 'program', 'connection', 'events'))
            ->header('Cache-Control', 'private, no-store');
    }
}
