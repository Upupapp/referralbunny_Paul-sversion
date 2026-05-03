<?php

namespace App\Http\Controllers;

use App\Models\DuplicateReviewItem;
use App\Models\ImportJob;
use App\Models\ImportMappingProfile;
use App\Models\ImportRow;
use App\Models\ImportRowError;
use App\Services\ImportMappingService;
use App\Services\ImportService;
use App\Services\ImportTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImportController extends Controller
{
    public function __construct(
        private ImportService         $imports,
        private ImportTemplateService $templates,
        private ImportMappingService  $mapping
    ) {}

    // ── Templates ─────────────────────────────────────────────

    // GET /api/imports/templates
    public function listTemplates(): JsonResponse
    {
        return response()->json($this->templates->getSupportedTypes());
    }

    // GET /api/imports/templates/{objectType}
    public function getTemplate(string $objectType): JsonResponse
    {
        $schema = $this->templates->getSchema($objectType);
        if (empty($schema)) return response()->json(['error' => 'Unknown object type.'], 404);
        return response()->json($schema);
    }

    // GET /api/imports/templates/{objectType}/download?format=csv&sample=1
    public function downloadTemplate(Request $request, string $objectType)
    {
        $withSample = $request->boolean('sample', false);
        return $this->templates->csvDownloadResponse($objectType, $withSample);
    }

    // ── Jobs ──────────────────────────────────────────────────

    // GET /api/imports/jobs
    public function index(Request $request): JsonResponse
    {
        $query = ImportJob::with('uploadedBy')
            ->orderByDesc('created_at');

        if ($request->filled('status'))       $query->where('status', $request->status);
        if ($request->filled('object_type'))  $query->where('object_type', $request->object_type);
        if ($request->filled('tenant_id'))    $query->where('tenant_id', $request->tenant_id);

        return response()->json($query->paginate(20));
    }

    // GET /api/imports/jobs/{job}
    public function show(ImportJob $job): JsonResponse
    {
        return response()->json($job->load('uploadedBy'));
    }

    // POST /api/imports/jobs
    public function create(Request $request): JsonResponse
    {
        $data = $request->validate([
            'object_type'   => 'required|string',
            'import_name'   => 'nullable|string|max:200',
            'import_type'   => 'nullable|in:quick,advanced,migration,sandbox',
            'import_mode'   => 'nullable|in:create_only,update_only,upsert,validate_only',
            'overwrite_mode'=> 'nullable|string',
            'tenant_id'     => 'nullable|string|exists:tenants,id',
            'pasted_content'=> 'nullable|string',
        ]);

        $file = $request->file('file');
        if (!$file && empty($data['pasted_content'])) {
            return response()->json(['error' => 'Either a file upload or pasted_content is required.'], 422);
        }

        $job = $this->imports->createJob(
            array_merge($data, ['user_id' => $request->user()->id]),
            $file,
            $data['pasted_content'] ?? null
        );

        return response()->json($job, 201);
    }

    // POST /api/imports/jobs/{job}/parse
    public function parse(ImportJob $job): JsonResponse
    {
        $result = $this->imports->parseStructure($job);
        return response()->json($result);
    }

    // GET /api/imports/jobs/{job}/mapping-suggestions
    public function mappingSuggestions(ImportJob $job): JsonResponse
    {
        $suggestions = $this->imports->detectMapping($job);
        $completeness = $this->mapping->validateMappingCompleteness($suggestions, $job->object_type);
        return response()->json(['suggestions' => $suggestions, 'completeness' => $completeness]);
    }

    // POST /api/imports/jobs/{job}/mapping
    public function saveMapping(Request $request, ImportJob $job): JsonResponse
    {
        $data = $request->validate([
            'mapping'      => 'required|array',
            'profile_name' => 'nullable|string|max:100',
        ]);

        $this->imports->saveMapping($job, $data['mapping']);

        // Save as profile if requested
        if (!empty($data['profile_name'])) {
            $fingerprint = $this->mapping->buildHeaderFingerprint($job->headers_detected_json ?? []);
            $this->mapping->saveProfile(
                $request->user()->id, $job->object_type, $data['mapping'],
                $data['profile_name'], $fingerprint, $job->tenant_id
            );
        }

        return response()->json(['message' => 'Mapping saved.', 'job' => $job->fresh()]);
    }

    // POST /api/imports/jobs/{job}/validate
    public function validate(ImportJob $job): JsonResponse
    {
        $summary = $this->imports->validateRows($job);
        return response()->json($summary);
    }

    // GET /api/imports/jobs/{job}/preview
    public function preview(ImportJob $job): JsonResponse
    {
        $rows = ImportRow::where('import_job_id', $job->id)
            ->limit(100)
            ->get(['id', 'row_number', 'mapped_data_json', 'status', 'error_count', 'warning_count', 'matched_entity_id']);

        return response()->json([
            'job'     => $job,
            'rows'    => $rows,
            'summary' => $job->error_summary_json,
        ]);
    }

    // POST /api/imports/jobs/{job}/execute
    public function execute(Request $request, ImportJob $job): JsonResponse
    {
        if (!in_array($job->status, ['ready_for_review', 'approved'])) {
            return response()->json(['error' => 'Job is not ready for import. Current status: ' . $job->status], 422);
        }

        $result = $this->imports->execute($job);
        return response()->json($result);
    }

    // POST /api/imports/jobs/{job}/cancel
    public function cancel(ImportJob $job): JsonResponse
    {
        if (!$job->canCancel()) {
            return response()->json(['error' => 'This job cannot be canceled in its current state.'], 422);
        }
        $job->update(['status' => 'canceled', 'canceled_at' => now()]);
        return response()->json(['message' => 'Import canceled.']);
    }

    // POST /api/imports/jobs/{job}/rollback
    public function rollback(Request $request, ImportJob $job): JsonResponse
    {
        if (!$job->canRollback()) {
            return response()->json(['error' => 'This job is not eligible for rollback.'], 422);
        }
        $rollback = $this->imports->rollback($job, $request->user()->id);
        return response()->json($rollback);
    }

    // GET /api/imports/jobs/{job}/rows
    public function rows(Request $request, ImportJob $job): JsonResponse
    {
        $query = ImportRow::where('import_job_id', $job->id);
        if ($request->filled('status')) $query->where('status', $request->status);
        return response()->json($query->orderBy('row_number')->paginate(50));
    }

    // GET /api/imports/jobs/{job}/errors
    public function errors(ImportJob $job): JsonResponse
    {
        $errors = ImportRowError::where('import_job_id', $job->id)
            ->orderBy('row_number')
            ->get();
        return response()->json($errors);
    }

    // GET /api/imports/jobs/{job}/report/download
    public function downloadReport(ImportJob $job)
    {
        $errors = ImportRowError::where('import_job_id', $job->id)->get();
        $rows   = ImportRow::where('import_job_id', $job->id)->get();

        $lines   = [];
        $headers = ['row_number', 'status', 'error_type', 'error_message', 'column_name', 'suggested_fix'];
        $lines[] = implode(',', $headers);

        foreach ($errors as $err) {
            $lines[] = implode(',', array_map(fn($v) => '"' . str_replace('"', '""', $v ?? '') . '"', [
                $err->row_number, $err->severity, $err->error_type,
                $err->error_message, $err->column_name, $err->suggested_fix,
            ]));
        }

        $filename = 'import_errors_' . $job->id . '.csv';
        return response(implode("\n", $lines), 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    // ── Duplicates ────────────────────────────────────────────

    // GET /api/imports/duplicates
    public function duplicates(Request $request): JsonResponse
    {
        $query = DuplicateReviewItem::with('job')->where('status', 'pending');
        if ($request->filled('import_job_id')) $query->where('import_job_id', $request->import_job_id);
        return response()->json($query->orderByDesc('match_score')->paginate(20));
    }

    // POST /api/imports/duplicates/{item}/resolve
    public function resolveDuplicate(Request $request, DuplicateReviewItem $item): JsonResponse
    {
        $data = $request->validate([
            'action' => 'required|in:confirmed_duplicate,not_duplicate,merged,skipped',
        ]);
        $item->update([
            'status'      => $data['action'],
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);
        return response()->json($item);
    }

    // ── Mapping profiles ──────────────────────────────────────

    // GET /api/imports/mapping-profiles
    public function mappingProfiles(Request $request): JsonResponse
    {
        $profiles = ImportMappingProfile::where('user_id', $request->user()->id)
            ->when($request->filled('object_type'), fn($q) => $q->where('object_type', $request->object_type))
            ->orderByDesc('created_at')
            ->get();
        return response()->json($profiles);
    }

    // DELETE /api/imports/mapping-profiles/{profile}
    public function deleteMappingProfile(Request $request, ImportMappingProfile $profile): JsonResponse
    {
        if ($profile->user_id !== $request->user()->id) return response()->json(['error' => 'Not authorized.'], 403);
        $profile->delete();
        return response()->json(['message' => 'Profile deleted.']);
    }

    // ── Stats ─────────────────────────────────────────────────

    // GET /api/imports/stats
    public function stats(): JsonResponse
    {
        return response()->json([
            'active_jobs'      => ImportJob::whereIn('status', ['importing', 'validating_rows', 'parsing'])->count(),
            'pending_approval' => ImportJob::where('status', 'waiting_for_approval')->count(),
            'failed_jobs'      => ImportJob::where('status', 'failed')->whereDate('created_at', '>=', now()->subDays(7))->count(),
            'completed_today'  => ImportJob::where('status', 'completed')->whereDate('completed_at', today())->count(),
            'pending_duplicates' => DuplicateReviewItem::where('status', 'pending')->count(),
        ]);
    }
}
