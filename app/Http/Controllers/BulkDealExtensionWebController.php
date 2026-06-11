<?php

namespace App\Http\Controllers;

use App\Models\DealExtensionRequestBatch;
use App\Models\Reseller;
use App\Models\Tenant;
use App\Services\BulkDealExtensionService;
use App\Services\DealExtensionEligibilityService;
use App\Services\TenantContext;
use Illuminate\Http\JsonResponse;
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
        $tenant   = $this->resolveTenant($tenantId);
        $reseller = $this->resolveReseller($tenantId);

        $batches = $this->bulk->getBatchesForReseller($tenantId, $reseller->id);

        return view('reseller.extension-requests.index', compact('tenant', 'batches', 'tenantId'));
    }

    /**
     * GET /reseller/{tenantId}/deals/extension-requests/create
     * Bulk extension request wizard (step 1 — deal selection).
     */
    public function resellerCreate(Request $request, string $tenantId): \Illuminate\Http\Response
    {
        $this->assertTenantContext($tenantId);
        $tenant   = $this->resolveTenant($tenantId);
        $reseller = $this->resolveReseller($tenantId);

        $eligibilityRows = $this->eligibility->getEligibleDealsForReseller($reseller, $tenantId);
        $eligible        = $eligibilityRows->where('eligible', true);
        $ineligible      = $eligibilityRows->where('eligible', false);

        return response()
            ->view('reseller.extension-requests.create', compact(
                'tenant', 'tenantId', 'reseller', 'eligibilityRows', 'eligible', 'ineligible'
            ))
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate')
            ->header('Pragma', 'no-cache');
    }

    /**
     * GET /reseller/{tenantId}/extension-requests/{batchId}
     * Referrer views their own batch status.
     */
    public function resellerShow(Request $request, string $tenantId, string $batchId): View|RedirectResponse
    {
        $this->assertTenantContext($tenantId);
        $tenant   = $this->resolveTenant($tenantId);
        $reseller = $this->resolveReseller($tenantId);

        $batch = $this->bulk->getBatchWithItems($batchId, $tenantId);

        if (!$batch || $batch->requested_by_reseller_id !== $reseller->id) {
            return redirect()->route('reseller.extension-requests.index', ['tenantId' => $tenantId])
                ->with('error', 'Extension request not found.');
        }

        return view('reseller.extension-requests.show', compact('tenant', 'batch', 'tenantId'));
    }

    /**
     * POST /reseller/{tenantId}/deals/extension-requests
     * Session-authenticated bulk submission from the wizard.
     */
    public function resellerStore(Request $request, string $tenantId): JsonResponse
    {
        $this->assertTenantContext($tenantId);
        $reseller = $this->resolveReseller($tenantId);

        $data = $request->validate([
            'deal_ids'                 => 'required|array|min:1|max:50',
            'deal_ids.*'               => 'required|string|uuid',
            'requested_extension_days' => 'required|integer|min:1|max:90',
            'shared_reason'            => 'required|string|min:10|max:2000',
            'per_deal_notes'           => 'nullable|array',
            'per_deal_notes.*'         => 'nullable|string|max:2000',
        ]);

        try {
            $result = $this->bulk->createBulkRequest(
                reseller:      $reseller,
                tenantId:      $tenantId,
                dealIds:       $data['deal_ids'],
                requestedDays: (int) $data['requested_extension_days'],
                sharedReason:  $data['shared_reason'],
                perDealNotes:  $data['per_deal_notes'] ?? [],
            );

            return response()->json([
                'success'          => true,
                'batch'            => [
                    'id'              => $result['batch']->id,
                    'batch_reference' => $result['batch']->batch_reference,
                    'total_items'     => $result['batch']->total_items,
                    'status'          => $result['batch']->status,
                ],
                'eligible_count'   => $result['eligible']->count(),
                'ineligible_count' => $result['ineligible']->count(),
                'ineligible'       => $result['ineligible']->map(fn($row) => [
                    'deal_id' => $row['deal_id'],
                    'name'    => $row['deal']->name,
                    'reason'  => $row['reason'],
                ])->values(),
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    // ── Admin/Manager views ────────────────────────────────────────

    /**
     * GET /tenant/{tenantId}/extension-requests
     * Admin list of all bulk extension request batches.
     */
    public function adminIndex(Request $request, string $tenantId): View
    {
        $this->assertAdminContext($tenantId);
        $tenant = $this->resolveTenant($tenantId);

        $tab    = $request->query('tab', 'pending');
        $search = $request->query('search');

        $statusFilter = match ($tab) {
            'approved' => 'approved',
            'declined' => 'declined',
            'partial'  => 'partially_approved',
            'all'      => null,
            default    => 'pending',
        };

        $metrics = $this->bulk->getMetricsForTenant($tenantId);
        $batches = $this->bulk->getBatchesPaginated($tenantId, $statusFilter, $search ?: null);

        return view('tenant.extension-requests.index', compact(
            'tenant', 'batches', 'tenantId', 'tab', 'metrics', 'search', 'statusFilter'
        ));
    }

    /**
     * GET /tenant/{tenantId}/extension-requests/{batchId}
     * Admin batch review page.
     */
    public function adminShow(Request $request, string $tenantId, string $batchId): View|RedirectResponse
    {
        $this->assertAdminContext($tenantId);
        $tenant = $this->resolveTenant($tenantId);

        $batch = $this->bulk->getBatchWithItems($batchId, $tenantId);

        if (!$batch) {
            return redirect()
                ->route('tenant.extension-requests.index', ['tenantId' => $tenantId])
                ->with('error', 'Batch not found.');
        }

        return view('tenant.extension-requests.show', compact('tenant', 'batch', 'tenantId'));
    }

    // ── Private helpers ───────────────────────────────────────────

    private function assertTenantContext(string $tenantId): void
    {
        // Belt-and-suspenders check: reseller.access middleware enforces this, but re-check here.
        if (Auth::guard('reseller')->check()) {
            $reseller = Auth::guard('reseller')->user();
            if (!$reseller || $reseller->tenant_id !== $tenantId) {
                abort(403, 'You do not have access to this workspace.');
            }
        }
    }

    private function assertAdminContext(string $tenantId): void
    {
        if (Auth::guard('web')->check()) return; // SA: allowed through

        $contextId = TenantContext::id();
        if (!$contextId || $contextId !== $tenantId) {
            abort(403, 'Tenant context mismatch.');
        }

        if (!in_array(TenantContext::role(), ['owner', 'admin', 'manager'], true)) {
            abort(403, 'Insufficient permissions to review extension requests.');
        }
    }

    private function resolveTenant(string $tenantId): Tenant
    {
        $tenant = Tenant::find($tenantId);
        if (!$tenant) {
            abort(404, 'Tenant not found.');
        }
        return $tenant;
    }

    private function resolveReseller(string $tenantId): Reseller
    {
        // Super Admins (web guard only) have no reseller record — give a clear error
        // instead of silently falling through to a confusing 403 "Referrer not found".
        if (Auth::guard('web')->check() && !Auth::guard('reseller')->check()) {
            abort(403, 'Super Admins cannot access the Referrer portal. Use the Tenant Admin panel instead.');
        }

        $user = Auth::guard('reseller')->user();

        $reseller = Reseller::where('tenant_id', $tenantId)
            ->where('id', $user?->id)
            ->first();

        if (!$reseller) {
            abort(403, 'Referrer not found or not authorized for this tenant.');
        }

        return $reseller;
    }
}
