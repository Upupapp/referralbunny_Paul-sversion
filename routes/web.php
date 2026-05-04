<?php

use App\Http\Controllers\Web\AuthWebController;
use App\Http\Controllers\Web\PlatformController;
use App\Http\Controllers\Web\TenantAdminController;
use App\Http\Controllers\Web\TenantAuthWebController;
use Illuminate\Support\Facades\Route;

// ── Auth ──────────────────────────────────────────────────────
Route::get('/login',  [AuthWebController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthWebController::class, 'login']);
Route::post('/logout',[AuthWebController::class, 'logout'])->name('logout');

// ── Redirect root to dashboard ────────────────────────────────
Route::get('/', fn() => redirect()->route('platform.dashboard'));

// ── Tenant Admin Auth ─────────────────────────────────────────
Route::get('/tenant/login',   [TenantAuthWebController::class, 'showLogin'])->name('tenant.login');
Route::post('/tenant/login',  [TenantAuthWebController::class, 'login'])->name('tenant.login.post');
Route::post('/tenant/logout', [TenantAuthWebController::class, 'logout'])->name('tenant.logout');

// Stub pages — shown as "coming soon" until full Blade views are built
Route::get('/tenant/create', fn() => view('auth.tenant-coming-soon', ['page' => 'Create Tenant']))->name('tenant.create');
Route::get('/tenant/join',   fn() => view('auth.tenant-coming-soon', ['page' => 'Join Tenant']))->name('tenant.join');
Route::get('/tenant/select', fn() => view('auth.tenant-coming-soon', ['page' => 'Select Workspace']))->name('tenant.select');
Route::get('/tenant/onboarding/{tenantId}', fn($tenantId) => redirect()->route('tenant.dashboard', $tenantId))->name('tenant.onboarding');
Route::get('/tenant/first-signin-password', fn() => view('auth.tenant-coming-soon', ['page' => 'Update Password']))->name('tenant.first-signin-password');

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
Route::middleware(['auth:tenant,web'])->prefix('tenant/{tenantId}')->name('tenant.')->group(function () {
    Route::get('/dashboard',       [TenantAdminController::class, 'dashboard'])->name('dashboard');
    Route::get('/deals',         [TenantAdminController::class, 'deals'])->name('deals');
    Route::get('/deals/{dealId}', [TenantAdminController::class, 'dealShow'])->name('deals.show');
    Route::get('/contacts',        [TenantAdminController::class, 'contacts'])->name('contacts');
    Route::get('/organizations',   [TenantAdminController::class, 'organizations'])->name('organizations');
    Route::get('/referrers',       [TenantAdminController::class, 'referrers'])->name('referrers');
    Route::get('/tasks',           [TenantAdminController::class, 'tasks'])->name('tasks');
    Route::get('/messages',        [TenantAdminController::class, 'messages'])->name('messages');
    Route::get('/reports',         [TenantAdminController::class, 'reports'])->name('reports');
    Route::get('/imports',         [TenantAdminController::class, 'imports'])->name('imports');
    Route::get('/users',           [TenantAdminController::class, 'users'])->name('users');
    Route::get('/billing',         [TenantAdminController::class, 'billing'])->name('billing');
    Route::get('/settings',        [TenantAdminController::class, 'settings'])->name('settings');

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
});
