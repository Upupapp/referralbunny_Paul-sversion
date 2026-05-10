<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\TenantAuthController;
use App\Http\Controllers\TenantInvitationController;
use App\Http\Controllers\TenantController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\ResellerController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\MessageReminderController;
use App\Http\Controllers\TenantMetricController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\FeatureAccessController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\PricingController;
use App\Http\Controllers\PromoCodeController;
use App\Http\Controllers\PromotionController;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\AgreementController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\RequiredDocumentController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\ExportController;
use Illuminate\Support\Facades\Route;

// ── Public routes ─────────────────────────────────────────────
Route::post('/auth/login', [AuthController::class, 'login']);

// ── Tenant Admin Auth (public) ────────────────────────────────
Route::post('/auth/tenant/login',    [TenantAuthController::class, 'login']);
Route::post('/auth/tenant/register', [TenantAuthController::class, 'register']);
Route::get('/auth/tenant/invite/{token}',          [TenantInvitationController::class, 'validate']);
Route::post('/auth/tenant/invite/{token}/accept',  [TenantInvitationController::class, 'accept']);
Route::post('/auth/tenant/join-request',           [TenantAuthController::class, 'joinRequest']);
Route::get('/auth/tenant/lookup-tenant',           [TenantAuthController::class, 'lookupTenant']);

// ── Protected routes ──────────────────────────────────────────
Route::middleware(['auth:sanctum', 'api.tenant'])->group(function () {

    // Auth — Super Admin
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me',      [AuthController::class, 'me']);

    // Auth — Tenant Admin (uses same sanctum guard; tokenable_type distinguishes)
    Route::post('/auth/tenant/logout',                [TenantAuthController::class, 'logout']);
    Route::get('/auth/tenant/me',                     [TenantAuthController::class, 'me']);
    Route::post('/auth/tenant/password/update',       [TenantAuthController::class, 'updatePassword']);
    Route::post('/auth/tenant/password/mark-reviewed',[TenantAuthController::class, 'markPasswordReviewed']);
    Route::post('/tenant-invitations',                [TenantInvitationController::class, 'store']);
    Route::delete('/tenant-invitations/{id}',         [TenantInvitationController::class, 'revoke']);

    // Tenants
    Route::apiResource('tenants', TenantController::class);
    Route::post('tenants/{tenant}/suspend',  [TenantController::class, 'suspend']);
    Route::post('tenants/{tenant}/activate', [TenantController::class, 'activate']);

    // Leads
    Route::get('leads',              [LeadController::class, 'index']);
    Route::post('leads',             [LeadController::class, 'store'])->middleware('feature.access:leads,create');
    Route::get('leads/{lead}',       [LeadController::class, 'show']);
    Route::put('leads/{lead}',       [LeadController::class, 'update']);
    Route::patch('leads/{lead}',     [LeadController::class, 'update']);
    Route::delete('leads/{lead}',    [LeadController::class, 'destroy']);
    Route::post('leads/{lead}/stage',             [LeadController::class, 'moveStage']);
    Route::post('leads/{lead}/notes',             [LeadController::class, 'addNote']);
    Route::post('leads/{lead}/reassign',          [LeadController::class, 'reassign']);
    Route::post('leads/{lead}/commission-splits', [LeadController::class, 'updateCommissionSplits']);
    // Deal Notes (comments with attachments + mentions)
    Route::get('deals/{dealId}/comments',                    [\App\Http\Controllers\DealCommentController::class, 'index']);
    Route::post('deals/{dealId}/comments',                   [\App\Http\Controllers\DealCommentController::class, 'store']);
    Route::patch('deals/{dealId}/comments/{commentId}',      [\App\Http\Controllers\DealCommentController::class, 'update']);
    Route::delete('deals/{dealId}/comments/{commentId}',     [\App\Http\Controllers\DealCommentController::class, 'destroy']);

    // Mention search — tenant-scoped, deal-scoped
    Route::get('deals/{dealId}/mentions/search', [\App\Http\Controllers\DealMentionSearchController::class, 'search']);

    // Note attachments — authorized download + delete
    Route::get('deals/{dealId}/comments/{commentId}/attachments/{attachmentId}',
        [\App\Http\Controllers\DealNoteAttachmentController::class, 'download']);
    Route::delete('deals/{dealId}/comments/{commentId}/attachments/{attachmentId}',
        [\App\Http\Controllers\DealNoteAttachmentController::class, 'destroy']);

    // Partner Splits
    Route::get('leads/{lead}/partner-splits',                [\App\Http\Controllers\DealPartnerSplitController::class, 'index']);
    Route::post('leads/{lead}/partner-splits',               [\App\Http\Controllers\DealPartnerSplitController::class, 'store']);
    Route::put('leads/{lead}/partner-splits/{splitId}',      [\App\Http\Controllers\DealPartnerSplitController::class, 'update']);
    Route::delete('leads/{lead}/partner-splits/{splitId}',   [\App\Http\Controllers\DealPartnerSplitController::class, 'destroy']);

    // Extension Requests
    Route::get('leads/{lead}/extension-requests',            [\App\Http\Controllers\DealAssignmentExtensionController::class, 'forDeal']);
    Route::post('leads/{lead}/extension-requests',           [\App\Http\Controllers\DealAssignmentExtensionController::class, 'store']);
    Route::get('extension-requests',                         [\App\Http\Controllers\DealAssignmentExtensionController::class, 'index']);
    Route::post('extension-requests/{id}/approve',           [\App\Http\Controllers\DealAssignmentExtensionController::class, 'approve']);
    Route::post('extension-requests/{id}/reject',            [\App\Http\Controllers\DealAssignmentExtensionController::class, 'reject']);
    Route::post('extension-requests/{id}/clarify',           [\App\Http\Controllers\DealAssignmentExtensionController::class, 'clarify']);

    // Resellers
    Route::get('resellers',                    [ResellerController::class, 'index']);
    Route::post('resellers',                   [ResellerController::class, 'store'])->middleware('feature.access:resellers,create');
    Route::get('resellers/activated-options',  [ResellerController::class, 'activatedOptions']);
    Route::get('resellers/check-email',        [ResellerController::class, 'checkEmail']);
    Route::get('resellers/summary',            [ResellerController::class, 'summary']);
    Route::post('resellers/add-referrer-role/{tenantUserId}', [ResellerController::class, 'addReferrerRole']);
    Route::get('resellers/{reseller}',         [ResellerController::class, 'show']);
    Route::put('resellers/{reseller}',         [ResellerController::class, 'update']);
    Route::patch('resellers/{reseller}',       [ResellerController::class, 'update']);
    Route::delete('resellers/{reseller}',      [ResellerController::class, 'destroy']);
    Route::post('resellers/{reseller}/deactivate', [ResellerController::class, 'deactivate']);

    // Messages
    Route::get('messages',           [MessageController::class, 'index']);
    Route::post('messages',          [MessageController::class, 'store'])->middleware('feature.access:messages,create');
    Route::get('messages/{message}', [MessageController::class, 'show']);
    Route::put('messages/{message}', [MessageController::class, 'update']);
    Route::patch('messages/{message}',[MessageController::class, 'update']);
    Route::delete('messages/{message}',[MessageController::class, 'destroy']);

    // Message reminders (R Bunny AI Dialog)
    Route::get('message-reminders/active',         [MessageReminderController::class, 'active']);
    Route::get('message-reminders/needs-reply',    [MessageReminderController::class, 'needsReply']);
    Route::get('message-reminders/suggestions',    [MessageReminderController::class, 'suggestedReplies']);
    Route::post('message-reminders/snooze',        [MessageReminderController::class, 'snooze']);
    Route::post('message-reminders/resolve',       [MessageReminderController::class, 'resolve']);
    Route::post('message-reminders/dismiss',       [MessageReminderController::class, 'dismiss']);

    // Notifications — specific routes MUST be before apiResource to avoid {notification} binding conflict
    Route::post('notifications/mark-all-read',           [NotificationController::class, 'markAllRead']);
    Route::get('notifications/unread-count',             [NotificationController::class, 'unreadCount']);
    Route::get('notifications/mine',                     [NotificationController::class, 'mine']);
    Route::get('notifications/mine/unread-count',        [NotificationController::class, 'mineUnreadCount']);
    Route::post('notifications/mine/mark-all-read',      [NotificationController::class, 'markMineRead']);
    Route::apiResource('notifications', NotificationController::class);

    // Analytics + Tenant Metrics
    Route::get('analytics/summary',                      [TenantMetricController::class, 'platformSummary']);
    Route::get('analytics/growth',                       [TenantMetricController::class, 'growth']);
    Route::get('analytics/funnel',                       [TenantMetricController::class, 'funnel']);
    Route::get('analytics/weekly-digest',                [TenantMetricController::class, 'weeklyDigest']);
    Route::get('analytics/monthly-report',               [TenantMetricController::class, 'monthlyReport']);
    Route::get('metrics',                                [TenantMetricController::class, 'index']);
    Route::get('metrics/{tenantId}',                     [TenantMetricController::class, 'show']);
    Route::post('metrics/{tenantId}/recalculate',        [TenantMetricController::class, 'recalculate']);

    // Legacy platform exports (super-admin only, direct download)
    Route::get('export/tenant-health',                   [TenantMetricController::class, 'exportHealth']);
    Route::get('export/notifications',                   [TenantMetricController::class, 'exportNotifications']);
    Route::get('export/leads',                           [TenantMetricController::class, 'exportLeads']);

    // ── Export Approval System ────────────────────────────────────
    Route::get('exports/settings',                       [ExportController::class, 'settings']);
    Route::put('exports/settings',                       [ExportController::class, 'updateSettings']);
    Route::get('exports',                                [ExportController::class, 'index']);
    Route::post('exports',                               [ExportController::class, 'store']);
    Route::get('exports/{id}',                           [ExportController::class, 'show']);
    Route::post('exports/{id}/approve',                  [ExportController::class, 'approve']);
    Route::post('exports/{id}/reject',                   [ExportController::class, 'reject']);
    Route::post('exports/{id}/cancel',                   [ExportController::class, 'cancel']);
    Route::get('exports/{id}/download',                  [ExportController::class, 'download']);

    // Billing
    Route::get('billing/dashboard',                      [BillingController::class, 'dashboard']);
    Route::get('billing/plans',                          [BillingController::class, 'plans']);
    Route::get('billing/exchange-rates',                 [BillingController::class, 'exchangeRates']);
    Route::put('billing/exchange-rates',                 [BillingController::class, 'updateExchangeRate']);
    Route::get('billing/invoices',                       [BillingController::class, 'invoices']);
    Route::get('billing/invoices/{invoice}',             [BillingController::class, 'invoice']);
    Route::post('billing/invoices',                      [BillingController::class, 'createInvoice']);
    Route::post('billing/invoices/{invoice}/waive',      [BillingController::class, 'waiveInvoice']);
    Route::post('billing/invoices/{invoice}/pay',        [BillingController::class, 'createPaymentIntent']);
    Route::get('billing/payments',                       [BillingController::class, 'payments']);
    Route::get('billing/refunds',                        [BillingController::class, 'refunds']);
    Route::post('billing/refunds',                       [BillingController::class, 'requestRefund']);
    Route::post('billing/credits',                       [BillingController::class, 'issueCredit']);
    Route::get('billing/audit-log',                      [BillingController::class, 'auditLog']);
    Route::get('billing/tenants/{tenantId}/subscription',[BillingController::class, 'tenantSubscription']);
    Route::post('billing/tenants/{tenantId}/trial',          [BillingController::class, 'activateTrial']);
    Route::post('billing/tenants/{tenantId}/suspend',        [BillingController::class, 'suspendTenant']);
    Route::post('billing/tenants/{tenantId}/extend-access',  [BillingController::class, 'extendAccess']);
    Route::post('billing/subscriptions/{subscription}/activate', [BillingController::class, 'activateSubscription']);
    Route::post('billing/subscriptions/{subscription}/cancel',   [BillingController::class, 'cancelSubscription']);
    // Super Admin: change tenant plan (requires double-auth in controller)
    Route::post('billing/tenants/{tenantId}/change-plan',    [BillingController::class, 'changeTenantPlan']);
    Route::get('billing/tenants/{tenantId}/plan-usage',      [BillingController::class, 'tenantPlanUsage']);
    Route::get('export/billing',                         [TenantMetricController::class, 'exportLeads']);

    // Pricing & Plans
    Route::get('pricing/plans',                      [PricingController::class, 'plans']);
    Route::get('pricing/plans/{plan}',               [PricingController::class, 'show']);
    Route::put('pricing/plans/{plan}',               [PricingController::class, 'update']);
    Route::put('pricing/plans/{plan}/price',         [PricingController::class, 'updatePrice']);
    Route::get('pricing/history',                    [PricingController::class, 'history']);

    // Promo Codes
    Route::get('promo-codes/performance',            [PromoCodeController::class, 'performance']);
    Route::post('promo-codes/validate',              [PromoCodeController::class, 'validate']);
    Route::post('promo-codes/apply',                 [PromoCodeController::class, 'apply']);
    Route::apiResource('promo-codes',                PromoCodeController::class);

    // Promotions
    Route::get('promotions/auto-apply',              [PromotionController::class, 'autoApply']);
    Route::apiResource('promotions',                 PromotionController::class);

    // Approvals
    Route::get('approvals',                          [ApprovalController::class, 'queue']);
    Route::get('approvals/history',                  [ApprovalController::class, 'history']);
    Route::get('approvals/{approval}',               [ApprovalController::class, 'show']);
    Route::post('approvals/{approval}/approve',      [ApprovalController::class, 'approve']);
    Route::post('approvals/{approval}/reject',       [ApprovalController::class, 'reject']);

    // Import Center
    Route::get('imports/stats',                            [ImportController::class, 'stats']);
    Route::get('imports/templates',                        [ImportController::class, 'listTemplates']);
    Route::get('imports/templates/{objectType}',           [ImportController::class, 'getTemplate']);
    Route::get('imports/templates/{objectType}/download',  [ImportController::class, 'downloadTemplate']);
    Route::get('imports/jobs',                             [ImportController::class, 'index']);
    Route::post('imports/jobs',                            [ImportController::class, 'create']);
    Route::get('imports/jobs/{job}',                       [ImportController::class, 'show']);
    Route::post('imports/jobs/{job}/parse',                [ImportController::class, 'parse']);
    Route::get('imports/jobs/{job}/mapping-suggestions',   [ImportController::class, 'mappingSuggestions']);
    Route::post('imports/jobs/{job}/mapping',              [ImportController::class, 'saveMapping']);
    Route::post('imports/jobs/{job}/validate',             [ImportController::class, 'validate']);
    Route::get('imports/jobs/{job}/preview',               [ImportController::class, 'preview']);
    Route::post('imports/jobs/{job}/execute',              [ImportController::class, 'execute']);
    Route::post('imports/jobs/{job}/cancel',               [ImportController::class, 'cancel']);
    Route::post('imports/jobs/{job}/rollback',             [ImportController::class, 'rollback']);
    Route::get('imports/jobs/{job}/rows',                  [ImportController::class, 'rows']);
    Route::get('imports/jobs/{job}/errors',                [ImportController::class, 'errors']);
    Route::get('imports/jobs/{job}/report/download',       [ImportController::class, 'downloadReport']);
    Route::get('imports/duplicates',                       [ImportController::class, 'duplicates']);
    Route::post('imports/duplicates/{item}/resolve',       [ImportController::class, 'resolveDuplicate']);
    Route::get('imports/mapping-profiles',                 [ImportController::class, 'mappingProfiles']);
    Route::delete('imports/mapping-profiles/{profile}',   [ImportController::class, 'deleteMappingProfile']);

    // Global Search
    Route::get('search',                       [SearchController::class, 'search']);
    Route::get('search/suggest',               [SearchController::class, 'suggest']);
    Route::get('search/recent',                [SearchController::class, 'recent']);
    Route::get('search/saved',                 [SearchController::class, 'savedSearches']);
    Route::post('search/saved',                [SearchController::class, 'saveSearch']);
    Route::delete('search/saved/{id}',         [SearchController::class, 'deleteSavedSearch']);
    Route::get('search/favorites',             [SearchController::class, 'getFavorites']);
    Route::post('search/favorites',            [SearchController::class, 'addFavorite']);
    Route::delete('search/favorites',          [SearchController::class, 'removeFavorite']);
    Route::post('search/actions',              [SearchController::class, 'executeAction']);
    Route::post('search/reindex',              [SearchController::class, 'reindex']);

    // Contacts
    Route::get('contacts',                                  [ContactController::class, 'index']);
    Route::post('contacts',                                 [ContactController::class, 'store']);
    Route::put('contacts/{id}',                            [ContactController::class, 'update']);
    Route::patch('contacts/{id}',                          [ContactController::class, 'update']);
    Route::delete('contacts/{id}',                         [ContactController::class, 'destroy']);
    Route::get('deals/{dealId}/contacts',                  [ContactController::class, 'forDeal']);
    Route::post('deals/{dealId}/contacts',                 [ContactController::class, 'linkToDeal']);
    Route::delete('deals/{dealId}/contacts/{contactId}',   [ContactController::class, 'unlinkFromDeal']);

    // Contact Role Assignments
    Route::get('contacts/{contactId}/role-assignments',    [\App\Http\Controllers\ContactRoleAssignmentController::class, 'index']);
    Route::post('contact-role-assignments',                [\App\Http\Controllers\ContactRoleAssignmentController::class, 'store']);
    Route::delete('contact-role-assignments/{id}',         [\App\Http\Controllers\ContactRoleAssignmentController::class, 'destroy']);
    Route::post('contact-role-assignments/{id}/resend',    [\App\Http\Controllers\ContactRoleAssignmentController::class, 'resend']);

    // Organizations
    Route::get('organizations',                            [OrganizationController::class, 'index']);
    Route::post('organizations',                           [OrganizationController::class, 'store']);
    Route::put('organizations/{id}',                      [OrganizationController::class, 'update']);
    Route::patch('organizations/{id}',                    [OrganizationController::class, 'update']);
    Route::delete('organizations/{id}',                   [OrganizationController::class, 'destroy']);

    // Required Documents (reseller KYC/verification)
    Route::get('required-documents/compliance',              [RequiredDocumentController::class, 'compliance']);
    Route::get('required-documents/reseller-status',         [RequiredDocumentController::class, 'resellerStatus']);
    Route::get('required-documents',                         [RequiredDocumentController::class, 'index']);
    Route::post('required-documents',                        [RequiredDocumentController::class, 'store']);
    Route::put('required-documents/{id}',                   [RequiredDocumentController::class, 'update']);
    Route::patch('required-documents/{id}',                 [RequiredDocumentController::class, 'update']);
    Route::delete('required-documents/{id}',                [RequiredDocumentController::class, 'destroy']);
    Route::post('required-documents/{docId}/submit',         [RequiredDocumentController::class, 'recordSubmission']);
    Route::patch('document-submissions/{submissionId}/review',[RequiredDocumentController::class, 'review']);
    Route::delete('document-submissions/{submissionId}',     [RequiredDocumentController::class, 'resetSubmission']);

    // Agreement Files
    Route::get('agreements/reseller-status',              [AgreementController::class, 'resellerStatus']);
    Route::get('agreements/compliance',                   [AgreementController::class, 'compliance']);
    Route::get('agreements',                              [AgreementController::class, 'index']);
    Route::post('agreements',                             [AgreementController::class, 'store']);
    Route::put('agreements/{id}',                        [AgreementController::class, 'update']);
    Route::delete('agreements/{id}',                     [AgreementController::class, 'destroy']);
    Route::post('agreements/{agreementId}/acknowledge',   [AgreementController::class, 'acknowledge']);
    Route::delete('agreements/{agreementId}/acknowledge', [AgreementController::class, 'revokeAcknowledgment']);

    // R Bunny on-site assistant
    Route::prefix('r-bunny')->group(function () {
        Route::get('status',             [\App\Http\Controllers\RBunnyController::class, 'status']);
        Route::post('dismiss',           [\App\Http\Controllers\RBunnyController::class, 'dismiss']);
        Route::post('snooze',            [\App\Http\Controllers\RBunnyController::class, 'snooze']);
        Route::post('handoff',           [\App\Http\Controllers\RBunnyController::class, 'handoff']);
        Route::get('preferences',        [\App\Http\Controllers\RBunnyController::class, 'preferences']);
        Route::post('preferences',       [\App\Http\Controllers\RBunnyController::class, 'updatePreferences']);
    });

    // Onboarding — works for all authenticated guards (web, tenant, reseller, partner)
    Route::prefix('onboarding')->group(function () {
        Route::get('status',             [\App\Http\Controllers\OnboardingController::class, 'status']);
        Route::post('start',             [\App\Http\Controllers\OnboardingController::class, 'start']);
        Route::post('advance',           [\App\Http\Controllers\OnboardingController::class, 'advance']);
        Route::post('complete',          [\App\Http\Controllers\OnboardingController::class, 'complete']);
        Route::post('skip',              [\App\Http\Controllers\OnboardingController::class, 'skip']);
        Route::post('task/complete',     [\App\Http\Controllers\OnboardingController::class, 'completeTask']);
        Route::post('task/dismiss',      [\App\Http\Controllers\OnboardingController::class, 'dismissTask']);
        Route::post('snooze',            [\App\Http\Controllers\OnboardingController::class, 'snooze']);
        Route::post('wake-up',           [\App\Http\Controllers\OnboardingController::class, 'wakeUp']);
    });

    // Feature Access Control
    Route::get('feature-access/usage',               [FeatureAccessController::class, 'usage']);
    Route::get('feature-access/check',               [FeatureAccessController::class, 'check']);
    Route::get('feature-access/limit',               [FeatureAccessController::class, 'limit']);
    Route::post('feature-access/grace-period',       [FeatureAccessController::class, 'startGracePeriod']);
    Route::post('feature-access/reset-usage',        [FeatureAccessController::class, 'resetUsage']);
    Route::get('tenant-overrides/{tenantId}',        [FeatureAccessController::class, 'listOverrides']);
    Route::post('tenant-overrides',                  [FeatureAccessController::class, 'createOverride']);
    Route::delete('tenant-overrides/{override}',     [FeatureAccessController::class, 'deleteOverride']);
});

// ── PayMongo Webhook (no auth — verified by signature) ────────
Route::post('webhooks/paymongo', [WebhookController::class, 'paymongo']);

// ── Development-only open routes (NOT available in production) ────
// These are gated to local/testing environments to avoid exposing unauthenticated
// endpoints in production. Remove this block entirely once proper seeding/testing
// workflows are in place.
if (app()->environment('local', 'testing')) {
    Route::get('/tenants',            [TenantController::class, 'index']);
    Route::get('/tenants/{tenant}',   [TenantController::class, 'show']);
    Route::get('/leads',              [LeadController::class, 'index']);
    Route::post('/leads',             [LeadController::class, 'store']);
    Route::get('/leads/{lead}',       [LeadController::class, 'show']);
    Route::put('/leads/{lead}',       [LeadController::class, 'update']);
    Route::patch('/leads/{lead}',     [LeadController::class, 'update']);
    Route::delete('/leads/{lead}',    [LeadController::class, 'destroy']);
    Route::get('/resellers',          [ResellerController::class, 'index']);
    Route::get('/messages',           [MessageController::class, 'index']);
    Route::get('/organizations',           [OrganizationController::class, 'index']);
    Route::get('/organizations/available', [OrganizationController::class, 'available']);
    Route::get('/contacts',           [ContactController::class, 'index']);
}
