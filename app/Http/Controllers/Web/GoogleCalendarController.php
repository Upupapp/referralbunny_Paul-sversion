<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Jobs\DeleteGoogleCalendarEvent;
use App\Jobs\SyncEntityToGoogleCalendar;
use App\Models\GoogleCalendarIntegration;
use App\Models\Task;
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
        $user = Auth::guard('tenant')->user() ?? Auth::guard('web')->user();
        if (!$user) abort(401);

        $integration = GoogleCalendarIntegration::where('tenant_user_id', $user->id)->first();

        return view('tenant.integrations.index', compact('tenantId', 'integration'));
    }

    // ── OAuth: redirect to Google ─────────────────────────────────────────────

    public function redirect(string $tenantId)
    {
        $user = Auth::guard('tenant')->user() ?? Auth::guard('web')->user();
        if (!$user) abort(401);

        // Generate a random state and store tenant_id + user_id in cache (5 min TTL)
        $state = Str::random(40);
        Cache::put("gcal_oauth_state_{$state}", [
            'tenant_id' => $tenantId,
            'user_id'   => $user->id,
        ], now()->addMinutes(5));

        $authUrl = $this->svc->getAuthUrl($state);
        return redirect()->away($authUrl);
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

        $tenantId = $cached['tenant_id'];
        $userId   = $cached['user_id'];

        try {
            $tokens = $this->svc->exchangeCode($code);

            if (isset($tokens['error'])) {
                return redirect("/tenant/{$tenantId}/integrations")->withErrors(['Google Calendar: ' . $tokens['error_description'] ?? $tokens['error']]);
            }

            // Fetch the user's Google email for display
            $googleEmail = $this->fetchGoogleEmail($tokens['access_token'] ?? '');

            GoogleCalendarIntegration::updateOrCreate(
                ['tenant_user_id' => $userId],
                [
                    'tenant_id'        => $tenantId,
                    'access_token'     => Crypt::encryptString($tokens['access_token'] ?? ''),
                    'refresh_token'    => isset($tokens['refresh_token'])
                        ? Crypt::encryptString($tokens['refresh_token'])
                        : DB::table('google_calendar_integrations')->where('tenant_user_id', $userId)->value('refresh_token'),
                    'token_expires_at' => now()->addSeconds($tokens['expires_in'] ?? 3600),
                    'scopes'           => $tokens['scope'] ?? null,
                    'google_email'     => $googleEmail,
                    'is_active'        => true,
                    'connected_at'     => now(),
                ],
            );

            // Kick off a background sync of open tasks assigned to this user
            $this->dispatchInitialSync($tenantId, $userId);

        } catch (\Throwable $e) {
            return redirect("/tenant/{$tenantId}/integrations")->withErrors(['Google Calendar connection failed: ' . $e->getMessage()]);
        }

        return redirect("/tenant/{$tenantId}/integrations")->with('success', 'Google Calendar connected successfully.');
    }

    // ── Disconnect ────────────────────────────────────────────────────────────

    public function disconnect(Request $request, string $tenantId)
    {
        $user = Auth::guard('tenant')->user() ?? Auth::guard('web')->user();
        if (!$user) abort(401);

        $integration = GoogleCalendarIntegration::where('tenant_user_id', $user->id)
            ->where('tenant_id', $tenantId)
            ->first();

        if ($integration) {
            // Delete all synced calendar events for this integration
            $integration->calendarEvents()->delete();
            $integration->delete();
        }

        return back()->with('success', 'Google Calendar disconnected.');
    }

    // ── Manual "Sync Now" ─────────────────────────────────────────────────────

    public function syncNow(Request $request, string $tenantId)
    {
        $user = Auth::guard('tenant')->user() ?? Auth::guard('web')->user();
        if (!$user) abort(401);

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

    private function fetchGoogleEmail(string $accessToken): ?string
    {
        try {
            $client   = new \Google\Client();
            $client->setAccessToken(['access_token' => $accessToken, 'token_type' => 'Bearer']);
            $oauth    = new \Google\Service\Oauth2($client);
            $info     = $oauth->userinfo->get();
            return $info->getEmail();
        } catch (\Throwable) {
            return null;
        }
    }

    private function dispatchInitialSync(string $tenantId, string $userId): void
    {
        // Sync open tasks with due dates assigned to this user
        $tasks = Task::where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->where('assigned_to_type', 'tenant_user')
            ->where('assigned_to_id', $userId)
            ->whereNotNull('due_at')
            ->whereNotIn('status', ['completed', 'cancelled', 'archived'])
            ->pluck('id');

        foreach ($tasks as $taskId) {
            SyncEntityToGoogleCalendar::dispatch('task', $taskId);
        }

        // Sync active/expiring deals to admins
        $isAdmin = DB::table('tenant_memberships')
            ->where('tenant_user_id', $userId)
            ->where('tenant_id', $tenantId)
            ->whereIn('role', ['owner', 'admin', 'manager'])
            ->where('status', 'active')
            ->exists();

        if ($isAdmin) {
            $deals = DB::table('leads')
                ->where('tenant_id', $tenantId)
                ->whereIn('status', ['active', 'expiring'])
                ->where('days_left', '>', 0)
                ->whereNull('deleted_at')
                ->pluck('id');

            foreach ($deals as $dealId) {
                SyncEntityToGoogleCalendar::dispatch('deal', $dealId);
            }
        }
    }
}
