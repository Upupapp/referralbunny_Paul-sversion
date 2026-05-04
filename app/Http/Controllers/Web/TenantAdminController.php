<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Tenant;
use App\Models\TenantConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TenantAdminController extends Controller
{
    private function config(string $tenantId): ?TenantConfig
    {
        return TenantConfig::where('tenant_id', $tenantId)->first();
    }

    private function configMeta(string $tenantId): array
    {
        $cfg    = $this->config($tenantId);
        $fields = collect($cfg?->fields ?? []);
        return [
            'config'       => $cfg,
            'leadLabel'    => $cfg?->lead_label ?? 'Deal',
            'showLocation' => $fields->contains('key', 'province') && $fields->contains('key', 'municipality'),
        ];
    }

    public function dashboard($tenantId)
    {
        $tenant = Tenant::findOrFail($tenantId);
        $tenant->load(['metric']);
        $metric = $tenant->metric;

        $accessExtendedNotif = Notification::where('tenant_id', $tenantId)
            ->where('type', 'access_extended')
            ->where('is_dismissed', false)
            ->where('is_read', false)
            ->latest()
            ->first();

        // Daily dashboard briefing — shown once per calendar day
        $seenTodayKey  = "dash_seen_{$tenantId}_" . now()->format('Y-m-d');
        $lastSeenKey   = "dash_last_seen_{$tenantId}";
        $dailyBriefing = null;

        if (!session()->has($seenTodayKey)) {
            // "Since" = last time the briefing was shown (default: 24h ago on first visit)
            $since = session($lastSeenKey, now()->subHours(24));

            // 1. Expiring deals
            $expiringDeals = DB::table('leads')
                ->where('tenant_id', $tenantId)
                ->where('status', 'expiring')
                ->select('id', 'name', 'days_left', 'deal_value', 'reseller_name')
                ->orderBy('days_left')
                ->get();

            // 2. New deals since last visit
            $newDeals = DB::table('leads')
                ->where('tenant_id', $tenantId)
                ->where('created_at', '>', $since)
                ->select('id', 'name', 'stage', 'deal_value', 'reseller_name', 'created_at')
                ->orderByDesc('created_at')
                ->get();

            // 3. New resellers invited since last visit (status = invited, pending acceptance)
            $newInvited = DB::table('resellers')
                ->where('tenant_id', $tenantId)
                ->where('status', 'invited')
                ->where('created_at', '>', $since)
                ->select('id', 'name', 'email', 'created_at')
                ->orderByDesc('created_at')
                ->get();

            // 4. Resellers who accepted invite and became active since last visit
            $newActive = DB::table('resellers')
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->where('updated_at', '>', $since)
                ->select('id', 'name', 'email', 'joined_date', 'updated_at')
                ->orderByDesc('updated_at')
                ->get();

            // Only show if at least one section has data
            if ($expiringDeals->isNotEmpty() || $newDeals->isNotEmpty() || $newInvited->isNotEmpty() || $newActive->isNotEmpty()) {
                $dailyBriefing = compact('expiringDeals', 'newDeals', 'newInvited', 'newActive');
            }

            session()->put($seenTodayKey, true);
            session()->put($lastSeenKey, now());
        }

        // Keep old variable name for backwards compat with any other references
        $expiryAlert = null;

        return view('tenant.dashboard', array_merge(
            compact('tenant', 'metric', 'accessExtendedNotif', 'expiryAlert', 'dailyBriefing'),
            $this->configMeta($tenantId)
        ));
    }

    public function deals($tenantId)
    {
        $tenant = Tenant::findOrFail($tenantId);
        return view('tenant.deals.index', array_merge(['tenant' => $tenant], $this->configMeta($tenantId)));
    }

    public function dealShow($tenantId, $dealId)
    {
        $tenant = Tenant::findOrFail($tenantId);
        return view('tenant.deals.show', array_merge(['tenant' => $tenant, 'dealId' => $dealId], $this->configMeta($tenantId)));
    }

    public function contacts($tenantId)
    {
        $tenant = Tenant::findOrFail($tenantId);
        return view('tenant.contacts.index', compact('tenant'));
    }

    public function organizations($tenantId)
    {
        $tenant = Tenant::findOrFail($tenantId);
        return view('tenant.organizations.index', compact('tenant'));
    }

    public function referrers($tenantId)
    {
        $tenant = Tenant::findOrFail($tenantId);
        return view('tenant.referrers.index', array_merge(
            compact('tenant'),
            $this->configMeta($tenantId)
        ));
    }

    public function tasks($tenantId)
    {
        $tenant = Tenant::findOrFail($tenantId);
        return view('tenant.tasks.index', compact('tenant'));
    }

    public function messages($tenantId)
    {
        $tenant = Tenant::findOrFail($tenantId);
        return view('tenant.messages.index', compact('tenant'));
    }

    public function reports($tenantId)
    {
        $tenant = Tenant::findOrFail($tenantId);
        return view('tenant.reports.index', array_merge(['tenant' => $tenant], $this->configMeta($tenantId)));
    }

    public function imports($tenantId)
    {
        $tenant = Tenant::findOrFail($tenantId);
        return view('tenant.imports.index', compact('tenant'));
    }

    public function users($tenantId)
    {
        $tenant = Tenant::findOrFail($tenantId);
        return view('tenant.users.index', compact('tenant'));
    }

    public function billing($tenantId)
    {
        $tenant = Tenant::findOrFail($tenantId);
        return view('tenant.billing.index', compact('tenant'));
    }

    public function settings($tenantId)
    {
        $tenant = Tenant::findOrFail($tenantId);
        return view('tenant.settings.index', compact('tenant'));
    }
}
