<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\Notification;
use App\Models\TenantMetric;
use App\Models\UsageMetric;
use App\Models\ApprovalRequest;
use App\Models\BillingAuditLog;
use App\Models\Lead;
use App\Services\AnalyticsService;
use App\Services\BillingService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PlatformController extends Controller
{
    public function dashboard(AnalyticsService $analytics, BillingService $billing)
    {
        $totalTenants = Tenant::count();
        $tenants = Tenant::latest()->limit(20)->get();
        $summary = $analytics->platformSummary();

        $industryData = Tenant::selectRaw('industry, count(*) as count')
            ->whereNotNull('industry')
            ->groupBy('industry')
            ->orderByDesc('count')
            ->get();

        $saUserId = (string) auth('web')->id();
        $recentNotifications = Notification::where('notifiable_type', 'super_admin')
            ->where('notifiable_id', $saUserId)
            ->where('is_read', false)
            ->where('is_dismissed', false)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        $billingSummary  = $billing->dashboardSummary();
        $approvalCount   = ApprovalRequest::where('status', 'pending')->count();
        $importStats     = [
            'active_jobs'      => \App\Models\ImportJob::whereIn('status', ['importing','validating_rows','parsing'])->count(),
            'pending_approval' => \App\Models\ImportJob::where('status', 'waiting_for_approval')->count(),
        ];

        // Health breakdown with metrics
        $healthMetrics = TenantMetric::selectRaw('health_level, count(*) as count, avg(health_score) as avg_score')
            ->groupBy('health_level')
            ->get()
            ->keyBy('health_level');

        // Manually extended access stats
        $extendedTenantsCount = BillingAuditLog::where('action', 'access_extended')
            ->distinct()
            ->count('tenant_id');
        $totalExtensions = BillingAuditLog::where('action', 'access_extended')->count();

        return view('platform.dashboard', [
            'tenants'             => $tenants,
            'stats'               => $summary,
            'industryData'        => $industryData,
            'totalTenants'        => $totalTenants,
            'recentNotifications' => $recentNotifications,
            'billing'             => $billingSummary,
            'approvalCount'       => $approvalCount,
            'healthMetrics'          => $healthMetrics,
            'importStats'            => $importStats,
            'extendedTenantsCount'   => $extendedTenantsCount,
            'totalExtensions'        => $totalExtensions,
        ]);
    }

    public function tenants(Request $request)
    {
        $query = Tenant::with(['metric'])->latest();

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'ilike', "%{$request->search}%")
                  ->orWhere('admin_email', 'ilike', "%{$request->search}%");
            });
        }
        if ($request->filled('status'))   $query->where('status', $request->status);
        if ($request->filled('industry'))  $query->where('industry', $request->industry);

        if ($request->filled('health_level')) {
            $query->whereHas('metric', fn($q) => $q->where('health_level', $request->health_level));
        }

        $tenants    = $query->paginate(15)->withQueryString();
        $industries = Tenant::whereNotNull('industry')->distinct()->pluck('industry')->sort();

        return view('platform.tenants.index', compact('tenants', 'industries'));
    }

    public function showTenant(Tenant $tenant)
    {
        $tenant->load(['config', 'subIndustries', 'resellers']);
        $leads   = Lead::where('tenant_id', $tenant->id)->latest()->limit(50)->get();
        $metric  = TenantMetric::where('tenant_id', $tenant->id)->first();
        $notifications = Notification::where('tenant_id', $tenant->id)
            ->orderByDesc('created_at')->limit(10)->get();

        return view('platform.tenants.show', compact('tenant', 'leads', 'metric', 'notifications'));
    }

    public function createTenant()
    {
        return view('platform.tenants.create');
    }

    public function storeTenant(Request $request)
    {
        $data = $request->validate([
            'name'                => 'required|string|max:200',
            'program_name'        => 'required|string|max:200',
            'business_name'       => 'nullable|string|max:200',
            'industry'            => 'required|string',
            'description'         => 'nullable|string',
            'contact_person'      => 'nullable|string|max:200',
            'contact_email'       => 'nullable|email',
            'contact_phone'       => 'nullable|string|max:50',
            'admin_name'          => 'required|string|max:200',
            'admin_email'         => 'required|email',
            'accent_color'        => 'nullable|string|max:7',
            'preferred_currency'  => 'nullable|string|max:3',
        ]);

        $tenant = Tenant::create([
            'id'                 => (string) Str::uuid(),
            'name'               => $data['name'],
            'slug'               => Str::slug($data['name']) . '-' . Str::random(4),
            'program_name'       => $data['program_name'],
            'business_name'      => $data['business_name'] ?? null,
            'industry'           => $data['industry'],
            'description'        => $data['description'] ?? null,
            'contact_person'     => $data['contact_person'] ?? null,
            'contact_email'      => $data['contact_email'] ?? null,
            'contact_phone'      => $data['contact_phone'] ?? null,
            'admin_name'         => $data['admin_name'],
            'admin_email'        => $data['admin_email'],
            'accent_color'       => $data['accent_color'] ?? '#7B61FF',
            'status'             => 'trial',
        ]);

        // Seed initial metrics
        \App\Models\TenantMetric::firstOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'id'                          => (string) Str::uuid(),
                'health_score'                => 60,
                'health_level'                => 'needs_attention',
                'setup_completion_percentage' => 20,
                'subscription_status'         => 'trial',
            ]
        );

        UsageMetric::firstOrCreate(
            ['tenant_id' => $tenant->id, 'billing_period_start' => now()->startOfMonth()->toDateString()],
            [
                'id'                 => (string) Str::uuid(),
                'billing_period_end' => now()->endOfMonth()->toDateString(),
            ]
        );

        return redirect()->route('platform.tenants.show', $tenant->id)
            ->with('success', "Tenant '{$tenant->name}' created successfully.")
            ->with('tenant_just_created', true);
    }

    public function messaging()
    {
        $tenants  = Tenant::orderBy('name')->get();
        $messages = \App\Models\Message::orderByDesc('created_at')->limit(50)->get();
        return view('platform.messaging', compact('tenants', 'messages'));
    }

    public function templates()
    {
        return view('platform.templates');
    }

    public function search(\Illuminate\Http\Request $request)
    {
        return view('platform.search', ['initialQuery' => $request->get('q', '')]);
    }

    public function import()
    {
        $stats = [
            'active_jobs'        => \App\Models\ImportJob::whereIn('status', ['importing','validating_rows','parsing'])->count(),
            'pending_approval'   => \App\Models\ImportJob::where('status','waiting_for_approval')->count(),
            'failed_jobs'        => \App\Models\ImportJob::where('status','failed')->whereDate('created_at','>=',now()->subDays(7))->count(),
            'pending_duplicates' => \App\Models\DuplicateReviewItem::where('status','pending')->count(),
        ];
        return view('platform.import.index', compact('stats'));
    }

    public function importShow(\App\Models\ImportJob $job)
    {
        return view('platform.import.show', compact('job'));
    }

    public function billing()
    {
        $summary = app(\App\Services\BillingService::class)->dashboardSummary();
        $invoices = \App\Models\Invoice::with('tenant')->orderByDesc('created_at')->limit(20)->get();
        $payments = \App\Models\Payment::with('tenant')->orderByDesc('created_at')->limit(20)->get();
        return view('platform.billing', compact('summary', 'invoices', 'payments'));
    }
}
