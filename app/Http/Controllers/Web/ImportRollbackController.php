<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessImportRollbackJob;
use App\Models\ActivityLog;
use App\Models\ImportBatch;
use App\Models\ImportRollback;
use App\Services\ImportRollbackPreviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Handles import batch rollback (Undo Import) actions.
 *
 * Tenant isolation: every query is scoped by $tenantId from the URL,
 * validated against the authenticated session. Never trust frontend tenant_id.
 *
 * Role restriction: only Tenant Admins can initiate rollbacks.
 * Referrers and Partners cannot.
 */
class ImportRollbackController extends Controller
{
    public function __construct(
        private ImportRollbackPreviewService $previewService,
    ) {}

    // ── Guards ────────────────────────────────────────────────────────────

    private function requireTenantAdmin(string $tenantId): void
    {
        if (Auth::guard('tenant')->check()) {
            // Verify membership for this tenant
            $userId = Auth::guard('tenant')->id();
            $member = \Illuminate\Support\Facades\DB::table('tenant_memberships')
                ->where('tenant_user_id', $userId)
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->first();
            abort_if(!$member, 403, 'You do not have access to this tenant.');
            abort_if(!in_array($member->role, ['owner', 'admin']), 403, 'Only Tenant Owners or Admins can undo imports.');
            return;
        }
        if (Auth::guard('web')->check()) {
            return; // Super admin
        }
        abort(403, 'Only Tenant Admins can undo imports.');
    }

    private function authId(): string
    {
        return (string) (
            Auth::guard('tenant')->id()
            ?? Auth::guard('web')->id()
            ?? ''
        );
    }

    private function authType(): string
    {
        if (Auth::guard('web')->check()) return 'super_admin';
        return 'tenant_admin';
    }

    // ── GET /tenant/{tenantId}/imports/{batchId}/rollback/preview ────────
    // Returns eligibility check + dry-run summary as JSON (for modal).

    public function preview(string $tenantId, string $batchId): JsonResponse
    {
        $this->requireTenantAdmin($tenantId);

        $batch = ImportBatch::where('id', $batchId)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        $check = $this->previewService->check($batch, $tenantId);

        return response()->json($check);
    }

    // ── POST /tenant/{tenantId}/imports/{batchId}/rollback ────────────────
    // Initiates the rollback after confirmation.

    public function store(string $tenantId, Request $request, string $batchId): JsonResponse
    {
        $this->requireTenantAdmin($tenantId);

        $request->validate([
            'confirmation_phrase' => 'required|string',
        ]);

        if (strtoupper(trim($request->input('confirmation_phrase'))) !== 'UNDO IMPORT') {
            return response()->json(['error' => 'Confirmation phrase is incorrect. Type UNDO IMPORT to confirm.'], 422);
        }

        $batch = ImportBatch::where('id', $batchId)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        // Re-validate eligibility server-side
        $check = $this->previewService->check($batch, $tenantId);
        if (!$check['eligible']) {
            return response()->json(['error' => $check['reason'] ?? 'This import cannot be undone.'], 422);
        }

        // Prevent duplicate rollback (lock)
        if ($batch->rollback_id || in_array($batch->rollback_status, ['processing', 'completed', 'completed_with_warnings'])) {
            return response()->json(['error' => 'A rollback is already in progress or completed for this import.'], 409);
        }

        $actorId = $this->authId();

        // Create rollback record
        $rollback = ImportRollback::create([
            'import_batch_id'    => $batch->id,
            'tenant_id'          => $tenantId,
            'requested_by'       => $actorId,
            'requested_by_type'  => $this->authType(),
            'mode'               => 'full_batch',
            'status'             => 'pending',
            'dry_run_summary'    => $check['summary'],
            'records_restored'   => 0,
            'records_deleted'    => 0,
            'records_skipped'    => 0,
            'records_failed'     => 0,
            'records_conflict'   => 0,
        ]);

        // Mark batch as rollback-processing
        $batch->update([
            'rollback_status' => 'processing',
            'rollback_id'     => $rollback->id,
        ]);

        // Audit log
        try {
            ActivityLog::create([
                'id'        => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'user_id'   => $actorId,
                'action'    => 'import_rollback_started',
                'entity'    => 'import_batch',
                'entity_id' => $batch->id,
                'metadata'  => ['rollback_id' => $rollback->id],
            ]);
        } catch (\Throwable) {}

        // Dispatch job
        ProcessImportRollbackJob::dispatch($rollback->id, $batch->id, $tenantId);

        return response()->json([
            'rollback_id' => $rollback->id,
            'status'      => 'processing',
            'message'     => 'Import rollback started. You will be notified when it completes.',
        ], 202);
    }

    // ── GET /tenant/{tenantId}/imports/{batchId}/rollback/{rollbackId} ────
    // Show rollback report page.

    public function show(string $tenantId, string $batchId, string $rollbackId)
    {
        $this->requireTenantAdmin($tenantId);

        $batch = ImportBatch::where('id', $batchId)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        $rollback = ImportRollback::where('id', $rollbackId)
            ->where('import_batch_id', $batchId)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        // Fetch snapshots for report
        $snapshots = \App\Models\ImportSnapshot::where('import_batch_id', $batchId)
            ->orderBy('created_at')
            ->paginate(50);

        return view('tenant.imports.rollback.show', compact('batch', 'rollback', 'snapshots', 'tenantId'));
    }

    // ── GET /tenant/{tenantId}/imports/{batchId}/rollback/{rollbackId}/status
    // Polling endpoint for rollback progress.

    public function status(string $tenantId, string $batchId, string $rollbackId): JsonResponse
    {
        $this->requireTenantAdmin($tenantId);

        $rollback = ImportRollback::where('id', $rollbackId)
            ->where('import_batch_id', $batchId)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        return response()->json([
            'rollback_id'      => $rollback->id,
            'status'           => $rollback->status,
            'started_at'       => $rollback->started_at?->toIso8601String(),
            'completed_at'     => $rollback->completed_at?->toIso8601String(),
            'records_restored' => $rollback->records_restored ?? 0,
            'records_deleted'  => $rollback->records_deleted  ?? 0,
            'records_skipped'  => $rollback->records_skipped  ?? 0,
            'records_failed'   => $rollback->records_failed   ?? 0,
            'records_conflict' => $rollback->records_conflict ?? 0,
            'result_summary'   => $rollback->result_summary,
            'is_done'          => $rollback->isCompleted() || $rollback->isFailed(),
        ]);
    }
}
