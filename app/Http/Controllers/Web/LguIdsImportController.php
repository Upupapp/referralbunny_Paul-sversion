<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ImportBatch;
use App\Models\ImportBatchRow;
use App\Models\PendingReferrerInvite;
use App\Models\Tenant;
use App\Services\LguIds\LguIdsImportService;
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

    // ── Index — Import Centre ─────────────────────────────────────

    public function index(string $tenantId)
    {
        $this->guardCheck();
        $tenant = $this->resolveTenant($tenantId);

        $batches = ImportBatch::where('tenant_id', $tenantId)
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
        } catch (\InvalidArgumentException $e) {
            return redirect()
                ->route('tenant.imports.lgu-ids', $tenantId)
                ->withErrors(['file' => $e->getMessage()])
                ->withInput();
        }

        return redirect()
            ->route('tenant.imports.lgu_ids.preview', [$tenantId, $batch->id])
            ->with('success', 'File uploaded. Review your import below before confirming.');
    }

    // ── Preview ───────────────────────────────────────────────────

    public function preview(string $tenantId, string $batchId)
    {
        $this->guardCheck();
        $tenant = $this->resolveTenant($tenantId);
        $batch  = ImportBatch::where('tenant_id', $tenantId)->findOrFail($batchId);

        $rows    = ImportBatchRow::where('import_batch_id', $batchId)
            ->orderBy('row_number')
            ->get();
        $grouped = $rows->groupBy('validation_status');
        $summary = $rows->groupBy('validation_status')->map->count();

        return view('tenant.imports.lgu_ids.preview', compact('tenant', 'batch', 'rows', 'grouped', 'summary'));
    }

    // ── Approve a single row (JSON) ───────────────────────────────

    public function approveRow(string $tenantId, string $batchId, string $rowId, Request $request)
    {
        $this->guardCheck();
        $this->resolveTenant($tenantId);

        $request->validate([
            'action' => 'required|in:create,update,skip,merge,overwrite,review,blocked',
        ]);

        $row = ImportBatchRow::where('import_batch_id', $batchId)->findOrFail($rowId);

        // Resellers may only approve their own rows
        if ($this->authRole() === 'reseller') {
            $norm  = $row->normalized_data;
            $email = \App\Models\Reseller::where('id', $this->authId())->value('email');
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

        $rows = ImportBatchRow::where('import_batch_id', $batchId)
            ->whereIn('id', $request->input('row_ids'))
            ->get();

        $approverId = $this->authId();
        $action     = $request->input('action');

        foreach ($rows as $row) {
            // Resellers may only approve their own rows
            if ($this->authRole() === 'reseller') {
                $norm  = $row->normalized_data;
                $email = \App\Models\Reseller::where('id', $approverId)->value('email');
                if (strtolower($norm['referrer_email'] ?? '') !== strtolower($email ?? '')) {
                    continue; // silently skip rows not owned by this reseller
                }
            }
            $this->service->approveRow($row, $action, $approverId);
        }

        return response()->json(['success' => true, 'updated' => $rows->count()]);
    }

    // ── Execute import ────────────────────────────────────────────

    public function execute(string $tenantId, string $batchId): RedirectResponse
    {
        $this->guardCheck();
        $this->resolveTenant($tenantId);

        $batch = ImportBatch::where('tenant_id', $tenantId)->findOrFail($batchId);

        // Only allow execution from 'previewed' state
        if ($batch->status !== 'previewed') {
            return redirect()
                ->route('tenant.imports.lgu_ids.preview', [$tenantId, $batchId])
                ->withErrors(['import' => "Import cannot be executed in '{$batch->status}' status. It must be in 'previewed' state."]);
        }

        $result = $this->service->executeImport($batch, $this->authId(), $this->authRole());

        return redirect()
            ->route('tenant.imports.lgu_ids.show', [$tenantId, $batchId])
            ->with('success', "Import complete. Created: {$result['created']}, Updated: {$result['updated']}, Skipped: {$result['skipped']}, Failed: {$result['failed']}.");
    }

    // ── Show report ───────────────────────────────────────────────

    public function show(string $tenantId, string $batchId)
    {
        $this->guardCheck();
        $tenant = $this->resolveTenant($tenantId);
        $batch  = ImportBatch::where('tenant_id', $tenantId)->findOrFail($batchId);

        $rows    = ImportBatchRow::where('import_batch_id', $batchId)
            ->orderBy('row_number')
            ->get();
        $grouped = $rows->groupBy('validation_status');
        $summary = $rows->groupBy('validation_status')->map->count();

        return view('tenant.imports.lgu_ids.show', compact('tenant', 'batch', 'rows', 'grouped', 'summary'));
    }

    // ── Download failed rows CSV ──────────────────────────────────

    public function downloadFailed(string $tenantId, string $batchId): StreamedResponse
    {
        $this->guardCheck();
        $this->resolveTenant($tenantId);

        $batch    = ImportBatch::where('tenant_id', $tenantId)->findOrFail($batchId);
        $csv      = $this->service->generateFailedRowsCsv($batch);
        $filename = 'lgu-ids-failed-rows-' . $batchId . '-' . date('Y-m-d') . '.csv';

        return response()->streamDownload(
            fn () => print($csv),
            $filename,
            ['Content-Type' => 'text/csv']
        );
    }
}
