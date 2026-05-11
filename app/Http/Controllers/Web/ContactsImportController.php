<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ImportBatch;
use App\Models\ImportBatchRow;
use App\Models\Reseller;
use App\Models\Tenant;
use App\Services\ContactsImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Contacts Import Controller.
 *
 * Works for BOTH the tenant admin guard AND the reseller guard.
 * Does NOT block lgu-ids — contacts import works for all tenants.
 * Partners are blocked at the guardCheck() level.
 */
class ContactsImportController extends Controller
{
    public function __construct(private ContactsImportService $service) {}

    // ── Guards / helpers ──────────────────────────────────────────

    /**
     * Verify that the authenticated user is allowed to access contact imports.
     * Partners are never permitted.
     */
    private function guardCheck(): void
    {
        abort_if(Auth::guard('partner')->check(), 403, 'Partners cannot access contact imports.');

        $isAuthed = Auth::guard('tenant')->check()
            || Auth::guard('reseller')->check()
            || Auth::guard('web')->check();

        abort_unless($isAuthed, 401, 'Unauthorized.');
    }

    private function resolveTenant(string $tenantId): Tenant
    {
        return Tenant::findOrFail($tenantId);
    }

    private function authId(): string
    {
        return (string) (
            Auth::guard('tenant')->id()
            ?? Auth::guard('reseller')->id()
            ?? Auth::id()
        );
    }

    private function authRole(): string
    {
        if (Auth::guard('tenant')->check())   return 'tenant_admin';
        if (Auth::guard('reseller')->check()) return 'reseller';
        return 'super_admin';
    }

    /**
     * Return the authenticated reseller's ID, or null if not a reseller.
     */
    private function authResellerId(): ?string
    {
        if (Auth::guard('reseller')->check()) {
            return (string) Auth::guard('reseller')->id();
        }
        return null;
    }

    // ── Index ─────────────────────────────────────────────────────

    /**
     * Show the contacts import hub.
     *
     * Admin: sees all contact import batches for the tenant.
     * Reseller: sees only their own batches.
     */
    public function index(string $tenantId)
    {
        $this->guardCheck();
        $tenant = $this->resolveTenant($tenantId);
        $isAdmin = $this->authRole() !== 'reseller';

        $query = ImportBatch::where('tenant_id', $tenantId)
            ->where('import_type', 'contacts');

        if (!$isAdmin) {
            // Resellers see only their own batches
            $query->where('imported_by_id', $this->authId());
        }

        $batches = $query->orderByDesc('created_at')->paginate(10);

        $view = $isAdmin
            ? 'tenant.imports.contacts.index'
            : 'reseller.contacts.imports.index';

        return view($view, compact('tenant', 'batches', 'isAdmin'));
    }

    // ── Download CSV template ─────────────────────────────────────

    public function downloadTemplate(string $tenantId): StreamedResponse
    {
        $this->guardCheck();
        $this->resolveTenant($tenantId);

        $csv      = $this->service->generateTemplateCsv();
        $filename = 'contacts-import-template-' . date('Y-m-d') . '.csv';

        return response()->streamDownload(
            fn () => print($csv),
            $filename,
            ['Content-Type' => 'text/csv']
        );
    }

    // ── Upload & create batch ─────────────────────────────────────

    public function upload(string $tenantId, Request $request): RedirectResponse
    {
        $this->guardCheck();
        $this->resolveTenant($tenantId);
        $role = $this->authRole();

        $request->validate([
            'file' => 'required|file|mimes:csv,xlsx|max:10240',
        ]);

        $indexRoute = $role === 'reseller'
            ? 'reseller.contacts.imports'
            : 'tenant.imports.contacts';

        try {
            $batch = $this->service->createBatch(
                file:                 $request->file('file'),
                tenantId:             $tenantId,
                importedById:         $this->authId(),
                importedByRole:       $role,
                importedByResellerId: $this->authResellerId(),
            );
        } catch (\InvalidArgumentException $e) {
            return redirect()
                ->route($indexRoute, $tenantId)
                ->withErrors(['file' => $e->getMessage()])
                ->withInput();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('ContactsImport::upload failed', [
                'tenant_id' => $tenantId,
                'role'      => $role,
                'error'     => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
            ]);
            return redirect()
                ->route($indexRoute, $tenantId)
                ->withErrors(['file' => 'Upload failed. Please try again or contact support if the problem persists.'])
                ->withInput();
        }

        $previewRoute = $role === 'reseller'
            ? 'reseller.contacts.imports.preview'
            : 'tenant.imports.contacts.preview';

        return redirect()
            ->route($previewRoute, [$tenantId, $batch->id])
            ->with('success', 'File uploaded. Review your import below before confirming.');
    }

    // ── Preview ───────────────────────────────────────────────────

    /**
     * Show the import preview / row-review screen.
     *
     * Admin sees all rows including cross-referrer info flags.
     * Reseller sees only their own batch and cross-referrer info is hidden.
     */
    public function preview(string $tenantId, string $batchId)
    {
        $this->guardCheck();
        $tenant  = $this->resolveTenant($tenantId);
        $isAdmin = $this->authRole() !== 'reseller';

        $batchQuery = ImportBatch::where('tenant_id', $tenantId)
            ->where('import_type', 'contacts');

        if (!$isAdmin) {
            $batchQuery->where('imported_by_id', $this->authId());
        }

        $batch = $batchQuery->findOrFail($batchId);

        $rows    = ImportBatchRow::where('import_batch_id', $batchId)
            ->orderBy('row_number')
            ->get();
        $grouped = $rows->groupBy('validation_status');
        $summary = $rows->groupBy('validation_status')->map->count();

        // Build the list of known/canonical field names for the unmapped-column mapper.
        $settings = \App\Models\TenantImportSettings::forTenant($tenantId);
        $template = config('referralbunny_import_templates')[$settings->industry_template_key ?? 'default']
            ?? config('referralbunny_import_templates.default')
            ?? [];
        $knownFields = array_values(array_unique(array_merge(
            $template['required_fields'] ?? [],
            $template['optional_fields'] ?? [],
            array_values($template['aliases'] ?? []),
        )));

        $view = $isAdmin
            ? 'tenant.imports.contacts.preview'
            : 'reseller.contacts.imports.preview';

        return view($view, compact('tenant', 'batch', 'rows', 'grouped', 'summary', 'isAdmin', 'knownFields'));
    }

    // ── Approve single row (JSON) ─────────────────────────────────

    public function approveRow(string $tenantId, string $batchId, string $rowId, Request $request)
    {
        $this->guardCheck();
        $this->resolveTenant($tenantId);

        $request->validate([
            'action' => 'required|in:create,update,skip,merge,overwrite,review,blocked',
        ]);

        $batchQuery = ImportBatch::where('tenant_id', $tenantId)->where('import_type', 'contacts');
        if ($this->authRole() === 'reseller') {
            $batchQuery->where('imported_by_id', $this->authId());
        }
        $batchQuery->findOrFail($batchId); // ensure access to this batch

        $row = ImportBatchRow::where('import_batch_id', $batchId)->findOrFail($rowId);

        $this->service->approveRow($row, $request->input('action'), $this->authId());

        return response()->json([
            'success'    => true,
            'row_action' => $request->input('action'),
        ]);
    }

    // ── Bulk approve (JSON) ───────────────────────────────────────

    public function bulkApprove(string $tenantId, string $batchId, Request $request)
    {
        $this->guardCheck();
        $this->resolveTenant($tenantId);

        $request->validate([
            'row_ids'   => 'required|array|min:1',
            'row_ids.*' => 'uuid',
            'action'    => 'required|in:create,update,skip,merge,overwrite,review,blocked',
        ]);

        $batchQuery = ImportBatch::where('tenant_id', $tenantId)->where('import_type', 'contacts');
        if ($this->authRole() === 'reseller') {
            $batchQuery->where('imported_by_id', $this->authId());
        }
        $batchQuery->findOrFail($batchId); // ensure access to this batch

        $rows       = ImportBatchRow::where('import_batch_id', $batchId)
            ->whereIn('id', $request->input('row_ids'))
            ->get();
        $approverId = $this->authId();
        $action     = $request->input('action');

        foreach ($rows as $row) {
            $this->service->approveRow($row, $action, $approverId);
        }

        return response()->json(['success' => true, 'updated' => $rows->count()]);
    }

    // ── Execute import ────────────────────────────────────────────

    public function execute(string $tenantId, string $batchId): RedirectResponse
    {
        $this->guardCheck();
        $this->resolveTenant($tenantId);
        $role = $this->authRole();

        $batchQuery = ImportBatch::where('tenant_id', $tenantId)->where('import_type', 'contacts');
        if ($role === 'reseller') {
            $batchQuery->where('imported_by_id', $this->authId());
        }
        $batch = $batchQuery->findOrFail($batchId);

        if ($batch->status !== 'previewed') {
            $previewRoute = $role === 'reseller'
                ? 'reseller.contacts.imports.preview'
                : 'tenant.imports.contacts.preview';

            return redirect()
                ->route($previewRoute, [$tenantId, $batchId])
                ->withErrors([
                    'import' => "Import cannot be executed in '{$batch->status}' status. It must be in 'previewed' state.",
                ]);
        }

        $result = $this->service->executeImport(
            batch:               $batch,
            tenantId:            $tenantId,
            executorId:          $this->authId(),
            executorRole:        $role,
            executorResellerId:  $this->authResellerId(),
        );

        $reportRoute = $role === 'reseller'
            ? 'reseller.contacts.imports.show'
            : 'tenant.imports.contacts.show';

        return redirect()
            ->route($reportRoute, [$tenantId, $batchId])
            ->with('success',
                "Import complete. Created: {$result['created']}, "
                . "Updated: {$result['updated']}, "
                . "Skipped: {$result['skipped']}, "
                . "Failed: {$result['failed']}."
            );
    }

    // ── Show report ───────────────────────────────────────────────

    public function show(string $tenantId, string $batchId)
    {
        $this->guardCheck();
        $tenant  = $this->resolveTenant($tenantId);
        $isAdmin = $this->authRole() !== 'reseller';

        $batchQuery = ImportBatch::where('tenant_id', $tenantId)->where('import_type', 'contacts');
        if (!$isAdmin) {
            $batchQuery->where('imported_by_id', $this->authId());
        }
        $batch = $batchQuery->findOrFail($batchId);

        $rows    = ImportBatchRow::where('import_batch_id', $batchId)
            ->orderBy('row_number')
            ->get();
        $grouped = $rows->groupBy('validation_status');
        $summary = $rows->groupBy('validation_status')->map->count();

        $view = $isAdmin
            ? 'tenant.imports.contacts.show'
            : 'reseller.contacts.imports.show';

        return view($view, compact('tenant', 'batch', 'rows', 'grouped', 'summary', 'isAdmin'));
    }

    // ── Download failed rows CSV ──────────────────────────────────

    public function downloadFailed(string $tenantId, string $batchId): StreamedResponse
    {
        $this->guardCheck();
        $this->resolveTenant($tenantId);

        $batchQuery = ImportBatch::where('tenant_id', $tenantId)->where('import_type', 'contacts');
        if ($this->authRole() === 'reseller') {
            $batchQuery->where('imported_by_id', $this->authId());
        }
        $batch = $batchQuery->findOrFail($batchId);

        $csv      = $this->service->generateFailedRowsCsv($batch);
        $filename = 'contacts-failed-rows-' . $batchId . '-' . date('Y-m-d') . '.csv';

        return response()->streamDownload(
            fn () => print($csv),
            $filename,
            ['Content-Type' => 'text/csv']
        );
    }
}
