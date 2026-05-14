<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Jobs\SyncEntityToGoogleCalendar;
use App\Models\GoogleCalendarIntegration;
use App\Models\Task;
use App\Models\Tenant;
use App\Services\GoogleCalendarService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GoogleCalendarController extends Controller
{
    public function __construct(private GoogleCalendarService $svc) {}

    // ── Integrations settings page ────────────────────────────────────────────

    public function index(string $tenantId)
    {
        $user        = $this->resolveUser();
        $tenant      = Tenant::findOrFail($tenantId);
        $integration = GoogleCalendarIntegration::where('tenant_user_id', $user->id)
            ->where('tenant_id', $tenantId)
            ->first();

        return view('tenant.integrations.index', compact('tenantId', 'tenant', 'integration'));
    }

    // ── OAuth: redirect to Google ─────────────────────────────────────────────

    public function redirect(string $tenantId)
    {
        $user  = $this->resolveUser();
        $state = Str::random(40);

        Cache::put("gcal_oauth_state_{$state}", [
            'tenant_id' => $tenantId,
            'user_id'   => $user->id,
        ], now()->addMinutes(5));

        return redirect()->away($this->svc->getAuthUrl($state));
    }

    // ── OAuth: callback from Google ───────────────────────────────────────────

    public function callback(Request $request)
    {
        $state = $request->query('state');
        $code  = $request->query('code');

        if (!$state || !$code) {
            return redirect('/')->withErrors(['Google Calendar connection failed: missing state or code.']);
        }

        $cached = Cache::pull("gcal_oauth_state_{$state}");
        if (!$cached) {
            return redirect('/')->withErrors(['Google Calendar connection failed: invalid or expired state.']);
        }

        ['tenant_id' => $tenantId, 'user_id' => $userId] = $cached;

        try {
            $tokens = $this->svc->exchangeCode($code);

            if (isset($tokens['error'])) {
                return redirect("/tenant/{$tenantId}/integrations")
                    ->withErrors(['Google Calendar: ' . ($tokens['error_description'] ?? $tokens['error'])]);
            }

            // Preserve existing refresh_token if Google didn't issue a new one
            $existingRefreshToken = GoogleCalendarIntegration::where('tenant_user_id', $userId)
                ->value('refresh_token');

            $refreshToken = isset($tokens['refresh_token'])
                ? Crypt::encryptString($tokens['refresh_token'])
                : $existingRefreshToken;

            GoogleCalendarIntegration::updateOrCreate(
                ['tenant_user_id' => $userId],
                [
                    'tenant_id'        => $tenantId,
                    'access_token'     => Crypt::encryptString($tokens['access_token'] ?? ''),
                    'refresh_token'    => $refreshToken,
                    'token_expires_at' => now()->addSeconds($tokens['expires_in'] ?? 3600),
                    'scopes'           => $tokens['scope'] ?? null,
                    'google_email'     => $this->svc->fetchUserEmail($tokens['access_token'] ?? ''),
                    'is_active'        => true,
                    'connected_at'     => now(),
                ],
            );

            Cache::forget("gcal_connected_{$userId}");
            $this->dispatchInitialSync($tenantId, $userId);

        } catch (\Throwable $e) {
            return redirect("/tenant/{$tenantId}/integrations")
                ->withErrors(['Google Calendar connection failed: ' . $e->getMessage()]);
        }

        return redirect("/tenant/{$tenantId}/integrations")
            ->with('success', 'Google Calendar connected successfully.');
    }

    // ── Disconnect ────────────────────────────────────────────────────────────

    public function disconnect(Request $request, string $tenantId)
    {
        $user        = $this->resolveUser();
        $integration = GoogleCalendarIntegration::where('tenant_user_id', $user->id)
            ->where('tenant_id', $tenantId)
            ->first();

        if ($integration) {
            $integration->calendarEvents()->delete();
            $integration->delete();
        }

        Cache::forget("gcal_connected_{$user->id}");

        return back()->with('success', 'Google Calendar disconnected.');
    }

    // ── Manual "Sync Now" ─────────────────────────────────────────────────────

    public function syncNow(Request $request, string $tenantId)
    {
        $user        = $this->resolveUser();
        $integration = GoogleCalendarIntegration::where('tenant_user_id', $user->id)
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->first();

        if (!$integration) {
            return back()->withErrors(['Google Calendar is not connected.']);
        }

        $this->dispatchInitialSync($tenantId, $user->id);

        return back()->with('success', 'Sync started — your calendar will be updated shortly.');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function resolveUser(): mixed
    {
        $user = Auth::guard('tenant')->user() ?? Auth::guard('web')->user();
        if (!$user) abort(401);
        return $user;
    }

    private function dispatchInitialSync(string $tenantId, string $userId): void
    {
        // Sync open tasks with due dates assigned to this user
        Task::where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->where('assigned_to_type', 'tenant_user')
            ->where('assigned_to_id', $userId)
            ->whereNotNull('due_at')
            ->whereNotIn('status', ['completed', 'cancelled', 'archived'])
            ->pluck('id')
            ->each(fn($id) => SyncEntityToGoogleCalendar::dispatch('task', $id));

        $isAdmin = DB::table('tenant_memberships')
            ->where('tenant_user_id', $userId)
            ->where('tenant_id', $tenantId)
            ->whereIn('role', ['owner', 'admin', 'manager'])
            ->where('status', 'active')
            ->exists();

        if ($isAdmin) {
            DB::table('leads')
                ->where('tenant_id', $tenantId)
                ->whereIn('status', ['active', 'expiring'])
                ->where('days_left', '>', 0)
                ->whereNull('deleted_at')
                ->pluck('id')
                ->each(fn($id) => SyncEntityToGoogleCalendar::dispatch('deal', $id));
        }
    }
}
