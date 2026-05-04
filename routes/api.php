<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\TenantAuthController;
use App\Http\Controllers\TenantInvitationController;
use App\Http\Controllers\TenantController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\ResellerController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\NotificationController;
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
Route::middleware('auth:sanctum')->group(function () {

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

    // Resellers
    Route::get('resellers',           [ResellerController::class, 'index']);
    Route::post('resellers',          [ResellerController::class, 'store'])->middleware('feature.access:resellers,create');
    Route::get('resellers/{reseller}',[ResellerController::class, 'show']);
    Route::put('resellers/{reseller}',[ResellerController::class, 'update']);
    Route::patch('resellers/{reseller}',[ResellerController::class, 'update']);
    Route::delete('resellers/{reseller}',[ResellerController::class, 'destroy']);

    // Messages
    Route::get('messages',           [MessageController::class, 'index']);
    Route::post('messages',          [MessageController::class, 'store'])->middleware('feature.access:messages,create');
    Route::get('messages/{message}', [MessageController::class, 'show']);
    Route::put('messages/{message}', [MessageController::class, 'update']);
    Route::patch('messages/{message}',[MessageController::class, 'update']);
    Route::delete('messages/{message}',[MessageController::class, 'destroy']);

    // Notifications
    Route::apiResource('notifications', NotificationController::class);
    Route::post('notifications/mark-all-read',           [NotificationController::class, 'markAllRead']);
    Route::get('notifications/unread-count',             [NotificationController::class, 'unreadCount']);

    // Analytics + Tenant Metrics
    Route::get('analytics/summary',                      [TenantMetricController::class, 'platformSummary']);
    Route::get('analytics/growth',                       [TenantMetricController::class, 'growth']);
    Route::get('analytics/funnel',                       [TenantMetricController::class, 'funnel']);
    Route::get('analytics/weekly-digest',                [TenantMetricController::class, 'weeklyDigest']);
    Route::get('analytics/monthly-report',               [TenantMetricController::class, 'monthlyReport']);
    Route::get('metrics',                                [TenantMetricController::class, 'index']);
    Route::get('metrics/{tenantId}',                     [TenantMetricController::class, 'show']);
    Route::post('metrics/{tenantId}/recalculate',        [TenantMetricController::class, 'recalculate']);

    // Exports
    Route::get('export/tenant-health',                   [TenantMetricController::class, 'exportHealth']);
    Route::get('export/notifications',                   [TenantMetricController::class, 'exportNotifications']);
    Route::get('export/leads',                           [TenantMetricController::class, 'exportLeads']);

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

// ── Temporary open routes for development (remove in production) ──
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
