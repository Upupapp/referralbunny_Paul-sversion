<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ImportBatch;
use App\Models\ImportBatchRow;
use App\Models\PendingReferrerInvite;
use App\Models\Tenant;
use App\Services\LguIds\LguIdsImportService;
use App\Services\NotificationDispatchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LguIdsImportController extends Controller
{
    public function __construct(private LguIdsImportService $service) {}

    // ── Guards / helpers ──────────────────────────────────────────

    private function resolveTenant(string $tenantId): Tenant
    {
        $tenant = Tenant::findOrFail($tenantId);
        abort_if($tenant->id !== 'lgu-ids', 403, 'LGU IDS only.');
        return $tenant;
    }

    private function authId(): string
    {
        return (string) (Auth::guard('tenant')->id() ?? Auth::guard('reseller')->id() ?? Auth::id());
    }

    private function authRole(): string
    {
        if (Auth::guard('tenant')->check())   return 'tenant_admin';
        if (Auth::guard('reseller')->check()) return 'reseller';
        return 'super_admin';
    }

    private function guardCheck(): void
    {
        $isAuthed = Auth::guard('tenant')->check()
            || Auth::guard('reseller')->check()
            || Auth::guard('web')->check();
        abort_unless($isAuthed, 401, 'Unauthorized.');
        abort_if(Auth::guard('partner')->check(), 403, 'Partners cannot access the import centre.');
    }

    private function batchQuery(string $tenantId): \Illuminate\Database\Eloquent\Builder
    {
        $query = ImportBatch::where('tenant_id', $tenantId);
        if ($this->authRole() === 'reseller') {
            $query->where('imported_by_id', $this->authId());
        }
        return $query;
    }

    // ── Index — Import Centre ─────────────────────────────────────

    public function index(string $tenantId)
    {
        $this->guardCheck();
        $tenant = $this->resolveTenant($tenantId);

        $batches = $this->batchQuery($tenantId)
            ->orderByDesc('created_at')
            ->paginate(10);

        $pendingInvites = PendingReferrerInvite::where('tenant_id', $tenantId)
            ->where('status', 'pending_invite')
            ->count();

        return view('tenant.imports.lgu_ids.index', compact('tenant', 'batches', 'pendingInvites'));
    }

    // ── Download CSV Template ─────────────────────────────────────

    public function downloadTemplate(string $tenantId): StreamedResponse
    {
        $this->guardCheck();
        $this->resolveTenant($tenantId);

        $csv      = $this->service->generateTemplateCsv();
        $filename = 'lgu-ids-import-template-' . date('Y-m-d') . '.csv';

        return response()->streamDownload(
            fn () => print($csv),
            $filename,
            ['Content-Type' => 'text/csv']
        );
    }

    // ── Upload & Preview ──────────────────────────────────────────

    public function upload(string $tenantId, Request $request): RedirectResponse
    {
        $this->guardCheck();
        $tenant = $this->resolveTenant($tenantId);

        $request->validate([
            'file' => 'required|file|mimes:csv,xlsx|max:10240',
        ]);

        try {
            $batch = $this->service->createBatch(
                file:            $request->file('file'),
                importedById:    $this->authId(),
                importedByRole:  $this->authRole(),
            );
        } catch (\Throwable $e) {
            return redirect()
                ->route('tenant.imports.lgu-ids', $tenantId)
                ->withErrors(['file' => $e->getMessage()])
                ->withInput();
        }

        return redirect()
            ->route('tenant.imports.lgu-ids.preview', [$tenantId, $batch->id])
            ->with('success', 'File uploaded successfully. Review your import below before confirming.');
    }

    // ── Preview ───────────────────────────────────────────────────

    public function preview(string $tenantId, string $batchId)
    {
        $this->guardCheck();
        $tenant = $this->resolveTenant($tenantId);
        $batch  = $this->batchQuery($tenantId)->findOrFail($batchId);

        $rows    = ImportBatchRow::where('import_batch_id', $batchId)
            ->orderBy('row_number')
            ->get();
        $grouped = $rows->groupBy('validation_status');
        $summary = $rows->groupBy('validation_status')->map->count();

        // Heal batches stuck in 'previewing': rows exist but status was never
        // updated to 'previewed' because the old code was missing DB columns.
        // Now that the migration has run, advance the status so execute() works.
        if ($batch->status === 'previewing' && $rows->count() > 0) {
            $counts = $grouped->map->count();
            $batch->update([
                'status'                => 'previewed',
                'successful_rows'       => $counts->get('ready', 0),
                'failed_rows'           => $counts->get('failed', 0) + $counts->get('blocked', 0),
                'duplicate_rows'        => $counts->get('duplicate', 0),
                'unknown_referrer_rows' => $counts->get('unknown_referrer', 0),
                'pricing_issue_rows'    => $counts->get('pricing_issue', 0),
                'blocked_rows'          => $counts->get('blocked', 0),
            ]);
            $batch->refresh();
        }

        return view('tenant.imports.lgu_ids.preview', compact('tenant', 'batch', 'rows', 'grouped', 'summary'));
    }

    // ── Correct province / municipality for a row (JSON) ─────────

    public function correctRow(string $tenantId, string $batchId, string $rowId, Request $request): \Illuminate\Http\JsonResponse
    {
        $this->guardCheck();
        $this->resolveTenant($tenantId);

        $data = $request->validate([
            'province'     => 'nullable|string|max:120',
            'municipality' => 'nullable|string|max:120',
        ]);

        $this->batchQuery($tenantId)->where('id', $batchId)->firstOrFail();
        $row = \App\Models\ImportBatchRow::where('import_batch_id', $batchId)->findOrFail($rowId);

        $normalized = is_array($row->normalized_data) ? $row->normalized_data : [];
        if (array_key_exists('province', $data))     $normalized['province']            = $data['province'] ?? '';
        if (array_key_exists('municipality', $data)) $normalized['municipality_or_city'] = $data['municipality'] ?? '';

        $updates = ['normalized_data' => $normalized];
        $hasBoth = !empty($normalized['province']) && !empty($normalized['municipality_or_city']);
        if ($hasBoth && in_array($row->validation_status, ['unknown_lgu', 'failed', 'needs_review'], true)) {
            $updates['validation_status'] = 'valid';
            if (!$row->row_action || in_array($row->row_action, ['skip', 'blocked', null], true)) {
                $updates['row_action'] = 'create';
            }
        }

        $row->update($updates);
        $row->refresh();

        return response()->json([
            'saved'             => true,
            'validation_status' => $row->validation_status,
            'row_action'        => $row->row_action,
        ]);
    }

    // ── Approve a single row (JSON) ───────────────────────────────

    public function approveRow(string $tenantId, string $batchId, string $rowId, Request $request)
    {
        $this->guardCheck();
        $this->resolveTenant($tenantId);

        $request->validate([
            'action' => 'required|in:create,update,skip,merge,overwrite,review,blocked',
        ]);

        $this->batchQuery($tenantId)->where('id', $batchId)->firstOrFail();
        $row = ImportBatchRow::where('import_batch_id', $batchId)->findOrFail($rowId);

        // Resellers may only approve their own rows
        if ($this->authRole() === 'reseller') {
            $norm  = $row->normalized_data;
            $email = Auth::guard('reseller')->user()?->email;
            abort_unless(
                strtolower($norm['referrer_email'] ?? '') === strtolower($email ?? ''),
                403,
                'You can only approve rows assigned to yourself.'
            );
        }

        $this->service->approveRow($row, $request->input('action'), $this->authId());

        return response()->json(['success' => true, 'row_action' => $request->input('action')]);
    }

    // ── Bulk Approve (JSON) ───────────────────────────────────────

    public function bulkApprove(string $tenantId, string $batchId, Request $request)
    {
        $this->guardCheck();
        $this->resolveTenant($tenantId);

        $request->validate([
            'row_ids'        => 'required|array|min:1',
            'row_ids.*'      => 'uuid',
            'action'         => 'required|in:create,update,skip,merge,overwrite,review,blocked',
        ]);

        $this->batchQuery($tenantId)->where('id', $batchId)->firstOrFail();
        $rows = ImportBatchRow::where('import_batch_id', $batchId)
            ->whereIn('id', $request->input('row_ids'))
            ->get();

        $approverId = $this->authId();
        $action     = $request->input('action');

        // Resolve reseller email once before the loop — not inside it
        $resellerEmail = ($this->authRole() === 'reseller')
            ? \Illuminate\Support\Facades\Auth::guard('reseller')->user()?->email
            : null;

        if ($this->authRole() === 'reseller' && $resellerEmail === null) {
            return response()->json(['error' => 'Reseller identity could not be verified.'], 403);
        }

        $approvedCount = 0;
        foreach ($rows as $row) {
            // Resellers may only approve their own rows
            if ($resellerEmail !== null) {
                $norm = $row->normalized_data;
                if (strtolower($norm['referrer_email'] ?? '') !== strtolower($resellerEmail)) {
                    continue; // silently skip rows not owned by this reseller
                }
            }
            $this->service->approveRow($row, $action, $approverId);
            $approvedCount++;
        }

        return response()->json(['success' => true, 'updated' => $approvedCount]);
    }

    // ── Execute import ────────────────────────────────────────────

    public function execute(string $tenantId, string $batchId): RedirectResponse
    {
        $this->guardCheck();
        $this->resolveTenant($tenantId);

        $batch = $this->batchQuery($tenantId)->findOrFail($batchId);

        // Only allow execution from 'previewed' state
        if ($batch->status !== 'previewed') {
            return redirect()
                ->route('tenant.imports.lgu-ids.preview', [$tenantId, $batchId])
                ->withErrors(['import' => "Import cannot be executed in '{$batch->status}' status. It must be in 'previewed' state."]);
        }

        try {
            $result = $this->service->executeImport($batch, $this->authId(), $this->authRole());
        } catch (\Throwable $e) {
            try {
                \App\Events\ImportFailed::dispatch(
                    batchId:    $batchId,
                    tenantId:   $tenantId,
                    fileName:   $batch->file_name ?? 'import.lgu-ids.csv',
                    importType: 'lgu_ids_deals',
                    status:     'failed',
                    failedRows: $batch->total_rows ?? 0,
                    totalRows:  $batch->total_rows ?? 0,
                    actorId:    $this->authId(),
                    actorRole:  $this->authRole(),
                );
            } catch (\Throwable) {}

            return redirect()
                ->route('tenant.imports.lgu-ids.preview', [$tenantId, $batchId])
                ->withErrors(['import' => 'Import failed: ' . $e->getMessage()]);
        }

        // Activity log — feeds resellerImportEvents() in CriticalActionService
        try {
            $isReseller = Auth::guard('reseller')->check();
            \App\Models\ActivityLog::create([
                'tenant_id' => $tenantId,
                'user_id'   => null,
                'action'    => 'deal_import_completed',
                'entity'    => $isReseller ? 'reseller' : 'tenant_admin',
                'entity_id' => (string) $this->authId(),
                'metadata'  => [
                    'batch_id'   => $batchId,
                    'file_name'  => $batch->file_name,
                    'import_type'=> 'lgu_ids_deals',
                    'created'    => $result['created'],
                    'updated'    => $result['updated'],
                    'skipped'    => $result['skipped'],
                    'failed'     => $result['failed'],
                    'actor_role' => $this->authRole(),
                ],
            ]);
        } catch (\Throwable) {}

        // Notify ALL tenant admins only when a Referrer executes — the service already
        // notifies tenant_admin uploaders/executors directly with per-user dedup keys.
        if ($this->authRole() !== 'tenant_admin') {
            try {
                $rs        = \App\Models\Reseller::find($this->authId());
                $actorName = $rs?->name ?: 'Referrer';
                $summary   = "Created: {$result['created']}, Updated: {$result['updated']}, Skipped: {$result['skipped']}.";
                app(NotificationDispatchService::class)->dispatchToTenantAdmins(
                    tenantId:     $tenantId,
                    category:     'deal_pipeline',
                    priority:     'normal',
                    title:        'LGU IDS deal import completed',
                    body:         "{$actorName} imported deals from \"{$batch->file_name}\". {$summary}",
                    actionUrl:    "/tenant/{$tenantId}/imports/lgu-ids/{$batchId}",
                    actionLabel:  'View Import',
                    dedupeSuffix: "lgu_import_done_{$batchId}",
                );
            } catch (\Throwable) {}
        }

        // ── Fire ImportFailed event for completed_with_warnings ────────
        // The 'failed' hard-failure is handled by try/catch around executeImport above (if any).
        $freshBatch = $batch->fresh();
        if (($result['failed'] ?? 0) > 0 && $freshBatch && $freshBatch->status === 'completed_with_warnings') {
            try {
                \App\Events\ImportFailed::dispatch(
                    batchId:    $batchId,
                    tenantId:   $tenantId,
                    fileName:   $batch->file_name ?? 'import.lgu-ids.csv',
                    importType: 'lgu_ids_deals',
                    status:     'completed_with_warnings',
                    failedRows: $result['failed'],
                    totalRows:  $batch->total_rows ?? ($result['created'] + $result['updated'] + $result['skipped'] + $result['failed']),
                    actorId:    $this->authId(),
                    actorRole:  $this->authRole(),
                );
            } catch (\Throwable) {}
        }

        try {
            $cs = app(\App\Services\CriticalActionService::class);
            $adminIds = $cs->invalidateAllAdminBadges($tenantId);
            \Illuminate\Support\Facades\Cache::deleteMultiple($adminIds->map(fn($uid) => "notif_unread_tenant_admin_{$uid}")->toArray());
        } catch (\Throwable) {}

        return redirect()
            ->route('tenant.imports.lgu-ids.show', [$tenantId, $batchId])
            ->with('success', "Import complete — Created: {$result['created']}, Updated: {$result['updated']}, Skipped: {$result['skipped']}, Failed: {$result['failed']}.");
    }

    // ── Show report ───────────────────────────────────────────────

    public function show(string $tenantId, string $batchId)
    {
        $this->guardCheck();
        $tenant = $this->resolveTenant($tenantId);
        $batch  = $this->batchQuery($tenantId)->findOrFail($batchId);

        $rows = ImportBatchRow::where('import_batch_id', $batchId)
            ->orderBy('row_number')
            ->paginate(100);

        // summary_json on $batch carries totals; grouped/summary not used in show view
        $grouped = collect();
        $summary = collect();

        return view('tenant.imports.lgu_ids.show', compact('tenant', 'batch', 'rows', 'grouped', 'summary'));
    }

    // ── Download failed rows CSV ──────────────────────────────────

    public function downloadFailed(string $tenantId, string $batchId): StreamedResponse
    {
        $this->guardCheck();
        $this->resolveTenant($tenantId);

        $batch    = $this->batchQuery($tenantId)->findOrFail($batchId);
        $csv      = $this->service->generateFailedRowsCsv($batch);
        $filename = 'lgu-ids-failed-rows-' . $batchId . '-' . date('Y-m-d') . '.csv';

        return response()->streamDownload(
            fn () => print($csv),
            $filename,
            ['Content-Type' => 'text/csv']
        );
    }
}
