<?php

namespace App\Http\Controllers;

use App\Models\DealExtensionRequestBatch;
use App\Models\Reseller;
use App\Services\BulkDealExtensionService;
use App\Services\DealExtensionEligibilityService;
use App\Services\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Serves Blade views for bulk deal extension requests.
 *
 * Referrer pages: auth:reseller,web + reseller.access middleware
 * Admin pages:    auth:tenant,web middleware
 */
class BulkDealExtensionWebController extends Controller
{
    public function __construct(
        private BulkDealExtensionService       $bulk,
        private DealExtensionEligibilityService $eligibility,
    ) {}

    // ── Referrer views ─────────────────────────────────────────────

    /**
     * GET /reseller/{tenantId}/extension-requests
     * Referrer's list of their own extension request batches.
     */
    public function resellerIndex(Request $request, string $tenantId): View
    {
        $this->assertTenantContext($tenantId);
        $reseller = $this->resolveReseller($tenantId);

        $batches = $this->bulk->getBatchesForReseller($tenantId, $reseller->id);

        return view('reseller.extension-requests.index', compact('batches', 'tenantId'));
    }

    /**
     * GET /reseller/{tenantId}/deals/extension-requests/create
     * Bulk extension request wizard (step 1 — deal selection).
     */
    public function resellerCreate(Request $request, string $tenantId): View
    {
        $this->assertTenantContext($tenantId);
        $reseller = $this->resolveReseller($tenantId);

        $eligibilityRows = $this->eligibility->getEligibleDealsForReseller($reseller, $tenantId);
        $eligible        = $eligibilityRows->where('eligible', true);
        $ineligible      = $eligibilityRows->where('eligible', false);

        return view('reseller.extension-requests.create', compact(
            'tenantId', 'reseller', 'eligibilityRows', 'eligible', 'ineligible'
        ));
    }

    /**
     * GET /reseller/{tenantId}/extension-requests/{batchId}
     * Referrer views their own batch status.
     */
    public function resellerShow(Request $request, string $tenantId, string $batchId): View|RedirectResponse
    {
        $this->assertTenantContext($tenantId);
        $reseller = $this->resolveReseller($tenantId);

        $batch = $this->bulk->getBatchWithItems($batchId, $tenantId);

        if (!$batch || $batch->requested_by_reseller_id !== $reseller->id) {
            return redirect()->route('reseller.extension-requests.index', ['tenantId' => $tenantId])
                ->with('error', 'Extension request not found.');
        }

        return view('reseller.extension-requests.show', compact('batch', 'tenantId'));
    }

    // ── Admin/Manager views ────────────────────────────────────────

    /**
     * GET /tenant/{tenantId}/extension-requests
     * Admin list of all bulk extension request batches.
     */
    public function adminIndex(Request $request, string $tenantId): View
    {
        $this->assertAdminContext($tenantId);

        $statusFilter = $request->query('status');
        $batches      = $this->bulk->getBatchesForTenant($tenantId, $statusFilter ?: null);

        return view('tenant.extension-requests.index', compact('batches', 'tenantId', 'statusFilter'));
    }

    /**
     * GET /tenant/{tenantId}/extension-requests/{batchId}
     * Admin batch review page.
     */
    public function adminShow(Request $request, string $tenantId, string $batchId): View|RedirectResponse
    {
        $this->assertAdminContext($tenantId);

        $batch = $this->bulk->getBatchWithItems($batchId, $tenantId);

        if (!$batch) {
            return redirect()
                ->route('tenant.extension-requests.index', ['tenantId' => $tenantId])
                ->with('error', 'Batch not found.');
        }

        return view('tenant.extension-requests.show', compact('batch', 'tenantId'));
    }

    // ── Private helpers ───────────────────────────────────────────

    private function assertTenantContext(string $tenantId): void
    {
        // Referrer routes: tenant isolation is enforced via reseller.access middleware
        // which already validates tenantId matches the authenticated reseller's tenant.
    }

    private function assertAdminContext(string $tenantId): void
    {
        $contextId = TenantContext::id();
        if ($contextId && $contextId !== $tenantId) {
            abort(403, 'Tenant mismatch.');
        }
    }

    private function resolveReseller(string $tenantId): Reseller
    {
        $user = Auth::guard('reseller')->user() ?? Auth::guard('web')->user();

        // In reseller portal, the authenticated user IS the reseller record
        $reseller = Reseller::where('tenant_id', $tenantId)
            ->where('id', $user?->id)
            ->first();

        if (!$reseller) {
            abort(403, 'Referrer not found or not authorized for this tenant.');
        }

        return $reseller;
    }
}
