<?php

use App\Http\Controllers\Web\AuthWebController;
use App\Http\Controllers\Web\ContactsImportController;
use App\Http\Controllers\Web\LguIdsImportController;
use App\Http\Controllers\Web\TenantDealImportController;
use App\Http\Controllers\Web\PlatformController;
use App\Http\Controllers\Web\MessageController;
use App\Http\Controllers\Web\NotificationsController;
use App\Http\Controllers\Web\PlatformProfileController;
use App\Http\Controllers\Web\ResellerProfileController;
use App\Http\Controllers\Web\TenantAdminController;
use App\Http\Controllers\Web\TenantProfileController;
use App\Http\Controllers\Web\TenantSignupWebController;
use App\Http\Controllers\Web\TenantAuthWebController;
use App\Http\Controllers\Web\TenantExportController;
use App\Http\Controllers\ResellerPortalAuthController;
use App\Http\Controllers\ResellerPortalController;
use Illuminate\Support\Facades\Route;

// ── Auth ──────────────────────────────────────────────────────
Route::get('/login',  [AuthWebController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthWebController::class, 'login']);
Route::post('/logout',[AuthWebController::class, 'logout'])->name('logout');

// ── Root — always show portal selection (scrapers get OG landing) ────────
Route::get('/', function (\Illuminate\Http\Request $request) {
    // Social scrapers — return landing page with OG tags
    $ua       = $request->userAgent() ?? '';
    $scrapers = ['facebookexternalhit', 'Twitterbot', 'LinkedInBot', 'WhatsApp', 'Slackbot', 'TelegramBot'];
    foreach ($scrapers as $bot) {
        if (stripos($ua, $bot) !== false) {
            return response(view('landing'), 200)->header('X-Robots-Tag', 'all');
        }
    }
    // Everyone else — always show the portal selection screen
    return view('auth.portal-select');
})->name('home');

// ── Portal selection ──────────────────────────────────────────
Route::get('/select-portal', fn() => view('auth.portal-select'))->name('portal.select');
Route::get('/sign-in',       fn() => view('auth.signin-select'))->name('signin.select');

// ── Tenant Admin Auth ─────────────────────────────────────────
Route::get('/tenant/login',   [TenantAuthWebController::class, 'showLogin'])->name('tenant.login');
Route::post('/tenant/login',  [TenantAuthWebController::class, 'login'])->name('tenant.login.post');
Route::post('/tenant/logout', [TenantAuthWebController::class, 'logout'])->name('tenant.logout');

// ── Tenant Invite Acceptance (public — no auth required) ──────
use App\Http\Controllers\Web\TenantInvitationController;
Route::get('/tenant/accept-invite/{token}',  [TenantInvitationController::class, 'show'])->name('tenant.accept-invite.show');
Route::post('/tenant/accept-invite/{token}', [TenantInvitationController::class, 'accept'])->name('tenant.accept-invite')->middleware('throttle:10,1');

// ── Contact Role Invite Acceptance (public — no auth required) ─
Route::get('/tenant/accept-role-invite/{token}',  [\App\Http\Controllers\Web\ContactRoleInviteWebController::class, 'show'])->name('contact-role-invite.show');
Route::post('/tenant/accept-role-invite/{token}', [\App\Http\Controllers\Web\ContactRoleInviteWebController::class, 'accept'])->name('contact-role-invite.accept');

// ── Tenant Workspace Selector ─────────────────────────────────
Route::get('/tenant/select-workspace',  [TenantAuthWebController::class, 'selectWorkspace'])->name('tenant.select-workspace');
Route::post('/tenant/select-workspace', [TenantAuthWebController::class, 'chooseWorkspace'])->name('tenant.choose-workspace');

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
Route::post('/reseller/setup',         [ResellerPortalAuthController::class, 'setup'])->name('reseller.setup.post')->middleware('throttle:10,1');
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
        Route::get('/profile',           [ResellerProfileController::class, 'show'])->name('profile');
        Route::post('/profile',          [ResellerProfileController::class, 'update'])->name('profile.update');
        Route::post('/profile/photo',    [ResellerProfileController::class, 'updatePhoto'])->name('profile.photo');
        Route::delete('/profile/photo',  [ResellerProfileController::class, 'destroyPhoto'])->name('profile.photo.destroy');
        Route::get('/messages',       [ResellerPortalController::class, 'messages'])->name('messages');
        Route::get('/notifications',                    [ResellerPortalController::class, 'notifications'])->name('notifications');
        Route::post('/notifications/{id}/read',         [\App\Http\Controllers\NotificationController::class, 'markNotifRead'])->name('notifications.read');

        // ── Contacts Import (Reseller) ────────────────────────────
        Route::get('/contacts/imports',                             [ContactsImportController::class, 'index'])->name('contacts.imports');
        Route::get('/contacts/imports/template',                    [ContactsImportController::class, 'downloadTemplate'])->name('contacts.imports.template');
        Route::post('/contacts/imports/upload',                     [ContactsImportController::class, 'upload'])->name('contacts.imports.upload');
        Route::get('/contacts/imports/{batchId}',                   [ContactsImportController::class, 'preview'])->name('contacts.imports.preview');
        Route::post('/contacts/imports/{batchId}/rows/{rowId}',     [ContactsImportController::class, 'approveRow'])->name('contacts.imports.approve-row');
        Route::post('/contacts/imports/{batchId}/bulk-approve',     [ContactsImportController::class, 'bulkApprove'])->name('contacts.imports.bulk-approve');
        Route::post('/contacts/imports/{batchId}/execute',          [ContactsImportController::class, 'execute'])->name('contacts.imports.execute');
        Route::get('/contacts/imports/{batchId}/report',            [ContactsImportController::class, 'show'])->name('contacts.imports.show');
        Route::get('/contacts/imports/{batchId}/failed',            [ContactsImportController::class, 'downloadFailed'])->name('contacts.imports.failed');
    });

// ── Partner Auth ──────────────────────────────────────────────
use App\Http\Controllers\Web\PartnerAuthController;
use App\Http\Controllers\Web\PartnerPortalController;
use App\Http\Controllers\Web\PartnerProfileController;

Route::get('/partner/login',            [PartnerAuthController::class, 'showLogin'])->name('partner.login');
Route::post('/partner/login',           [PartnerAuthController::class, 'login'])->name('partner.login.post');
Route::post('/partner/logout',          [PartnerAuthController::class, 'logout'])->name('partner.logout');
Route::get('/partner/setup',            [PartnerAuthController::class, 'showSetup'])->name('partner.setup');
Route::post('/partner/setup',           [PartnerAuthController::class, 'setup'])->name('partner.setup.post');
Route::get('/partner/invite/{token}',   [PartnerAuthController::class, 'showInvite'])->name('partner.invite');
Route::get('/partner/forgot-password',  [PartnerAuthController::class, 'showForgotPassword'])->name('partner.forgot-password');
Route::post('/partner/forgot-password', [PartnerAuthController::class, 'forgotPassword'])->name('partner.forgot-password.post');
Route::get('/partner/reset-password',   [PartnerAuthController::class, 'showResetPassword'])->name('partner.reset-password');
Route::post('/partner/reset-password',  [PartnerAuthController::class, 'resetPassword'])->name('partner.reset-password.post');

// ── Partner Portal ────────────────────────────────────────────
Route::middleware(['auth:partner', 'partner.access'])
    ->prefix('partner')
    ->name('partner.')
    ->group(function () {
        Route::get('/dashboard',                      [PartnerPortalController::class, 'dashboard'])->name('dashboard');
        Route::get('/deals',                          [PartnerPortalController::class, 'deals'])->name('deals');
        Route::get('/deals/{dealId}',                 [PartnerPortalController::class, 'dealShow'])->name('deals.show');
        Route::get('/messages',                       [PartnerPortalController::class, 'messages'])->name('messages');
        Route::get('/messages/thread/{threadId}',     [PartnerPortalController::class, 'threadMessages'])->name('messages.thread');
        Route::post('/messages/send',                 [PartnerPortalController::class, 'sendMessage'])->name('messages.send')->middleware('throttle:60,1');
        Route::get('/profile',                        [PartnerProfileController::class, 'show'])->name('profile');
        Route::post('/profile',                       [PartnerProfileController::class, 'update'])->name('profile.update');
        Route::post('/profile/photo',                 [PartnerProfileController::class, 'updatePhoto'])->name('profile.photo');
        Route::delete('/profile/photo',               [PartnerProfileController::class, 'destroyPhoto'])->name('profile.photo.destroy');
    });

// ── Super Admin Profile ───────────────────────────────────────
Route::middleware('auth')->prefix('platform')->name('platform.')->group(function () {
    Route::get('/profile',           [PlatformProfileController::class, 'show'])->name('profile');
    Route::post('/profile',          [PlatformProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/photo',    [PlatformProfileController::class, 'updatePhoto'])->name('profile.photo');
    Route::delete('/profile/photo',  [PlatformProfileController::class, 'destroyPhoto'])->name('profile.photo.destroy');
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

// ── Messaging thread endpoints — accessible by tenant admins AND resellers ──
Route::middleware(['auth:tenant,reseller,web'])
    ->prefix('tenant/{tenantId}/messages')
    ->name('tenant.messages.')
    ->group(function () {
        Route::get('/threads',             [MessageController::class, 'threads'])->name('threads');
        Route::post('/threads',            [MessageController::class, 'startThread'])->name('start')->middleware('throttle:60,1');
        Route::get('/threads/{threadId}',  [MessageController::class, 'threadMessages'])->name('thread');
        Route::post('/threads/{threadId}', [MessageController::class, 'sendMessage'])->name('send')->middleware('throttle:60,1');
    });

// ── Tenant app ────────────────────────────────────────────────
use App\Http\Controllers\Web\TenantUserManagementController;
Route::middleware(['auth:tenant,web', 'tenant.access'])->prefix('tenant/{tenantId}')->name('tenant.')->group(function () {
    Route::get('/dashboard',       [TenantAdminController::class, 'dashboard'])->name('dashboard');
    Route::get('/critical-actions', [\App\Http\Controllers\Web\CriticalActionsController::class, 'index'])->name('critical-actions');
    Route::get('/deals',         [TenantAdminController::class, 'deals'])->name('deals');
    Route::get('/deals/{dealId}', [TenantAdminController::class, 'dealShow'])->name('deals.show');
    Route::get('/contacts',        [TenantAdminController::class, 'contacts'])->name('contacts');
    Route::get('/organizations',   [TenantAdminController::class, 'organizations'])->name('organizations');
    Route::get('/referrers',               [TenantAdminController::class, 'referrers'])->name('referrers');
    Route::get('/referrers/{referrerId}',  [TenantAdminController::class, 'referrerDetail'])->name('referrers.show');
    // Tasks
    Route::get('/tasks',                  [\App\Http\Controllers\Web\TaskController::class, 'index'])->name('tasks');
    Route::post('/tasks',                 [\App\Http\Controllers\Web\TaskController::class, 'store'])->name('tasks.store');
    Route::get('/tasks/eligible-assignees', [\App\Http\Controllers\Web\TaskController::class, 'eligibleAssignees'])->name('tasks.eligible-assignees');
    Route::get('/tasks/{taskId}',         [\App\Http\Controllers\Web\TaskController::class, 'show'])->name('tasks.show');
    Route::post('/tasks/{taskId}/assign-to-me',           [\App\Http\Controllers\Web\TaskController::class, 'assignToSelf'])->name('tasks.assign-to-me');
    Route::post('/tasks/{taskId}/complete',               [\App\Http\Controllers\Web\TaskController::class, 'complete'])->name('tasks.complete');
    Route::post('/tasks/{taskId}/complete-with-response', [\App\Http\Controllers\Web\TaskController::class, 'completeWithResponse'])->name('tasks.complete-response');

    // Request Forms
    Route::get('/request-forms',                [\App\Http\Controllers\Web\RequestFormController::class, 'index'])->name('request-forms');
    Route::get('/request-forms/create',         [\App\Http\Controllers\Web\RequestFormController::class, 'create'])->name('request-forms.create');
    Route::post('/request-forms',               [\App\Http\Controllers\Web\RequestFormController::class, 'store'])->name('request-forms.store');
    Route::get('/request-forms/{formId}/edit',   [\App\Http\Controllers\Web\RequestFormController::class, 'edit'])->name('request-forms.edit');
    Route::patch('/request-forms/{formId}',      [\App\Http\Controllers\Web\RequestFormController::class, 'update'])->name('request-forms.update');
    Route::post('/request-forms/{formId}/publish',    [\App\Http\Controllers\Web\RequestFormController::class, 'publish'])->name('request-forms.publish');
    Route::post('/request-forms/{formId}/unpublish',  [\App\Http\Controllers\Web\RequestFormController::class, 'unpublish'])->name('request-forms.unpublish');
    Route::post('/request-forms/{formId}/duplicate',  [\App\Http\Controllers\Web\RequestFormController::class, 'duplicate'])->name('request-forms.duplicate');
    Route::get('/request-forms/{formId}/submissions', [\App\Http\Controllers\Web\RequestFormController::class, 'submissions'])->name('request-forms.submissions');
    Route::get('/request-forms/{formId}/submissions/{submissionId}', [\App\Http\Controllers\Web\RequestFormController::class, 'submissionShow'])->name('request-forms.submissions.show');
    Route::delete('/request-forms/{formId}',    [\App\Http\Controllers\Web\RequestFormController::class, 'destroy'])->name('request-forms.destroy');
    Route::get('/messages',                              [TenantAdminController::class, 'messages'])->name('messages');
    Route::get('/reports',         [TenantAdminController::class, 'reports'])->name('reports');
    Route::get('/commission',      [\App\Http\Controllers\Web\TenantCommissionController::class, 'index'])->name('commission');
    Route::get('/commission/export', [\App\Http\Controllers\Web\TenantCommissionController::class, 'export'])->name('commission.export');
    Route::get('/imports',         [TenantAdminController::class, 'imports'])->name('imports');
    Route::get('/billing',         [TenantAdminController::class, 'billing'])->middleware('password.confirm')->name('billing');
    Route::get('/settings',        [TenantAdminController::class, 'settings'])->middleware('password.confirm')->name('settings');
    Route::post('/settings',       [TenantAdminController::class, 'updateSettings'])->middleware('throttle:20,1')->name('settings.update');

    // ── Export Approval Center ────────────────────────────────────
    Route::get('/exports',                           [\App\Http\Controllers\Web\TenantExportController::class, 'index'])->name('exports');
    Route::get('/exports/{exportId}',                [\App\Http\Controllers\Web\TenantExportController::class, 'show'])->name('exports.show');
    Route::get('/exports/{exportId}/download',       [\App\Http\Controllers\Web\TenantExportController::class, 'downloadWeb'])->name('exports.download');

    // ── User & Role Management ────────────────────────────────────
    Route::get('/users',                               [TenantUserManagementController::class, 'index'])->name('users');
    Route::post('/users/invite',                       [TenantUserManagementController::class, 'invite'])->name('users.invite');
    Route::post('/users/{userId}/permissions',         [TenantUserManagementController::class, 'updatePermissions'])->name('users.permissions');
    Route::post('/users/{userId}/billing-toggle',      [TenantUserManagementController::class, 'toggleBilling'])->name('users.billing-toggle');
    Route::post('/users/{userId}/deactivate',          [TenantUserManagementController::class, 'deactivateUser'])->name('users.deactivate');
    Route::delete('/users/{userId}',                   [TenantUserManagementController::class, 'removeUser'])->name('users.remove');
    Route::post('/invitations/{inviteId}/resend',        [TenantUserManagementController::class, 'resendInvite'])->name('invitations.resend');
    Route::post('/invitations/{inviteId}/remind-now',    [TenantUserManagementController::class, 'sendReminderNow'])->name('invitations.remind-now');
    Route::delete('/invitations/{inviteId}',             [TenantUserManagementController::class, 'revokeInvite'])->name('invitations.revoke');

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
    Route::get('/profile',              [TenantProfileController::class, 'show'])->name('profile');
    Route::post('/profile',             [TenantProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/photo',       [TenantProfileController::class, 'updatePhoto'])->name('profile.photo');
    Route::delete('/profile/photo',     [TenantProfileController::class, 'destroyPhoto'])->name('profile.photo.destroy');
    Route::post('/notifications/{notificationId}/read',    [NotificationsController::class, 'markRead'])->name('notifications.read');
    Route::post('/notifications/{notificationId}/archive', [NotificationsController::class, 'archive'])->name('notifications.archive');
    Route::post('/notifications/mark-all-read',            [NotificationsController::class, 'markAllRead'])->name('notifications.mark-all-read');

    // ── LGU IDS Deal Import ───────────────────────────────────────
    Route::get('/imports/lgu-ids',                         [LguIdsImportController::class, 'index'])->name('imports.lgu-ids');
    Route::get('/imports/lgu-ids/template',                [LguIdsImportController::class, 'downloadTemplate'])->name('imports.lgu-ids.template');
    Route::post('/imports/lgu-ids/upload',                 [LguIdsImportController::class, 'upload'])->name('imports.lgu-ids.upload')->middleware('throttle:20,60');
    Route::get('/imports/lgu-ids/{batchId}',               [LguIdsImportController::class, 'preview'])->name('imports.lgu-ids.preview');
    Route::post('/imports/lgu-ids/{batchId}/rows/{rowId}', [LguIdsImportController::class, 'approveRow'])->name('imports.lgu-ids.approve-row');
    Route::post('/imports/lgu-ids/{batchId}/bulk-approve', [LguIdsImportController::class, 'bulkApprove'])->name('imports.lgu-ids.bulk-approve');
    Route::post('/imports/lgu-ids/{batchId}/execute',      [LguIdsImportController::class, 'execute'])->name('imports.lgu-ids.execute');
    Route::get('/imports/lgu-ids/{batchId}/report',        [LguIdsImportController::class, 'show'])->name('imports.lgu-ids.show');
    Route::get('/imports/lgu-ids/{batchId}/failed',        [LguIdsImportController::class, 'downloadFailed'])->name('imports.lgu-ids.failed');

    // ── Generic Tenant Deal Import ────────────────────────────────
    // Blocked for lgu-ids at controller level (abort_if check in resolveTenant).
    Route::get('/imports/deals',                         [TenantDealImportController::class, 'index'])->name('imports.deals');
    Route::get('/imports/deals/template',                [TenantDealImportController::class, 'downloadTemplate'])->name('imports.deals.template');
    Route::post('/imports/deals/upload',                 [TenantDealImportController::class, 'upload'])->name('imports.deals.upload')->middleware('throttle:20,60');
    Route::get('/imports/deals/settings',                [TenantDealImportController::class, 'showSettings'])->name('imports.deals.settings');
    Route::post('/imports/deals/settings',               [TenantDealImportController::class, 'updateSettings'])->name('imports.deals.settings.update');
    Route::get('/imports/deals/{batchId}',               [TenantDealImportController::class, 'preview'])->name('imports.deals.preview');
    Route::post('/imports/deals/{batchId}/rows/{rowId}', [TenantDealImportController::class, 'approveRow'])->name('imports.deals.approve-row');
    Route::post('/imports/deals/{batchId}/bulk-approve', [TenantDealImportController::class, 'bulkApprove'])->name('imports.deals.bulk-approve');
    Route::post('/imports/deals/{batchId}/execute',      [TenantDealImportController::class, 'execute'])->name('imports.deals.execute');
    Route::get('/imports/deals/{batchId}/report',        [TenantDealImportController::class, 'show'])->name('imports.deals.show');
    Route::get('/imports/deals/{batchId}/failed',        [TenantDealImportController::class, 'downloadFailed'])->name('imports.deals.failed');

    // ── Import Rollback (Undo Import) — Tenant Admin only ────────
    Route::get('/imports/{batchId}/rollback/preview',
        [\App\Http\Controllers\Web\ImportRollbackController::class, 'preview'])->name('imports.rollback.preview');
    Route::post('/imports/{batchId}/rollback',
        [\App\Http\Controllers\Web\ImportRollbackController::class, 'store'])->name('imports.rollback.store');
    Route::get('/imports/{batchId}/rollback/{rollbackId}',
        [\App\Http\Controllers\Web\ImportRollbackController::class, 'show'])->name('imports.rollback.show');
    Route::get('/imports/{batchId}/rollback/{rollbackId}/status',
        [\App\Http\Controllers\Web\ImportRollbackController::class, 'status'])->name('imports.rollback.status');

    // ── Dynamic Column Handling ───────────────────────────────────
    Route::post('/imports/deals/{batchId}/column-actions',       [TenantDealImportController::class, 'saveColumnActions'])->name('imports.deals.column-actions');
    Route::post('/imports/deals/{batchId}/initiate-adoption',    [TenantDealImportController::class, 'initiateTemplateAdoption'])->name('imports.deals.initiate-adoption');
    Route::post('/imports/deals/{batchId}/verify-adoption',      [TenantDealImportController::class, 'verifyAndSaveTemplate'])->name('imports.deals.verify-adoption');
    // Same for contacts
    Route::post('/imports/contacts/{batchId}/column-actions',    [ContactsImportController::class, 'saveColumnActions'])->name('imports.contacts.column-actions');
    Route::post('/imports/contacts/{batchId}/initiate-adoption', [ContactsImportController::class, 'initiateTemplateAdoption'])->name('imports.contacts.initiate-adoption');
    Route::post('/imports/contacts/{batchId}/verify-adoption',   [ContactsImportController::class, 'verifyAndSaveTemplate'])->name('imports.contacts.verify-adoption');

    // ── Contacts Import ───────────────────────────────────────────
    Route::get('/imports/contacts',                           [ContactsImportController::class, 'index'])->name('imports.contacts');
    Route::get('/imports/contacts/template',                  [ContactsImportController::class, 'downloadTemplate'])->name('imports.contacts.template');
    Route::post('/imports/contacts/upload',                   [ContactsImportController::class, 'upload'])->name('imports.contacts.upload');
    Route::get('/imports/contacts/{batchId}',                 [ContactsImportController::class, 'preview'])->name('imports.contacts.preview');
    Route::post('/imports/contacts/{batchId}/rows/{rowId}',   [ContactsImportController::class, 'approveRow'])->name('imports.contacts.approve-row');
    Route::post('/imports/contacts/{batchId}/bulk-approve',   [ContactsImportController::class, 'bulkApprove'])->name('imports.contacts.bulk-approve');
    Route::post('/imports/contacts/{batchId}/execute',        [ContactsImportController::class, 'execute'])->name('imports.contacts.execute');
    Route::get('/imports/contacts/{batchId}/report',          [ContactsImportController::class, 'show'])->name('imports.contacts.show');
    Route::get('/imports/contacts/{batchId}/failed',          [ContactsImportController::class, 'downloadFailed'])->name('imports.contacts.failed');
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
        Route::get('/tasks',                     [\App\Http\Controllers\Web\TaskController::class, 'index'])->name('tenant.sub.tasks');
        Route::post('/tasks',                    [\App\Http\Controllers\Web\TaskController::class, 'store'])->name('tenant.sub.tasks.store');
        Route::get('/tasks/eligible-assignees',  [\App\Http\Controllers\Web\TaskController::class, 'eligibleAssignees'])->name('tenant.sub.tasks.eligible-assignees');
        Route::get('/tasks/{taskId}',            [\App\Http\Controllers\Web\TaskController::class, 'show'])->name('tenant.sub.tasks.show');
        Route::post('/tasks/{taskId}/assign-to-me', [\App\Http\Controllers\Web\TaskController::class, 'assignToSelf'])->name('tenant.sub.tasks.assign-to-me');
        Route::post('/tasks/{taskId}/complete',  [\App\Http\Controllers\Web\TaskController::class, 'complete'])->name('tenant.sub.tasks.complete');
        Route::post('/tasks/{taskId}/complete-with-response', [\App\Http\Controllers\Web\TaskController::class, 'completeWithResponse'])->name('tenant.sub.tasks.complete-response');
        Route::get('/request-forms',             [\App\Http\Controllers\Web\RequestFormController::class, 'index'])->name('tenant.sub.request-forms');
        Route::get('/messages',    [TenantAdminController::class, 'messages'])->name('tenant.sub.messages');
        Route::get('/reports',     [TenantAdminController::class, 'reports'])->name('tenant.sub.reports');
        Route::get('/imports',     [TenantAdminController::class, 'imports'])->name('tenant.sub.imports');
        Route::get('/users',       [TenantAdminController::class, 'users'])->name('tenant.sub.users');
        Route::get('/billing',     [TenantAdminController::class, 'billing'])->middleware('password.confirm')->name('tenant.sub.billing');
        Route::get('/settings',    [TenantAdminController::class, 'settings'])->middleware('password.confirm')->name('tenant.sub.settings');
        Route::post('/settings',   [TenantAdminController::class, 'updateSettings'])->middleware('throttle:20,1')->name('tenant.sub.settings.update');
    });

// ── Public Request Forms (no auth required) ──────────────────────────────────
Route::middleware(['throttle:30,1'])->group(function () {
    Route::get('/request/{token}',        [\App\Http\Controllers\PublicRequestFormController::class, 'show'])->name('public.request-form');
    Route::post('/request/{token}/submit',[\App\Http\Controllers\PublicRequestFormController::class, 'submit'])->name('public.request-form.submit')->middleware('throttle:5,1');
});
