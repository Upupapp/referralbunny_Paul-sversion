<?php

use App\Http\Controllers\Web\AuthWebController;
use App\Http\Controllers\Web\PlatformController;
use App\Http\Controllers\Web\MessageController;
use App\Http\Controllers\Web\NotificationsController;
use App\Http\Controllers\Web\TenantAdminController;
use App\Http\Controllers\Web\TenantSignupWebController;
use App\Http\Controllers\Web\TenantAuthWebController;
use App\Http\Controllers\ResellerPortalAuthController;
use App\Http\Controllers\ResellerPortalController;
use Illuminate\Support\Facades\Route;

// ── Auth ──────────────────────────────────────────────────────
Route::get('/login',  [AuthWebController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthWebController::class, 'login']);
Route::post('/logout',[AuthWebController::class, 'logout'])->name('logout');

// ── Root — public landing (scrapers see OG tags; users get redirected) ───
Route::get('/', function (\Illuminate\Http\Request $request) {
    // Social scrapers — always return landing with OG tags
    $ua = $request->userAgent() ?? '';
    $scrapers = ['facebookexternalhit', 'Twitterbot', 'LinkedInBot', 'WhatsApp', 'Slackbot', 'TelegramBot'];
    foreach ($scrapers as $bot) {
        if (stripos($ua, $bot) !== false) {
            return response(view('landing'), 200)
                ->header('X-Robots-Tag', 'all');
        }
    }
    // Authenticated tenant admin → their portal
    if (auth('tenant')->check()) {
        $membership = \App\Models\TenantMembership::where('tenant_user_id', auth('tenant')->id())
            ->where('status', 'active')->first();
        return $membership
            ? redirect()->route('tenant.dashboard', $membership->tenant_id)
            : redirect()->route('tenant.login');
    }
    // Authenticated super admin → platform
    if (auth('web')->check()) {
        return redirect()->route('platform.dashboard');
    }
    // Regular guest → show portal selection directly (no extra redirect)
    return view('auth.portal-select');
})->name('home');

// ── Portal selection ──────────────────────────────────────────
Route::get('/select-portal', fn() => view('auth.portal-select'))->name('portal.select');

// ── Tenant Admin Auth ─────────────────────────────────────────
Route::get('/tenant/login',   [TenantAuthWebController::class, 'showLogin'])->name('tenant.login');
Route::post('/tenant/login',  [TenantAuthWebController::class, 'login'])->name('tenant.login.post');
Route::post('/tenant/logout', [TenantAuthWebController::class, 'logout'])->name('tenant.logout');

// ── Build a Referral Program (Tenant Signup) ─────────────────
Route::get('/tenant/create',  [TenantSignupWebController::class, 'showBuild'])->name('tenant.create');
Route::post('/tenant/create', [TenantSignupWebController::class, 'build'])->name('tenant.create.post');

// ── Join a Referral Program ───────────────────────────────────
Route::get('/tenant/join',         [TenantSignupWebController::class, 'showJoin'])->name('tenant.join');
Route::get('/join-referral-program', [TenantSignupWebController::class, 'showJoin'])->name('join.program');
Route::get('/tenant/invite/{token}', [TenantSignupWebController::class, 'showInvite'])->name('tenant.invite.show');

// ── Other tenant stubs ────────────────────────────────────────
Route::get('/tenant/select', fn() => view('auth.tenant-coming-soon', ['page' => 'Select Workspace']))->name('tenant.select');
Route::get('/tenant/onboarding/{tenantId}', fn($tenantId) => redirect()->route('tenant.dashboard', $tenantId))->name('tenant.onboarding');
Route::get('/tenant/first-signin-password', fn() => view('auth.tenant-coming-soon', ['page' => 'Update Password']))->name('tenant.first-signin-password');

// ── Reseller Auth ────────────────────────────────────────────
Route::get('/reseller/login',  [ResellerPortalAuthController::class, 'showLogin'])->name('reseller.login');
Route::post('/reseller/login', [ResellerPortalAuthController::class, 'login'])->name('reseller.login.post');
Route::post('/reseller/logout',[ResellerPortalAuthController::class, 'logout'])->name('reseller.logout');
Route::get('/reseller/setup',          [ResellerPortalAuthController::class, 'showSetup'])->name('reseller.setup');
Route::post('/reseller/setup',         [ResellerPortalAuthController::class, 'setup'])->name('reseller.setup.post');
Route::get('/reseller/forgot-password',[ResellerPortalAuthController::class, 'showForgotPassword'])->name('reseller.forgot-password');
Route::post('/reseller/forgot-password',[ResellerPortalAuthController::class, 'forgotPassword'])->name('reseller.forgot-password.post');
Route::get('/reseller/reset-password', [ResellerPortalAuthController::class, 'showResetPassword'])->name('reseller.reset-password');
Route::post('/reseller/reset-password',[ResellerPortalAuthController::class, 'resetPassword'])->name('reseller.reset-password.post');

// ── Reseller Portal ────────────────────────────────────────────
Route::middleware(['auth:reseller,web', 'reseller.access'])
    ->prefix('reseller/{tenantId}')
    ->name('reseller.')
    ->group(function () {
        Route::get('/dashboard',  [ResellerPortalController::class, 'dashboard'])->name('dashboard');
        Route::get('/deals',      [ResellerPortalController::class, 'deals'])->name('deals');
        Route::get('/commission', [ResellerPortalController::class, 'commission'])->name('commission');
        Route::get('/profile',    [ResellerPortalController::class, 'profile'])->name('profile');
    });

// ── Platform (Super Admin) ────────────────────────────────────
Route::middleware('auth')->prefix('platform')->name('platform.')->group(function () {
    Route::get('/dashboard',           [PlatformController::class, 'dashboard'])->name('dashboard');
    Route::get('/tenants',             [PlatformController::class, 'tenants'])->name('tenants');
    Route::get('/tenants/create',      [PlatformController::class, 'createTenant'])->name('tenants.create');
    Route::post('/tenants',            [PlatformController::class, 'storeTenant'])->name('tenants.store');
    Route::get('/tenants/{tenant}',    [PlatformController::class, 'showTenant'])->name('tenants.show');
    Route::get('/messaging',           [PlatformController::class, 'messaging'])->name('messaging');
    Route::get('/templates',           [PlatformController::class, 'templates'])->name('templates');
    Route::get('/billing',             [PlatformController::class, 'billing'])->name('billing');
    Route::get('/search',              [PlatformController::class, 'search'])->name('search');
    Route::get('/import',              [PlatformController::class, 'import'])->name('import');
    Route::get('/import/{job}',        [PlatformController::class, 'importShow'])->name('import.show');
});

// ── Tenant app ────────────────────────────────────────────────
Route::middleware(['auth:tenant,web', 'tenant.access'])->prefix('tenant/{tenantId}')->name('tenant.')->group(function () {
    Route::get('/dashboard',       [TenantAdminController::class, 'dashboard'])->name('dashboard');
    Route::get('/deals',         [TenantAdminController::class, 'deals'])->name('deals');
    Route::get('/deals/{dealId}', [TenantAdminController::class, 'dealShow'])->name('deals.show');
    Route::get('/contacts',        [TenantAdminController::class, 'contacts'])->name('contacts');
    Route::get('/organizations',   [TenantAdminController::class, 'organizations'])->name('organizations');
    Route::get('/referrers',       [TenantAdminController::class, 'referrers'])->name('referrers');
    Route::get('/tasks',           [TenantAdminController::class, 'tasks'])->name('tasks');
    Route::get('/messages',                              [TenantAdminController::class, 'messages'])->name('messages');
    Route::get('/messages/threads',                      [MessageController::class, 'threads'])->name('messages.threads');
    Route::post('/messages/threads',                     [MessageController::class, 'startThread'])->name('messages.start');
    Route::get('/messages/threads/{threadId}',           [MessageController::class, 'threadMessages'])->name('messages.thread');
    Route::post('/messages/threads/{threadId}',          [MessageController::class, 'sendMessage'])->name('messages.send');
    Route::get('/reports',         [TenantAdminController::class, 'reports'])->name('reports');
    Route::get('/imports',         [TenantAdminController::class, 'imports'])->name('imports');
    Route::get('/users',           [TenantAdminController::class, 'users'])->name('users');
    Route::get('/billing',         [TenantAdminController::class, 'billing'])->middleware('password.confirm')->name('billing');
    Route::get('/settings',        [TenantAdminController::class, 'settings'])->middleware('password.confirm')->name('settings');

    Route::get('/leads', function($tenantId) {
        $tenant = \App\Models\Tenant::findOrFail($tenantId);
        $cfg    = \App\Models\TenantConfig::where('tenant_id', $tenantId)->first();
        $fields = collect($cfg?->fields ?? []);
        return view('tenant.leads.index', [
            'tenantId'     => $tenantId,
            'tenant'       => $tenant,
            'config'       => $cfg,
            'leadLabel'    => $cfg?->lead_label ?? 'Lead',
            'showLocation' => $fields->contains('key', 'province') && $fields->contains('key', 'municipality'),
        ]);
    })->name('leads');
    Route::get('/leads/{leadId}', fn($tenantId, $leadId) => view('tenant.leads.show', ['tenantId' => $tenantId, 'leadId' => $leadId, 'tenant' => \App\Models\Tenant::findOrFail($tenantId)]))->name('leads.show');
    Route::get('/resellers',      fn($tenantId) => view('tenant.resellers.index', ['tenantId' => $tenantId, 'tenant' => \App\Models\Tenant::findOrFail($tenantId)]))->name('resellers');
    Route::get('/notifications',  [NotificationsController::class, 'index'])->name('notifications');
    Route::post('/notifications/{notificationId}/read',    [NotificationsController::class, 'markRead'])->name('notifications.read');
    Route::post('/notifications/{notificationId}/archive', [NotificationsController::class, 'archive'])->name('notifications.archive');
    Route::post('/notifications/mark-all-read',            [NotificationsController::class, 'markAllRead'])->name('notifications.mark-all-read');
});

// ── Subdomain tenant routes: {slug}.referralbunny.ai ─────────
// These mirror the /tenant/{tenantId}/* routes above.
// DetectTenantSubdomain injects tenantId from subdomain lookup.
Route::domain('{subdomain}.' . config('app.domain', 'referralbunny.ai'))
    ->middleware(['tenant.subdomain', 'auth:tenant,web', 'tenant.access'])
    ->group(function () {
        Route::get('/',            [TenantAdminController::class, 'dashboard'])->name('tenant.sub.home');
        Route::get('/dashboard',   [TenantAdminController::class, 'dashboard'])->name('tenant.sub.dashboard');
        Route::get('/deals',       [TenantAdminController::class, 'deals'])->name('tenant.sub.deals');
        Route::get('/deals/{dealId}', [TenantAdminController::class, 'dealShow'])->name('tenant.sub.deals.show');
        Route::get('/contacts',    [TenantAdminController::class, 'contacts'])->name('tenant.sub.contacts');
        Route::get('/organizations',[TenantAdminController::class, 'organizations'])->name('tenant.sub.organizations');
        Route::get('/referrers',   [TenantAdminController::class, 'referrers'])->name('tenant.sub.referrers');
        Route::get('/tasks',       [TenantAdminController::class, 'tasks'])->name('tenant.sub.tasks');
        Route::get('/messages',    [TenantAdminController::class, 'messages'])->name('tenant.sub.messages');
        Route::get('/reports',     [TenantAdminController::class, 'reports'])->name('tenant.sub.reports');
        Route::get('/imports',     [TenantAdminController::class, 'imports'])->name('tenant.sub.imports');
        Route::get('/users',       [TenantAdminController::class, 'users'])->name('tenant.sub.users');
        Route::get('/billing',     [TenantAdminController::class, 'billing'])->middleware('password.confirm')->name('tenant.sub.billing');
        Route::get('/settings',    [TenantAdminController::class, 'settings'])->middleware('password.confirm')->name('tenant.sub.settings');
    });
