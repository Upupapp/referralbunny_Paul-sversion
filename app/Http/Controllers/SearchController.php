<?php

namespace App\Http\Controllers;

use App\Models\ApprovalRequest;
use App\Models\BillingAuditLog;
use App\Models\Invoice;
use App\Models\PromoCode;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\IndexingService;
use App\Services\SearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __construct(
        private SearchService  $search,
        private IndexingService $indexing
    ) {}

    // GET /api/search?q=...&type=...&status=...&from=...&to=...&limit=...&offset=...
    public function search(Request $request): JsonResponse
    {
        $query   = $request->get('q', '');
        $limit   = min((int) $request->get('limit', 20), 50);
        $offset  = (int) $request->get('offset', 0);
        $filters = array_filter([
            'type'      => $request->get('type'),
            'status'    => $request->get('status'),
            'from'      => $request->get('from'),
            'to'        => $request->get('to'),
            'tenant_id' => $request->get('tenant_id'),
        ]);

        if (strlen($query) < 1) {
            return response()->json(['results' => [], 'total' => 0, 'query' => '', 'command' => null]);
        }

        $result = $this->search->search($query, $filters, $limit, $offset);

        // Log recent search (if authenticated)
        if ($user = $request->user()) {
            $this->search->logSearch($user->id, $query, $result['total']);
        }

        return response()->json($result);
    }

    // GET /api/search/suggest?q=...
    public function suggest(Request $request): JsonResponse
    {
        $query = $request->get('q', '');
        return response()->json($this->search->suggest($query));
    }

    // GET /api/search/recent
    public function recent(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) return response()->json([]);
        try {
            return response()->json($this->search->getRecent($user->id));
        } catch (\Throwable) {
            return response()->json([]);
        }
    }

    // GET /api/search/saved
    public function savedSearches(Request $request): JsonResponse
    {
        try {
            return response()->json($this->search->getSavedSearches($request->user()->id));
        } catch (\Throwable) {
            return response()->json([]);
        }
    }

    // POST /api/search/saved
    public function saveSearch(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'     => 'required|string|max:100',
            'query'    => 'required|string',
            'filters'  => 'nullable|array',
            'is_pinned'=> 'nullable|boolean',
        ]);

        try {
            $saved = $this->search->saveSearch(
                $request->user()->id,
                $data['name'],
                $data['query'],
                $data['filters'] ?? [],
                $data['is_pinned'] ?? false
            );
            return response()->json($saved, 201);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Could not save search: ' . $e->getMessage()], 500);
        }
    }

    // DELETE /api/search/saved/{id}
    public function deleteSavedSearch(string $id, Request $request): JsonResponse
    {
        \App\Models\SavedSearch::where('id', $id)->where('user_id', $request->user()->id)->delete();
        return response()->json(['message' => 'Deleted.']);
    }

    // POST /api/search/favorites
    public function addFavorite(Request $request): JsonResponse
    {
        $data = $request->validate([
            'entity_type' => 'required|string',
            'entity_id'   => 'required|string',
            'label'       => 'nullable|string',
            'url'         => 'nullable|string',
        ]);

        $fav = $this->search->addFavorite($request->user()->id, $data['entity_type'], $data['entity_id'], $data['label'] ?? null, $data['url'] ?? null);
        return response()->json($fav, 201);
    }

    // DELETE /api/search/favorites
    public function removeFavorite(Request $request): JsonResponse
    {
        $data = $request->validate(['entity_type' => 'required', 'entity_id' => 'required']);
        $this->search->removeFavorite($request->user()->id, $data['entity_type'], $data['entity_id']);
        return response()->json(['message' => 'Removed.']);
    }

    // GET /api/search/favorites
    public function getFavorites(Request $request): JsonResponse
    {
        return response()->json($this->search->getFavorites($request->user()->id));
    }

    // POST /api/search/actions
    public function executeAction(Request $request): JsonResponse
    {
        $data = $request->validate([
            'action'      => 'required|string',
            'entity_type' => 'required|string',
            'entity_id'   => 'required|string',
            'reason'      => 'nullable|string',
        ]);

        $action     = $data['action'];
        $entityType = $data['entity_type'];
        $entityId   = $data['entity_id'];
        $reason     = $data['reason'] ?? 'Action from global search';
        $userId     = $request->user()->id;

        $result = match ($action) {
            'suspend_tenant'   => $this->suspendTenant($entityId, $reason, $userId),
            'activate_tenant'  => $this->activateTenant($entityId, $reason, $userId),
            'mark_paid'        => $this->markInvoicePaid($entityId, $userId),
            'waive_invoice'    => $this->waiveInvoice($entityId, $reason, $userId),
            'disable_promo'    => $this->disablePromo($entityId, $userId),
            'approve'          => $this->approveRequest($entityId, $userId),
            'reject'           => $this->rejectRequest($entityId, $reason, $userId),
            default            => ['success' => false, 'message' => 'Unknown action.'],
        };

        BillingAuditLog::log('search_action_' . $action, [
            'entity_type'  => $entityType,
            'entity_id'    => $entityId,
            'performed_by' => $userId,
            'reason'       => $reason,
        ]);

        return response()->json($result);
    }

    // POST /api/search/reindex
    public function reindex(Request $request): JsonResponse
    {
        $type = $request->get('type'); // null = all

        if ($type) {
            $count = $this->indexing->reindexType($type);
            return response()->json(['message' => "Reindexed {$count} {$type} records."]);
        }

        $results = $this->indexing->reindexAll();
        $total   = array_sum($results);
        return response()->json(['message' => "Reindexed {$total} total records.", 'breakdown' => $results]);
    }

    // ── Action helpers ────────────────────────────────────────

    private function suspendTenant(string $tenantId, string $reason, int $userId): array
    {
        $sub = Subscription::where('tenant_id', $tenantId)->where('status', 'active')->first();
        if (!$sub) return ['success' => false, 'message' => 'No active subscription found.'];

        $sub->update(['status' => 'suspended']);
        Tenant::where('id', $tenantId)->update(['status' => 'inactive']);
        return ['success' => true, 'message' => 'Tenant suspended.'];
    }

    private function activateTenant(string $tenantId, string $reason, int $userId): array
    {
        Tenant::where('id', $tenantId)->update(['status' => 'active']);
        Subscription::where('tenant_id', $tenantId)->where('status', 'suspended')->update(['status' => 'active']);
        return ['success' => true, 'message' => 'Tenant activated.'];
    }

    private function markInvoicePaid(string $invoiceId, int $userId): array
    {
        $invoice = Invoice::find($invoiceId);
        if (!$invoice) return ['success' => false, 'message' => 'Invoice not found.'];
        $invoice->update(['status' => 'paid', 'paid_at' => now()]);
        return ['success' => true, 'message' => 'Invoice marked as paid.'];
    }

    private function waiveInvoice(string $invoiceId, string $reason, int $userId): array
    {
        $invoice = Invoice::find($invoiceId);
        if (!$invoice) return ['success' => false, 'message' => 'Invoice not found.'];
        $invoice->update(['status' => 'waived']);
        return ['success' => true, 'message' => 'Invoice waived.'];
    }

    private function disablePromo(string $promoId, int $userId): array
    {
        PromoCode::where('id', $promoId)->update(['status' => 'inactive']);
        return ['success' => true, 'message' => 'Promo code disabled.'];
    }

    private function approveRequest(string $approvalId, int $userId): array
    {
        $approval = ApprovalRequest::find($approvalId);
        if (!$approval || !$approval->isPending()) return ['success' => false, 'message' => 'Approval not found or not pending.'];

        app(\App\Services\ApprovalService::class)->approve($approval, $userId);
        return ['success' => true, 'message' => 'Approved.'];
    }

    private function rejectRequest(string $approvalId, string $reason, int $userId): array
    {
        $approval = ApprovalRequest::find($approvalId);
        if (!$approval || !$approval->isPending()) return ['success' => false, 'message' => 'Approval not found or not pending.'];

        app(\App\Services\ApprovalService::class)->reject($approval, $userId, $reason);
        return ['success' => true, 'message' => 'Rejected.'];
    }
}
