<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ImportBatch;
use App\Models\ImportBatchRow;
use App\Models\PendingReferrerInvite;
use App\Models\Reseller;
use App\Models\Tenant;
use App\Models\TenantCustomField;
use App\Models\TenantImportSettings;
use App\Services\ColumnDetectionService;
use App\Services\GenericDealImportService;
use App\Services\TemplateAdoptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Generic tenant deal import controller.
 *
 * IMPORTANT: This controller is explicitly blocked for the lgu-ids tenant.
 * LGU IDS uses LguIdsImportController + LguIdsImportService exclusively.
 */
class TenantDealImportController extends Controller
{
    public function __construct(
        private GenericDealImportService $service,
        private ColumnDetectionService   $columnDetection,
        private TemplateAdoptionService  $templateAdoption,
    ) {}

    // ── Guards / helpers ──────────────────────────────────────────

    /**
     * Resolve the tenant and hard-block lgu-ids from using this generic controller.
     */
    private function resolveTenant(string $tenantId): Tenant
    {
        $tenant = Tenant::findOrFail($tenantId);
        abort_if(
            $tenant->id === 'lgu-ids',
            403,
            'LGU IDS uses its own dedicated import system. Please use the LGU IDS import section.'
        );
        return $tenant;
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

    private function guardCheck(): void
    {
        $isAuthed = Auth::guard('tenant')->check()
            || Auth::guard('reseller')->check()
            || Auth::guard('web')->check();
        abort_unless($isAuthed, 401, 'Unauthorized.');
        abort_if(Auth::guard('partner')->check(), 403, 'Partners cannot access the import centre.');
    }

    // ── Index ─────────────────────────────────────────────────────

    public function index(string $tenantId)
    {
        $this->guardCheck();
        $tenant   = $this->resolveTenant($tenantId);
        $settings = $this->service->getSettings($tenantId);
        $template = $this->service->getTemplate($tenantId);

        $batches = ImportBatch::where('tenant_id', $tenantId)
            ->where('import_type', 'generic_deals')
            ->orderByDesc('created_at')
            ->paginate(10);

        $pendingInvites = PendingReferrerInvite::where('tenant_id', $tenantId)
            ->where('status', 'pending_invite')
            ->count();

        return view('tenant.imports.deals.index', compact(
            'tenant', 'batches', 'pendingInvites', 'settings', 'template'
        ));
    }

    // ── Download CSV template ─────────────────────────────────────

    public function downloadTemplate(string $tenantId): StreamedResponse
    {
        $this->guardCheck();
        $this->resolveTenant($tenantId);

        $csv      = $this->service->generateTemplateCsv($tenantId);
        $filename = 'deals-import-template-' . date('Y-m-d') . '.csv';

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
        $tenant = $this->resolveTenant($tenantId);

        $request->validate([
            'file' => 'required|file|mimes:csv,xlsx,txt|max:10240',
        ]);

        try {
            $batch = $this->service->createBatch(
                file:            $request->file('file'),
                tenantId:        $tenantId,
                importedById:    $this->authId(),
                importedByRole:  $this->authRole(),
            );
        } catch (\InvalidArgumentException $e) {
            return redirect()
                ->route('tenant.imports.deals', $tenantId)
                ->withErrors(['file' => $e->getMessage()])
                ->withInput();
        }

        return redirect()
            ->route('tenant.imports.deals.preview', [$tenantId, $batch->id])
            ->with('success', 'File uploaded. Review your import below before confirming.');
    }

    // ── Preview ───────────────────────────────────────────────────

    public function preview(string $tenantId, string $batchId)
    {
        $this->guardCheck();
        $tenant = $this->resolveTenant($tenantId);
        $batch  = ImportBatch::where('tenant_id', $tenantId)
            ->where('import_type', 'generic_deals')
            ->findOrFail($batchId);

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

        return view('tenant.imports.deals.preview', compact(
            'tenant', 'batch', 'rows', 'grouped', 'summary', 'knownFields'
        ));
    }

    // ── Approve single row (JSON) ─────────────────────────────────

    public function approveRow(string $tenantId, string $batchId, string $rowId, Request $request)
    {
        $this->guardCheck();
        $this->resolveTenant($tenantId);

        $request->validate([
            'action' => 'required|in:create,update,skip,merge,overwrite,review,blocked',
        ]);

        $row = ImportBatchRow::where('import_batch_id', $batchId)->findOrFail($rowId);

        // Resellers may only approve rows assigned to themselves
        if ($this->authRole() === 'reseller') {
            $norm       = $row->normalized_data;
            $myEmail    = Reseller::where('id', $this->authId())->value('email');
            abort_unless(
                strtolower($norm['referrer_email'] ?? '') === strtolower($myEmail ?? ''),
                403,
                'You can only approve rows assigned to yourself.'
            );
        }

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

        $rows       = ImportBatchRow::where('import_batch_id', $batchId)
            ->whereIn('id', $request->input('row_ids'))
            ->get();
        $approverId = $this->authId();
        $action     = $request->input('action');

        foreach ($rows as $row) {
            // Resellers may only approve rows assigned to themselves — silently skip others
            if ($this->authRole() === 'reseller') {
                $norm    = $row->normalized_data;
                $myEmail = Reseller::where('id', $approverId)->value('email');
                if (strtolower($norm['referrer_email'] ?? '') !== strtolower($myEmail ?? '')) {
                    continue;
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

        $batch = ImportBatch::where('tenant_id', $tenantId)
            ->where('import_type', 'generic_deals')
            ->findOrFail($batchId);

        if ($batch->status !== 'previewed') {
            return redirect()
                ->route('tenant.imports.deals.preview', [$tenantId, $batchId])
                ->withErrors([
                    'import' => "Import cannot be executed in '{$batch->status}' status. It must be in 'previewed' state.",
                ]);
        }

        $result = $this->service->executeImport(
            batch:        $batch,
            tenantId:     $tenantId,
            executorId:   $this->authId(),
            executorRole: $this->authRole(),
        );

        return redirect()
            ->route('tenant.imports.deals.show', [$tenantId, $batchId])
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
        $tenant = $this->resolveTenant($tenantId);
        $batch  = ImportBatch::where('tenant_id', $tenantId)
            ->where('import_type', 'generic_deals')
            ->findOrFail($batchId);

        $rows    = ImportBatchRow::where('import_batch_id', $batchId)
            ->orderBy('row_number')
            ->get();
        $grouped = $rows->groupBy('validation_status');
        $summary = $rows->groupBy('validation_status')->map->count();

        return view('tenant.imports.deals.show', compact(
            'tenant', 'batch', 'rows', 'grouped', 'summary'
        ));
    }

    // ── Download failed rows CSV ──────────────────────────────────

    public function downloadFailed(string $tenantId, string $batchId): StreamedResponse
    {
        $this->guardCheck();
        $this->resolveTenant($tenantId);

        $batch    = ImportBatch::where('tenant_id', $tenantId)
            ->where('import_type', 'generic_deals')
            ->findOrFail($batchId);
        $csv      = $this->service->generateFailedRowsCsv($batch);
        $filename = 'deals-failed-rows-' . $batchId . '-' . date('Y-m-d') . '.csv';

        return response()->streamDownload(
            fn () => print($csv),
            $filename,
            ['Content-Type' => 'text/csv']
        );
    }

    // ── Import settings ───────────────────────────────────────────

    public function showSettings(string $tenantId)
    {
        $this->guardCheck();
        $tenant   = $this->resolveTenant($tenantId);
        $settings = $this->service->getSettings($tenantId);
        $templates = config('referralbunny_import_templates', []);

        return view('tenant.imports.deals.settings', compact('tenant', 'settings', 'templates'));
    }

    public function updateSettings(string $tenantId, Request $request): RedirectResponse
    {
        $this->guardCheck();
        $this->resolveTenant($tenantId);

        $validated = $request->validate([
            'industry_template_key'      => 'required|string|max:50',
            'default_stage'              => 'nullable|string|max:50',
            'default_status'             => 'nullable|string|max:30',
            'default_currency'           => 'nullable|string|max:10',
            'allow_referrer_import'      => 'boolean',
            'allow_referrer_new_deals'   => 'boolean',
            'allow_referrer_partner_add' => 'boolean',
            'duplicate_handling'         => 'required|in:allow,block,require_review,merge_approved,overwrite_approved',
            'unknown_org_behavior'       => 'required|in:auto_create,flag_review,reject',
            'unknown_referrer_behavior'  => 'required|in:flag_invite,reject',
            'unknown_partner_behavior'   => 'required|in:flag_invite,reject',
        ]);

        $settings = TenantImportSettings::forTenant($tenantId);
        $settings->update($validated);

        return redirect()
            ->route('tenant.imports.deals.settings', $tenantId)
            ->with('success', 'Import settings saved successfully.');
    }

    // ── Dynamic Column Handling ───────────────────────────────────

    /**
     * Save per-column actions (ignore / map / create / metadata) chosen by the admin
     * after the unmapped-column detection step.
     *
     * For 'create' actions a TenantCustomField record is created immediately.
     */
    public function saveColumnActions(string $tenantId, string $batchId, Request $request): JsonResponse
    {
        $this->guardCheck();
        $this->resolveTenant($tenantId);

        $validated = $request->validate([
            'actions'             => 'required|array',
            'actions.*.header'    => 'required|string',
            'actions.*.action'    => 'required|in:ignore,map,create,metadata',
            'actions.*.map_to'    => 'nullable|string',
            'actions.*.data_type' => 'nullable|string',
            'actions.*.label'     => 'nullable|string',
        ]);

        $batch = ImportBatch::where('tenant_id', $tenantId)->findOrFail($batchId);

        $createdFields = [];

        foreach ($validated['actions'] as $action) {
            if ($action['action'] === 'create') {
                $label    = $action['label'] ?? $action['header'];
                $fieldKey = $this->columnDetection->generateFieldKey($label, $tenantId, 'deals');
                $dataType = $action['data_type'] ?? 'text';

                $field = TenantCustomField::firstOrCreate(
                    ['tenant_id' => $tenantId, 'destination_type' => 'deals', 'field_key' => $fieldKey],
                    [
                        'field_label'       => $label,
                        'data_type'         => $dataType,
                        'is_required'       => false,
                        'is_importable'     => true,
                        'is_exportable'     => true,
                        'is_visible'        => true,
                        'created_by_user_id'=> $this->authId(),
                    ]
                );

                $createdFields[] = [
                    'header'    => $action['header'],
                    'field_key' => $field->field_key,
                    'label'     => $field->field_label,
                    'data_type' => $field->data_type,
                ];
            }
        }

        $batch->update(['column_actions_json' => $validated['actions']]);

        return response()->json([
            'success'        => true,
            'created_fields' => $createdFields,
            'total_actions'  => count($validated['actions']),
        ]);
    }

    /**
     * Initiate the template adoption flow for a previewed batch.
     * Returns the confirmation phrase and flags that a password will be required.
     */
    public function initiateTemplateAdoption(string $tenantId, string $batchId, Request $request): JsonResponse
    {
        $this->guardCheck();
        abort_if($tenantId === 'lgu-ids', 403, 'LGU IDS uses a locked import template that cannot be modified.');
        $this->resolveTenant($tenantId);

        // Determine acting user role
        $role = $this->authRole();
        if (!$this->templateAdoption->canAdopt($role)) {
            return response()->json([
                'can_adopt' => false,
                'message'   => 'Your role does not have permission to save import templates.',
            ], 403);
        }

        $batch = ImportBatch::where('tenant_id', $tenantId)->findOrFail($batchId);
        $batch->update(['template_adoption_status' => 'pending']);

        return response()->json([
            'can_adopt'              => true,
            'requires_confirmation'  => 'USE THIS TEMPLATE',
            'requires_password'      => true,
        ]);
    }

    /**
     * Verify double-auth (confirmation phrase + password) and save the template version.
     * On success, the batch adoption status is set to 'saved'.
     */
    public function verifyAndSaveTemplate(string $tenantId, string $batchId, Request $request): JsonResponse
    {
        $this->guardCheck();
        abort_if($tenantId === 'lgu-ids', 403, 'LGU IDS uses a locked import template that cannot be modified.');
        $this->resolveTenant($tenantId);

        $request->validate([
            'confirmation_phrase' => 'required|string',
            'password'            => 'required|string',
            'template_name'       => 'nullable|string|max:200',
        ]);

        if ($request->input('confirmation_phrase') !== 'USE THIS TEMPLATE') {
            return response()->json([
                'success' => false,
                'message' => 'Confirmation phrase did not match. The template was not saved.',
            ], 422);
        }

        $user = Auth::guard('tenant')->user();

        if (!$user || !Hash::check($request->input('password'), $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication failed. The template was not saved.',
            ], 403);
        }

        $batch        = ImportBatch::where('tenant_id', $tenantId)->findOrFail($batchId);
        $template     = $this->service->getTemplate($tenantId);
        $templateName = $request->input('template_name') ?? ('Deals Template — ' . now()->format('Y-m-d'));

        $savedTemplate = $this->templateAdoption->createVersion(
            tenantId:       $tenantId,
            destType:       'deals',
            batch:          $batch,
            fields:         array_merge(
                $template['required_fields'] ?? [],
                $template['optional_fields'] ?? []
            ),
            requiredFields: $template['required_fields'] ?? [],
            aliases:        $template['aliases'] ?? [],
            sampleHeaders:  $batch->unmapped_columns_json ?? [],
            templateName:   $templateName,
            createdBy:      $user,
            approvedBy:     $user,
        );

        $batch->update(['template_adoption_status' => 'saved']);

        return response()->json([
            'success'     => true,
            'template_id' => $savedTemplate->id,
            'version'     => $savedTemplate->version_number,
        ]);
    }
}
