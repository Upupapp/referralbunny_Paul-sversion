<?php

namespace App\Http\Controllers;

use App\Services\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AgreementController extends Controller
{
    // ── Agreement Files (admin CRUD) ──────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $agreements = DB::table('reseller_agreement_files')
            ->where('tenant_id', TenantContext::id())
            ->orderBy('display_order')
            ->orderBy('created_at')
            ->get();

        return response()->json($agreements);
    }

    public function store(Request $request): JsonResponse
    {
        $tenantId = TenantContext::requireId();

        $data = $request->validate([
            'label'          => 'required|string|max:100',
            'description'    => 'nullable|string',
            'file_url'       => 'nullable|string|max:2048',
            'is_required'    => 'boolean',
            'version'        => 'nullable|string|max:20',
            'effective_date' => 'nullable|date',
            'display_order'  => 'nullable|integer',
        ]);

        $id = (string) Str::uuid();
        DB::table('reseller_agreement_files')->insert([
            'id'             => $id,
            'tenant_id'      => $tenantId,
            'label'          => $data['label'],
            'description'    => $data['description'] ?? null,
            'file_url'       => $data['file_url'] ?? null,
            'is_required'    => $data['is_required'] ?? true,
            'version'        => $data['version'] ?? '1.0',
            'effective_date' => $data['effective_date'] ?? null,
            'display_order'  => $data['display_order'] ?? 0,
            'is_active'      => true,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        return response()->json(DB::table('reseller_agreement_files')->where('id', $id)->first(), 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'label'          => 'sometimes|string|max:100',
            'description'    => 'nullable|string',
            'file_url'       => 'nullable|string|max:2048',
            'is_required'    => 'sometimes|boolean',
            'is_active'      => 'sometimes|boolean',
            'version'        => 'nullable|string|max:20',
            'effective_date' => 'nullable|date',
            'display_order'  => 'nullable|integer',
        ]);

        DB::table('reseller_agreement_files')
            ->where('id', $id)
            ->where('tenant_id', TenantContext::requireId())
            ->update(array_merge($data, ['updated_at' => now()]));

        return response()->json(DB::table('reseller_agreement_files')->where('id', $id)->first());
    }

    public function destroy(string $id): JsonResponse
    {
        DB::table('reseller_agreement_files')
            ->where('id', $id)
            ->where('tenant_id', TenantContext::requireId())
            ->delete();
        return response()->json(['deleted' => true]);
    }

    // ── Acknowledgments ──────────────────────────────────────────────────

    /**
     * GET /api/agreements/reseller-status?reseller_id=
     * All active agreements + whether this reseller acknowledged each one
     */
    public function resellerStatus(Request $request): JsonResponse
    {
        $resellerId = $request->reseller_id;
        $tenantId   = TenantContext::id();

        $agreements = DB::table('reseller_agreement_files as af')
            ->leftJoin('reseller_agreement_acknowledgments as ack', function ($join) use ($resellerId) {
                $join->on('af.id', '=', 'ack.agreement_file_id')
                     ->where('ack.reseller_id', '=', $resellerId);
            })
            ->where('af.tenant_id', $tenantId)
            ->where('af.is_active', true)
            ->select('af.*', 'ack.agreed_at', 'ack.agreed_by_name')
            ->orderBy('af.display_order')
            ->get();

        return response()->json($agreements);
    }

    /**
     * GET /api/agreements/compliance
     * Per-reseller summary: how many required agreements each reseller has signed
     */
    public function compliance(Request $request): JsonResponse
    {
        $tenantId = TenantContext::id();

        $requiredCount = DB::table('reseller_agreement_files')
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where('is_required', true)
            ->count();

        $resellers = DB::table('resellers')
            ->where('tenant_id', $tenantId)
            ->select('id', 'name')
            ->get();

        // Single query: count required acknowledgments per reseller
        $ackCounts = DB::table('reseller_agreement_acknowledgments as ack')
            ->join('reseller_agreement_files as af', 'ack.agreement_file_id', '=', 'af.id')
            ->where('af.tenant_id', $tenantId)
            ->where('af.is_required', true)
            ->where('af.is_active', true)
            ->whereIn('ack.reseller_id', $resellers->pluck('id'))
            ->groupBy('ack.reseller_id')
            ->pluck(DB::raw('COUNT(*) as cnt'), 'ack.reseller_id')
            ->map(fn($v) => (int) $v);

        $result = $resellers->map(fn($r) => [
            'reseller_id'     => $r->id,
            'required_total'  => $requiredCount,
            'acknowledged'    => $ackCounts->get($r->id, 0),
            'fully_compliant' => $requiredCount === 0 || $ackCounts->get($r->id, 0) >= $requiredCount,
        ]);

        return response()->json([
            'required_agreements' => $requiredCount,
            'resellers'           => $result,
        ]);
    }

    /**
     * POST /api/agreements/{agreementId}/acknowledge
     * Record that a reseller has agreed (admin marks on behalf of reseller)
     */
    public function acknowledge(Request $request, string $agreementId): JsonResponse
    {
        $data = $request->validate([
            'reseller_id'    => 'required|string|exists:resellers,id',
            'agreed_by_name' => 'nullable|string',
        ]);

        $agreement = DB::table('reseller_agreement_files')->where('id', $agreementId)->first();
        if (!$agreement) {
            return response()->json(['error' => 'Agreement not found'], 404);
        }

        if ($agreement->tenant_id !== TenantContext::id()) {
            abort(403, 'You do not have access to this agreement.');
        }

        $existing = DB::table('reseller_agreement_acknowledgments')
            ->where('reseller_id', $data['reseller_id'])
            ->where('agreement_file_id', $agreementId)
            ->first();

        if ($existing) {
            DB::table('reseller_agreement_acknowledgments')
                ->where('id', $existing->id)
                ->update([
                    'agreed_at'      => now(),
                    'agreed_by_name' => $data['agreed_by_name'] ?? null,
                    'ip_address'     => $request->ip(),
                ]);
        } else {
            DB::table('reseller_agreement_acknowledgments')->insert([
                'id'               => (string) Str::uuid(),
                'tenant_id'        => $agreement->tenant_id,
                'reseller_id'      => $data['reseller_id'],
                'agreement_file_id' => $agreementId,
                'agreed_at'        => now(),
                'agreed_by_name'   => $data['agreed_by_name'] ?? null,
                'ip_address'       => $request->ip(),
                'created_at'       => now(),
            ]);
        }

        return response()->json(['acknowledged' => true, 'agreed_at' => now()]);
    }

    /**
     * DELETE /api/agreements/{agreementId}/acknowledge?reseller_id=
     * Revoke an acknowledgment (admin use)
     */
    public function revokeAcknowledgment(Request $request, string $agreementId): JsonResponse
    {
        $agreement = DB::table('reseller_agreement_files')->where('id', $agreementId)->first();
        if (!$agreement || $agreement->tenant_id !== TenantContext::id()) {
            abort(403, 'You do not have access to this agreement.');
        }

        DB::table('reseller_agreement_acknowledgments')
            ->where('agreement_file_id', $agreementId)
            ->where('reseller_id', $request->reseller_id)
            ->delete();

        return response()->json(['revoked' => true]);
    }
}
