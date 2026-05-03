<?php

namespace App\Services;

use App\Models\BillingAuditLog;
use App\Models\DuplicateReviewItem;
use App\Models\ImportFieldChange;
use App\Models\ImportJob;
use App\Models\ImportRow;
use App\Models\ImportRowError;
use App\Models\ImportRollback;
use App\Models\ImportSnapshot;
use App\Models\Lead;
use App\Models\Reseller;
use App\Models\Tenant;
use App\Models\UsageMetric;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImportService
{
    public function __construct(
        private ImportMappingService    $mapping,
        private ImportValidationService $validation,
        private ImportTemplateService   $templates,
        private NotificationService     $notifications
    ) {}

    // ── Step 1: Create job + upload file ─────────────────────

    public function createJob(array $options, ?UploadedFile $file = null, ?string $pastedContent = null): ImportJob
    {
        $importName = $options['import_name'] ?? $this->autoName($options['object_type']);

        $job = ImportJob::create([
            'import_name'  => $importName,
            'import_type'  => $options['import_type']  ?? 'quick',
            'object_type'  => $options['object_type'],
            'tenant_id'    => $options['tenant_id']    ?? null,
            'uploaded_by'  => $options['user_id'],
            'source_type'  => $file ? 'file_upload' : 'pasted_spreadsheet',
            'import_mode'  => $options['import_mode']  ?? 'upsert',
            'overwrite_mode'=> $options['overwrite_mode'] ?? 'overwrite_mapped',
            'status'       => 'uploaded',
            'rollback_available_until' => now()->addDays(30),
        ]);

        if ($file) {
            $path = $file->storeAs('imports/' . $job->id, $file->getClientOriginalName(), 'local');
            $job->update([
                'file_name' => $file->getClientOriginalName(),
                'file_size' => $file->getSize(),
                'file_type' => $file->getClientOriginalExtension(),
                'raw_file_path' => $path,
            ]);
        }

        if ($pastedContent) {
            $path = 'imports/' . $job->id . '/pasted.csv';
            Storage::disk('local')->put($path, $pastedContent);
            $job->update(['raw_file_path' => $path, 'file_name' => 'pasted_data.csv', 'file_type' => 'csv']);
        }

        BillingAuditLog::log('import_job_created', [
            'entity_type'  => 'import_job',
            'entity_id'    => $job->id,
            'performed_by' => $options['user_id'],
            'after'        => ['object_type' => $job->object_type, 'source' => $job->source_type],
        ]);

        return $job;
    }

    // ── Step 2: Parse file structure ──────────────────────────

    public function parseStructure(ImportJob $job): array
    {
        $job->update(['status' => 'parsing']);

        try {
            $content = Storage::disk('local')->get($job->raw_file_path);
            $rows    = $this->parseCsvContent($content);

            if (empty($rows)) {
                $job->update(['status' => 'failed', 'error_summary_json' => ['error' => 'File is empty or could not be read.']]);
                return ['success' => false, 'error' => 'File is empty.'];
            }

            $headers = array_shift($rows); // First row = headers

            // Detect issues
            $duplicateHeaders = array_diff_key($headers, array_unique($headers));
            $emptyHeaders     = array_filter($headers, fn($h) => trim($h) === '');

            $job->update([
                'status'              => 'mapping_required',
                'total_rows'          => count($rows),
                'headers_detected_json' => $headers,
            ]);

            return [
                'success'           => true,
                'headers'           => $headers,
                'row_count'         => count($rows),
                'duplicate_headers' => $duplicateHeaders,
                'empty_headers'     => $emptyHeaders,
            ];
        } catch (\Throwable $e) {
            $job->update(['status' => 'failed', 'error_summary_json' => ['error' => $e->getMessage()]]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // ── Step 3: Detect + save column mapping ─────────────────

    public function detectMapping(ImportJob $job): array
    {
        $headers = $job->headers_detected_json ?? [];
        return $this->mapping->detectMapping($headers, $job->object_type);
    }

    public function saveMapping(ImportJob $job, array $columnMapping): void
    {
        $job->update([
            'column_mapping_json' => $columnMapping,
            'status'              => 'validating_rows',
        ]);
    }

    // ── Step 4: Validate all rows ─────────────────────────────

    public function validateRows(ImportJob $job): array
    {
        $job->update(['status' => 'validating_rows']);

        $content = Storage::disk('local')->get($job->raw_file_path);
        $rows    = $this->parseCsvContent($content);
        array_shift($rows); // remove header row

        $mapping   = $job->column_mapping_json ?? [];
        $summary   = ['total_rows' => 0, 'error_rows' => 0, 'warning_rows' => 0, 'valid_rows' => 0, 'duplicate_rows' => 0];
        $allMapped = [];

        foreach ($rows as $i => $rawRow) {
            $rawAssoc = $this->buildAssocRow($rawRow, $job->headers_detected_json ?? []);
            $mapped   = $this->mapping->applyMapping($rawAssoc, $mapping);
            $allMapped[] = $mapped;
        }

        foreach ($allMapped as $i => $mapped) {
            $rowNum    = $i + 2; // 1-indexed, +1 for header
            $result    = $this->validation->validateRow($mapped, $job->object_type, $rowNum);
            $existing  = $this->validation->checkDuplicateInDb($mapped, $job->object_type);
            $inFileDup = $this->validation->checkDuplicateInFile($allMapped, $i, $job->object_type);
            $summary['total_rows']++;

            $status = 'valid_new';
            if (!empty($result['errors'])) {
                $status = 'error';
                $summary['error_rows']++;
            } elseif (!empty($result['warnings'])) {
                $status = 'warning';
                $summary['warning_rows']++;
            } elseif ($existing) {
                $status = 'valid_update';
            }

            if ($inFileDup) {
                $summary['duplicate_rows']++;
            }

            if ($status !== 'error') $summary['valid_rows']++;

            $importRow = ImportRow::create([
                'import_job_id'     => $job->id,
                'row_number'        => $rowNum,
                'raw_data_json'     => $allMapped[$i],
                'mapped_data_json'  => $mapped,
                'matched_entity_id' => $existing['id'] ?? null,
                'status'            => $status,
                'error_count'       => count($result['errors']),
                'warning_count'     => count($result['warnings']),
            ]);

            foreach ($result['errors'] as $err) {
                ImportRowError::create([
                    'import_job_id' => $job->id,
                    'import_row_id' => $importRow->id,
                    'row_number'    => $rowNum,
                    'column_name'   => $err['col'],
                    'error_type'    => $err['type'],
                    'error_message' => $err['msg'],
                    'severity'      => $err['severity'],
                    'suggested_fix' => $err['fix'],
                ]);
            }

            // Duplicate review items
            if ($existing && $inFileDup === false) {
                DuplicateReviewItem::create([
                    'import_job_id'           => $job->id,
                    'object_type'             => $job->object_type,
                    'uploaded_row_data_json'  => $mapped,
                    'possible_match_entity_id'=> $existing['id'],
                    'possible_match_data_json'=> $existing,
                    'match_score'             => 90,
                    'matched_fields_json'     => ['email'],
                ]);
            }
        }

        $riskLevel = $this->validation->calculateRiskLevel([
            'total_rows'       => $summary['total_rows'],
            'records_to_update'=> $summary['valid_rows'] - $summary['error_rows'],
        ]);

        $qualityScore = $this->validation->calculateDataQualityScore($summary);

        $job->update([
            'status'             => 'ready_for_review',
            'total_rows'         => $summary['total_rows'],
            'failed_rows'        => $summary['error_rows'],
            'warning_rows'       => $summary['warning_rows'],
            'successful_rows'    => $summary['valid_rows'],
            'risk_level'         => $riskLevel,
            'approval_required'  => $this->validation->requiresApproval($riskLevel),
            'error_summary_json' => array_merge($summary, ['quality_score' => $qualityScore]),
        ]);

        return array_merge($summary, ['risk_level' => $riskLevel, 'quality_score' => $qualityScore]);
    }

    // ── Step 5: Execute import ────────────────────────────────

    public function execute(ImportJob $job): array
    {
        if ($job->approval_required && $job->status === 'waiting_for_approval') {
            return ['success' => false, 'error' => 'Import is waiting for approval.'];
        }

        $job->update(['status' => 'importing', 'started_at' => now()]);

        $rows      = ImportRow::where('import_job_id', $job->id)->whereIn('status', ['valid_new', 'valid_update', 'warning'])->get();
        $created   = 0;
        $updated   = 0;
        $failed    = 0;

        foreach ($rows as $row) {
            try {
                DB::beginTransaction();

                $result = match ($job->object_type) {
                    'tenant'   => $this->importTenant($job, $row),
                    'reseller' => $this->importReseller($job, $row),
                    'lead'     => $this->importLead($job, $row),
                    default    => ['action' => 'skipped'],
                };

                if (($result['action'] ?? '') === 'created') { $created++; $row->update(['status' => 'imported', 'created_entity_id' => $result['id']]); }
                elseif (($result['action'] ?? '') === 'updated') { $updated++; $row->update(['status' => 'imported', 'updated_entity_id' => $result['id']]); }
                else { $row->update(['status' => 'skipped']); }

                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                $row->update(['status' => 'failed']);
                ImportRowError::create([
                    'import_job_id' => $job->id,
                    'import_row_id' => $row->id,
                    'row_number'    => $row->row_number,
                    'error_type'    => 'import_error',
                    'error_message' => $e->getMessage(),
                    'severity'      => 'blocking',
                ]);
                $failed++;
            }
        }

        $status = $failed > 0 ? 'completed_with_errors' : 'completed';
        $job->update([
            'status'          => $status,
            'successful_rows' => $created + $updated,
            'failed_rows'     => $failed,
            'completed_at'    => now(),
        ]);

        BillingAuditLog::log('import_completed', [
            'entity_type'  => 'import_job',
            'entity_id'    => $job->id,
            'performed_by' => $job->uploaded_by,
            'after'        => compact('created', 'updated', 'failed'),
        ]);

        $this->notifications->send(
            category:  'system',
            type:      $failed > 0 ? 'warning' : 'info',
            priority:  $failed > 0 ? 'high' : 'low',
            message:   "Import '{$job->import_name}' completed. {$created} created, {$updated} updated, {$failed} failed.",
            actionUrl: '/platform/import/' . $job->id,
            channel:   'in_app',
            metadata:  ['import_job_id' => $job->id],
        );

        return compact('created', 'updated', 'failed', 'status');
    }

    // ── Rollback ──────────────────────────────────────────────

    public function rollback(ImportJob $job, int $requestedBy): ImportRollback
    {
        $rollback = ImportRollback::create([
            'import_job_id' => $job->id,
            'requested_by'  => $requestedBy,
            'status'        => 'running',
        ]);

        $snapshots   = ImportSnapshot::where('import_job_id', $job->id)->get();
        $restored    = 0;
        $deleted     = 0;

        foreach ($snapshots as $snap) {
            try {
                if ($snap->action === 'created') {
                    // Delete the created record
                    $model = $this->resolveModel($snap->entity_type);
                    $model::where('id', $snap->entity_id)->delete();
                    $deleted++;
                } elseif ($snap->action === 'updated') {
                    // Restore old values
                    $model = $this->resolveModel($snap->entity_type);
                    $model::where('id', $snap->entity_id)->update($snap->old_values_json);
                    $restored++;
                }
            } catch (\Throwable) {
                // Continue with other records
            }
        }

        $rollback->update([
            'status'                => 'completed',
            'records_restored'      => $restored,
            'records_deleted'       => $deleted,
            'rollback_summary_json' => compact('restored', 'deleted'),
            'completed_at'          => now(),
        ]);

        $job->update(['status' => 'rolled_back', 'rollback_status' => 'completed']);

        return $rollback;
    }

    // ── Object importers ──────────────────────────────────────

    private function importTenant(ImportJob $job, ImportRow $row): array
    {
        $data     = $row->mapped_data_json;
        $existing = $row->matched_entity_id ? Tenant::find($row->matched_entity_id) : null;

        $payload = array_filter([
            'name'         => $data['tenant_name']            ?? null,
            'industry'     => $data['industry']               ?? null,
            'admin_email'  => $data['primary_admin_email']    ?? null,
            'admin_name'   => trim(($data['primary_admin_first_name'] ?? '') . ' ' . ($data['primary_admin_last_name'] ?? '')),
            'status'       => $data['tenant_status']          ?? 'trial',
            'slug'         => $data['tenant_slug']            ?? Str::slug($data['tenant_name'] ?? ''),
            'program_name' => $data['tenant_name']            ?? null,
        ], fn($v) => $v !== null && $v !== '');

        if ($existing && $job->import_mode !== 'create_only') {
            ImportSnapshot::create(['import_job_id' => $job->id, 'entity_type' => 'tenant', 'entity_id' => $existing->id, 'action' => 'updated', 'old_values_json' => $existing->toArray(), 'new_values_json' => $payload]);
            $existing->update($payload);
            return ['action' => 'updated', 'id' => $existing->id];
        }

        if (!$existing && $job->import_mode !== 'update_only') {
            $tenant = Tenant::create(array_merge($payload, ['id' => (string) Str::uuid()]));
            UsageMetric::firstOrCreate(['tenant_id' => $tenant->id, 'billing_period_start' => now()->startOfMonth()->toDateString()], ['billing_period_end' => now()->endOfMonth()->toDateString()]);
            ImportSnapshot::create(['import_job_id' => $job->id, 'entity_type' => 'tenant', 'entity_id' => $tenant->id, 'action' => 'created', 'old_values_json' => [], 'new_values_json' => $payload]);
            return ['action' => 'created', 'id' => $tenant->id];
        }

        return ['action' => 'skipped'];
    }

    private function importReseller(ImportJob $job, ImportRow $row): array
    {
        $data     = $row->mapped_data_json;
        $existing = $row->matched_entity_id ? Reseller::find($row->matched_entity_id) : null;

        $tenantId = $this->resolveTenantId($data['tenant_identifier'] ?? null);
        if (!$tenantId) return ['action' => 'skipped'];

        $payload = array_filter([
            'tenant_id' => $tenantId,
            'name'      => $data['reseller_name']   ?? null,
            'email'     => $data['reseller_email']  ?? null,
            'phone'     => $data['reseller_phone']  ?? null,
            'status'    => $data['reseller_status'] ?? 'invited',
            'territory' => $data['assigned_region'] ?? null,
        ], fn($v) => $v !== null && $v !== '');

        if ($existing && $job->import_mode !== 'create_only') {
            ImportSnapshot::create(['import_job_id' => $job->id, 'entity_type' => 'reseller', 'entity_id' => $existing->id, 'action' => 'updated', 'old_values_json' => $existing->toArray(), 'new_values_json' => $payload]);
            $existing->update($payload);
            return ['action' => 'updated', 'id' => $existing->id];
        }

        if (!$existing && $job->import_mode !== 'update_only') {
            $reseller = Reseller::create(array_merge($payload, ['id' => (string) Str::uuid()]));
            ImportSnapshot::create(['import_job_id' => $job->id, 'entity_type' => 'reseller', 'entity_id' => $reseller->id, 'action' => 'created', 'old_values_json' => [], 'new_values_json' => $payload]);
            return ['action' => 'created', 'id' => $reseller->id];
        }

        return ['action' => 'skipped'];
    }

    private function importLead(ImportJob $job, ImportRow $row): array
    {
        $data     = $row->mapped_data_json;
        $existing = $row->matched_entity_id ? Lead::find($row->matched_entity_id) : null;

        $tenantId = $this->resolveTenantId($data['tenant_identifier'] ?? null);
        if (!$tenantId) return ['action' => 'skipped'];

        $payload = array_filter([
            'tenant_id'    => $tenantId,
            'name'         => $data['lead_name']             ?? null,
            'status'       => $data['lead_status']           ?? 'active',
            'stage'        => $data['lead_status']           ?? 'introduction',
            'deal_value'   => is_numeric($data['deal_value'] ?? null) ? $data['deal_value'] : 0,
            'reseller_name'=> $data['referred_by_reseller_email'] ?? null,
            'days_left'    => 21,
            'commission_status' => 'pending',
        ], fn($v) => $v !== null && $v !== '');

        if ($existing && $job->import_mode !== 'create_only') {
            ImportSnapshot::create(['import_job_id' => $job->id, 'entity_type' => 'lead', 'entity_id' => $existing->id, 'action' => 'updated', 'old_values_json' => $existing->toArray(), 'new_values_json' => $payload]);
            $existing->update($payload);
            return ['action' => 'updated', 'id' => $existing->id];
        }

        if (!$existing && $job->import_mode !== 'update_only') {
            $lead = Lead::create(array_merge($payload, ['id' => (string) Str::uuid()]));
            ImportSnapshot::create(['import_job_id' => $job->id, 'entity_type' => 'lead', 'entity_id' => $lead->id, 'action' => 'created', 'old_values_json' => [], 'new_values_json' => $payload]);
            return ['action' => 'created', 'id' => $lead->id];
        }

        return ['action' => 'skipped'];
    }

    // ── Helpers ───────────────────────────────────────────────

    private function parseCsvContent(string $content): array
    {
        $rows   = [];
        $stream = fopen('data://text/plain,' . urlencode($content), 'r');
        if (!$stream) {
            // Fallback: split on newlines
            foreach (explode("\n", $content) as $line) {
                if (trim($line) === '') continue;
                $rows[] = str_getcsv($line);
            }
            return $rows;
        }

        while (($row = fgetcsv($stream)) !== false) {
            if (array_filter($row, fn($v) => trim($v) !== '')) {
                $rows[] = $row;
            }
        }
        fclose($stream);
        return $rows;
    }

    private function buildAssocRow(array $rawRow, array $headers): array
    {
        $assoc = [];
        foreach ($headers as $i => $header) {
            $assoc[$header] = $rawRow[$i] ?? null;
        }
        return $assoc;
    }

    private function resolveTenantId(?string $identifier): ?string
    {
        if (!$identifier) return null;
        $tenant = Tenant::where('id', $identifier)
            ->orWhere('slug', $identifier)
            ->orWhere('name', $identifier)
            ->orWhere('admin_email', $identifier)
            ->first();
        return $tenant?->id;
    }

    private function resolveModel(string $entityType): string
    {
        return match ($entityType) {
            'tenant'   => Tenant::class,
            'reseller' => Reseller::class,
            'lead'     => Lead::class,
            default    => throw new \InvalidArgumentException("Unknown entity type: {$entityType}"),
        };
    }

    private function autoName(string $objectType): string
    {
        return ucfirst($objectType) . ' Import — ' . now()->format('M d, Y H:i');
    }
}
