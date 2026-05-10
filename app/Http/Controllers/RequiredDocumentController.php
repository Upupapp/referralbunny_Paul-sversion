<?php

namespace App\Http\Controllers;

use App\Services\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RequiredDocumentController extends Controller
{
    // ── Document requirements CRUD ────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $docs = DB::table('reseller_required_documents')
            ->where('tenant_id', TenantContext::id())
            ->orderBy('display_order')
            ->orderBy('created_at')
            ->get();

        return response()->json($docs);
    }

    public function store(Request $request): JsonResponse
    {
        $tenantId = TenantContext::requireId();

        $data = $request->validate([
            'label'           => 'required|string|max:150',
            'document_type'   => 'required|string|max:100',
            'description'     => 'nullable|string',
            'accepted_formats'=> 'nullable|string|max:255',
            'is_required'     => 'boolean',
            'display_order'   => 'nullable|integer',
        ]);

        $id = (string) Str::uuid();
        DB::table('reseller_required_documents')->insert([
            'id'               => $id,
            'tenant_id'        => $tenantId,
            'label'            => $data['label'],
            'document_type'    => $data['document_type'],
            'description'      => $data['description'] ?? null,
            'accepted_formats' => $data['accepted_formats'] ?? 'PDF, JPG, PNG',
            'is_required'      => $data['is_required'] ?? true,
            'is_active'        => true,
            'display_order'    => $data['display_order'] ?? 0,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        return response()->json(DB::table('reseller_required_documents')->where('id', $id)->first(), 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'label'           => 'sometimes|string|max:150',
            'document_type'   => 'sometimes|string|max:100',
            'description'     => 'nullable|string',
            'accepted_formats'=> 'nullable|string|max:255',
            'is_required'     => 'sometimes|boolean',
            'is_active'       => 'sometimes|boolean',
            'display_order'   => 'nullable|integer',
        ]);

        DB::table('reseller_required_documents')
            ->where('id', $id)
            ->where('tenant_id', TenantContext::requireId())
            ->update(array_merge($data, ['updated_at' => now()]));

        return response()->json(DB::table('reseller_required_documents')->where('id', $id)->first());
    }

    public function destroy(string $id): JsonResponse
    {
        DB::table('reseller_required_documents')
            ->where('id', $id)
            ->where('tenant_id', TenantContext::requireId())
            ->delete();
        return response()->json(['deleted' => true]);
    }

    // ── Compliance & Submissions ───────────────────────────────────────────

    /**
     * GET /api/required-documents/compliance
     * Per-reseller summary: how many required docs each reseller has approved
     */
    public function compliance(Request $request): JsonResponse
    {
        $tenantId = TenantContext::id();

        $requiredCount = DB::table('reseller_required_documents')
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where('is_required', true)
            ->count();

        $resellers = DB::table('resellers')
            ->where('tenant_id', $tenantId)
            ->select('id', 'name')
            ->get();

        $result = $resellers->map(function ($r) use ($tenantId, $requiredCount) {
            $approvedCount = DB::table('reseller_document_submissions as ds')
                ->join('reseller_required_documents as rd', 'ds.required_document_id', '=', 'rd.id')
                ->where('ds.reseller_id', $r->id)
                ->where('ds.status', 'approved')
                ->where('rd.is_required', true)
                ->where('rd.is_active', true)
                ->count();

            return [
                'reseller_id'     => $r->id,
                'required_total'  => $requiredCount,
                'approved'        => $approvedCount,
                'fully_compliant' => $requiredCount === 0 || $approvedCount >= $requiredCount,
            ];
        });

        return response()->json([
            'required_documents' => $requiredCount,
            'resellers'          => $result,
        ]);
    }

    /**
     * GET /api/required-documents/reseller-status?reseller_id=
     * All active required docs + this reseller's submission status for each
     */
    public function resellerStatus(Request $request): JsonResponse
    {
        $resellerId = $request->reseller_id;
        $tenantId   = TenantContext::id();

        $docs = DB::table('reseller_required_documents as rd')
            ->leftJoin('reseller_document_submissions as ds', function ($join) use ($resellerId) {
                $join->on('rd.id', '=', 'ds.required_document_id')
                     ->where('ds.reseller_id', '=', $resellerId);
            })
            ->where('rd.tenant_id', $tenantId)
            ->where('rd.is_active', true)
            ->select(
                'rd.*',
                'ds.id as submission_id',
                'ds.file_url',
                'ds.file_name',
                'ds.status as submission_status',
                'ds.submitted_at',
                'ds.reviewed_at',
                'ds.reviewed_by',
                'ds.review_notes',
            )
            ->orderBy('rd.display_order')
            ->get();

        return response()->json($docs);
    }

    /**
     * POST /api/required-documents/{docId}/submit
     * Admin records that a reseller has submitted a document (with optional file URL)
     */
    public function recordSubmission(Request $request, string $docId): JsonResponse
    {
        $data = $request->validate([
            'reseller_id' => 'required|string|exists:resellers,id',
            'file_url'    => 'nullable|string|max:2048',
            'file_name'   => 'nullable|string|max:255',
        ]);

        $doc = DB::table('reseller_required_documents')->where('id', $docId)->first();
        if (!$doc) {
            return response()->json(['error' => 'Document not found'], 404);
        }

        if ($doc->tenant_id !== TenantContext::id()) {
            abort(403, 'You do not have access to this document requirement.');
        }

        $existing = DB::table('reseller_document_submissions')
            ->where('reseller_id', $data['reseller_id'])
            ->where('required_document_id', $docId)
            ->first();

        if ($existing) {
            DB::table('reseller_document_submissions')
                ->where('id', $existing->id)
                ->update([
                    'file_url'     => $data['file_url'] ?? $existing->file_url,
                    'file_name'    => $data['file_name'] ?? $existing->file_name,
                    'status'       => 'submitted',
                    'submitted_at' => now(),
                    'updated_at'   => now(),
                ]);
        } else {
            DB::table('reseller_document_submissions')->insert([
                'id'                   => (string) Str::uuid(),
                'tenant_id'            => $doc->tenant_id,
                'reseller_id'          => $data['reseller_id'],
                'required_document_id' => $docId,
                'file_url'             => $data['file_url'] ?? null,
                'file_name'            => $data['file_name'] ?? null,
                'status'               => 'submitted',
                'submitted_at'         => now(),
                'created_at'           => now(),
                'updated_at'           => now(),
            ]);
        }

        return response()->json(['submitted' => true, 'submitted_at' => now()]);
    }

    /**
     * PATCH /api/document-submissions/{submissionId}/review
     * Admin approves or rejects a document submission
     */
    public function review(Request $request, string $submissionId): JsonResponse
    {
        $submission = DB::table('reseller_document_submissions')->where('id', $submissionId)->first();
        if (!$submission) {
            return response()->json(['error' => 'Submission not found'], 404);
        }

        if ($submission->tenant_id !== TenantContext::id()) {
            abort(403, 'You do not have access to this submission.');
        }

        $data = $request->validate([
            'status'       => 'required|in:approved,rejected',
            'reviewed_by'  => 'nullable|string',
            'review_notes' => 'nullable|string',
        ]);

        DB::table('reseller_document_submissions')
            ->where('id', $submissionId)
            ->update([
                'status'       => $data['status'],
                'reviewed_at'  => now(),
                'reviewed_by'  => $data['reviewed_by'] ?? null,
                'review_notes' => $data['review_notes'] ?? null,
                'updated_at'   => now(),
            ]);

        return response()->json(['reviewed' => true, 'status' => $data['status']]);
    }

    /**
     * DELETE /api/document-submissions/{submissionId}
     * Reset a submission (admin use — reseller must resubmit)
     */
    public function resetSubmission(string $submissionId): JsonResponse
    {
        $submission = DB::table('reseller_document_submissions')->where('id', $submissionId)->first();
        if ($submission && $submission->tenant_id !== TenantContext::id()) {
            abort(403, 'You do not have access to this submission.');
        }

        DB::table('reseller_document_submissions')->where('id', $submissionId)->delete();
        return response()->json(['reset' => true]);
    }
}
