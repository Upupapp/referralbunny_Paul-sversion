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
     * Resolve the tenant. lgu-ids is blocked for admins (they use LguIdsImportController)
     * but allowed for resellers who use the generic import form.
     */
    private function resolveTenant(string $tenantId): Tenant
    {
        $tenant = Tenant::findOrFail($tenantId);
        if (!$this->isReseller()) {
            abort_if(
                $tenant->id === 'lgu-ids',
                403,
                'LGU IDS uses its own dedicated import system. Please use the LGU IDS import section.'
            );
        }
        return $tenant;
    }

    private function isReseller(): bool
    {
        return Auth::guard('reseller')->check();
    }

    private function authId(): string
    {
        return (string) (
            Auth::guard('tenant')->id()
            ?? Auth::guard('reseller')->id()
            ?? Auth::id()
        );
    }

    private function authResellerId(): ?string
    {
        return $this->isReseller() ? (string) Auth::guard('reseller')->id() : null;
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

    /** Build a batch query that accepts both generic_deals and lgu_ids_deals for LGU IDS resellers. */
    private function batchQuery(string $tenantId): \Illuminate\Database\Eloquent\Builder
    {
        $q = ImportBatch::where('tenant_id', $tenantId);
        if ($tenantId === 'lgu-ids' && $this->isReseller()) {
            $q->whereIn('import_type', ['generic_deals', 'lgu_ids_deals']);
        } else {
            $q->where('import_type', 'generic_deals');
        }
        if ($this->isReseller()) {
            $q->where('imported_by_id', $this->authId());
        }
        return $q;
    }

    /** Return the correct named routes based on who is accessing. */
    private function routeNames(): array
    {
        if ($this->isReseller()) {
            return [
                'index'   => 'reseller.deals.imports',
                'preview' => 'reseller.deals.imports.preview',
                'show'    => 'reseller.deals.imports.show',
            ];
        }
        return [
            'index'   => 'tenant.imports.deals',
            'preview' => 'tenant.imports.deals.preview',
            'show'    => 'tenant.imports.deals.show',
        ];
    }

    // ── Index ─────────────────────────────────────────────────────

    public function index(string $tenantId)
    {
        $this->guardCheck();
        $tenant   = $this->resolveTenant($tenantId);
        $settings = $this->service->getSettings($tenantId);
        $template = $this->service->getTemplate($tenantId);

        $query = $this->batchQuery($tenantId);

        $batches = $query->orderByDesc('created_at')->paginate(10);

        $pendingInvites = PendingReferrerInvite::where('tenant_id', $tenantId)
            ->where('status', 'pending_invite')
            ->count();

        $view = $this->isReseller()
            ? 'reseller.deals.imports.index'
            : 'tenant.imports.deals.index';

        return view($view, compact('tenant', 'batches', 'pendingInvites', 'settings', 'template'));
    }

    // ── Download CSV template ─────────────────────────────────────

    public function downloadTemplate(string $tenantId): StreamedResponse
    {
        $this->guardCheck();
        $this->resolveTenant($tenantId);

        // LGU IDS resellers get the province/municipality template
        if ($tenantId === 'lgu-ids' && $this->isReseller()) {
            $lguService = app(\App\Services\LguIds\LguIdsImportService::class);
            $csv = $lguService->generateTemplateCsv();
        } else {
            $csv = $this->service->generateTemplateCsv($tenantId);
        }
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
            'file' => 'required|file|mimes:csv,xlsx|max:10240',
        ]);

        $routes = $this->routeNames();

        try {
            // LGU IDS resellers use LguIdsImportService (province + municipality columns)
            if ($tenantId === 'lgu-ids' && $this->isReseller()) {
                $lguService = app(\App\Services\LguIds\LguIdsImportService::class);
                $batch = $lguService->createBatch(
                    file:           $request->file('file'),
                    importedById:   $this->authId(),
                    importedByRole: $this->authRole(),
                );
            } else {
                $batch = $this->service->createBatch(
                    file:           $request->file('file'),
                    tenantId:       $tenantId,
                    importedById:   $this->authId(),
                    importedByRole: $this->authRole(),
                );
            }
        } catch (\InvalidArgumentException $e) {
            return redirect()
                ->route($routes['index'], $tenantId)
                ->withErrors(['file' => $e->getMessage()])
                ->withInput();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('DealImport::upload failed', [
                'tenant_id' => $tenantId, 'role' => $this->authRole(),
                'error'     => $e->getMessage(), 'trace' => $e->getTraceAsString(),
            ]);
            return redirect()
                ->route($routes['index'], $tenantId)
                ->withErrors(['file' => 'Upload failed: ' . $e->getMessage()])
                ->withInput();
        }

        return redirect()
            ->route($routes['preview'], [$tenantId, $batch->id])
            ->with('success', 'File uploaded. Review your import below before confirming.');
    }

    // ── Preview ───────────────────────────────────────────────────

    public function preview(string $tenantId, string $batchId)
    {
        $this->guardCheck();
        $tenant = $this->resolveTenant($tenantId);

        $batch = $this->batchQuery($tenantId)->findOrFail($batchId);

        $rows    = ImportBatchRow::where('import_batch_id', $batchId)
            ->orderBy('row_number')
            ->get();
        $grouped = $rows->groupBy('validation_status');
        $summary = $rows->groupBy('validation_status')->map->count();

        $settings = \App\Models\TenantImportSettings::forTenant($tenantId);
        $template = config('referralbunny_import_templates')[$settings->industry_template_key ?? 'default']
            ?? config('referralbunny_import_templates.default')
            ?? [];
        $knownFields = array_values(array_unique(array_merge(
            $template['required_fields'] ?? [],
            $template['optional_fields'] ?? [],
            array_values($template['aliases'] ?? []),
        )));

        $isLguIds  = ($tenantId === 'lgu-ids' && $batch->import_type === 'lgu_ids_deals');
        $provinces = $isLguIds ? config('philippines.provinces', []) : [];

        $view = $this->isReseller()
            ? 'reseller.deals.imports.preview'
            : 'tenant.imports.deals.preview';

        return view($view, compact('tenant', 'batch', 'rows', 'grouped', 'summary', 'knownFields', 'isLguIds', 'provinces'));
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

        $row = ImportBatchRow::where('import_batch_id', $batchId)->findOrFail($rowId);

        $normalized = is_array($row->normalized_data) ? $row->normalized_data : [];

        if (array_key_exists('province', $data)) {
            $normalized['province'] = $data['province'] ?? '';
        }
        if (array_key_exists('municipality', $data)) {
            $normalized['municipality_or_city'] = $data['municipality'] ?? '';
        }

        $updates = ['normalized_data' => $normalized];

        // If the row was previously 'unknown_lgu' and now has both fields,
        // mark it as ready to import (action=create, status=valid)
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
        $routes = $this->routeNames();

        $batch = $this->batchQuery($tenantId)->findOrFail($batchId);

        if ($batch->status !== 'previewed') {
            return redirect()
                ->route($routes['preview'], [$tenantId, $batchId])
                ->withErrors([
                    'import' => "Import cannot be executed in '{$batch->status}' status. It must be in 'previewed' state.",
                ]);
        }

        try {
            // LGU IDS reseller batches use LguIdsImportService
            if ($batch->import_type === 'lgu_ids_deals') {
                $lguService = app(\App\Services\LguIds\LguIdsImportService::class);
                $result = $lguService->executeImport(
                    batch:       $batch,
                    executorId:  $this->authId(),
                    executorRole: $this->authRole(),
                );
            } else {
                $result = $this->service->executeImport(
                    batch:        $batch,
                    tenantId:     $tenantId,
                    executorId:   $this->authId(),
                    executorRole: $this->authRole(),
                );
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('DealImport::execute failed', [
                'tenant_id' => $tenantId, 'batch_id' => $batchId, 'error' => $e->getMessage(),
            ]);
            return redirect()
                ->route($routes['preview'], [$tenantId, $batchId])
                ->withErrors(['import' => 'Import failed. Please try again or contact support.']);
        }

        // ── Activity log + critical-actions feed ──────────────────────
        try {
            $resellerId = $this->authResellerId();
            \App\Models\ActivityLog::create([
                'tenant_id' => $tenantId,
                'user_id'   => null,
                'action'    => 'deal_import_completed',
                'entity'    => $resellerId ? 'reseller' : 'tenant_admin',
                'entity_id' => (string) ($resellerId ?? $this->authId()),
                'metadata'  => [
                    'batch_id'    => $batchId,
                    'file_name'   => $batch->file_name ?? 'import.csv',
                    'import_type' => $batch->import_type ?? 'deals',
                    'created'     => $result['created'],
                    'updated'     => $result['updated'],
                    'skipped'     => $result['skipped'],
                    'failed'      => $result['failed'],
                    'actor_role'  => $this->authRole(),
                ],
            ]);
        } catch (\Throwable) {
            // Never block the import redirect for a logging failure
        }

        // Notify admins + managers that an import was completed
        try {
            // Resolve actor display name
            if ($this->isReseller()) {
                $rs         = \App\Models\Reseller::find($this->authResellerId());
                $actorName  = $rs?->name ?: 'Referrer';
            } elseif (Auth::guard('tenant')->check()) {
                $tu        = Auth::guard('tenant')->user();
                $actorName = trim(($tu->first_name ?? '') . ' ' . ($tu->last_name ?? '')) ?: ($tu->email ?? 'Team member');
            } else {
                $actorName = 'Admin';
            }

            $fileName = $batch->file_name ?? 'import file';
            $summary  = "Created: {$result['created']}, Updated: {$result['updated']}, Skipped: {$result['skipped']}.";

            app(\App\Services\NotificationDispatchService::class)->dispatchToTenantAdmins(
                tenantId:     $tenantId,
                category:     'deal_pipeline',
                priority:     'normal',
                title:        "Deal import completed",
                body:         "{$actorName} imported deals from {$fileName}. {$summary}",
                actionUrl:    "/tenant/{$tenantId}/imports",
                actionLabel:  'View Import',
                dedupeSuffix: "import_done_{$batchId}",
                metadata:     [
                    'batch_id'  => $batchId,
                    'file_name' => $fileName,
                    'created'   => $result['created'],
                    'updated'   => $result['updated'],
                    'skipped'   => $result['skipped'],
                    'failed'    => $result['failed'],
                ],
            );
        } catch (\Throwable) {
            // Notification failure must never block the import redirect
        }

        return redirect()
            ->route($routes['show'], [$tenantId, $batchId])
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

        $batch = $this->batchQuery($tenantId)->findOrFail($batchId);

        $rows    = ImportBatchRow::where('import_batch_id', $batchId)
            ->orderBy('row_number')
            ->paginate(50);
        $allRows = ImportBatchRow::where('import_batch_id', $batchId)->get(['validation_status', 'row_action']);
        $grouped = $allRows->groupBy('validation_status');
        $summary = $allRows->groupBy('validation_status')->map->count();

        $isLguIds = ($tenantId === 'lgu-ids' && $batch->import_type === 'lgu_ids_deals');
        $view = $this->isReseller()
            ? 'reseller.deals.imports.show'
            : 'tenant.imports.deals.show';

        return view($view, compact('tenant', 'batch', 'rows', 'grouped', 'summary', 'isLguIds'));
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
        $tenant    = $this->resolveTenant($tenantId);
        $settings  = $this->service->getSettings($tenantId);
        $templates = config('referralbunny_import_templates', []);

        // Build label map expected by the view: ['default' => 'Default', 'lgu_ids' => 'Lgu Ids', ...]
        $templateKeys = collect($templates)
            ->mapWithKeys(fn ($v, $k) => [
                $k => $v['label'] ?? ucwords(str_replace('_', ' ', $k)),
            ])
            ->toArray();

        return view('tenant.imports.deals.settings', compact('tenant', 'settings', 'templates', 'templateKeys'));
    }

    public function updateSettings(string $tenantId, Request $request): RedirectResponse
    {
        $this->guardCheck();
        $this->resolveTenant($tenantId);
        abort_unless(
            $this->authRole() === 'tenant_admin',
            403,
            'Only Tenant Admins can change import settings.'
        );

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
