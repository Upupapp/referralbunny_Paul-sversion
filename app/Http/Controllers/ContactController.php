<?php

namespace App\Http\Controllers;

use App\Services\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ContactController extends Controller
{
    // ── Contact CRUD ─────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        // Derive tenant from authenticated context, never from user input
        $tenantId = TenantContext::id();
        if (!$tenantId && !TenantContext::isSuperAdmin()) {
            abort(403, 'Tenant context required.');
        }

        $contacts = DB::table('contacts as c')
            ->leftJoin('organizations as o', 'c.organization_id', '=', 'o.id')
            ->leftJoin(
                DB::raw('(SELECT contact_id, COUNT(*) as deal_count FROM deal_contacts GROUP BY contact_id) dc'),
                'c.id', '=', 'dc.contact_id'
            )
            ->leftJoin(
                DB::raw("(
                    SELECT DISTINCT ON (contact_id)
                        contact_id,
                        id              AS role_invite_id,
                        invited_role    AS role_invite_role,
                        status          AS role_invite_status,
                        associated_deal_id AS role_invite_deal_id
                    FROM contact_role_invitations
                    ORDER BY contact_id, created_at DESC
                ) cri"),
                'c.id', '=', 'cri.contact_id'
            )
            ->when($tenantId, fn($q) => $q->where('c.tenant_id', $tenantId))
            ->select(
                'c.*',
                'o.name as org_name',
                DB::raw('COALESCE(dc.deal_count, 0) as deal_count'),
                'cri.role_invite_id',
                'cri.role_invite_role',
                'cri.role_invite_status',
                'cri.role_invite_deal_id'
            )
            ->orderBy('c.first_name')
            ->get();

        return response()->json($contacts);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'first_name'      => 'required|string|max:100',
            'last_name'       => 'nullable|string|max:100',
            'email'           => 'nullable|email|max:255',
            'phone'           => 'nullable|string|max:50',
            'job_title'       => 'nullable|string|max:150',
            'organization_id' => 'nullable|string|exists:organizations,id',
            'status'          => 'nullable|in:active,inactive,prospect',
            'notes'           => 'nullable|string',
        ]);

        // Derive tenant from authenticated context, never from user input
        $tenantId = TenantContext::requireId();

        $id = (string) Str::uuid();
        DB::table('contacts')->insert([
            'id'              => $id,
            'tenant_id'       => $tenantId,
            'first_name'      => $data['first_name'],
            'last_name'       => $data['last_name'] ?? null,
            'email'           => $data['email'] ?? null,
            'phone'           => $data['phone'] ?? null,
            'job_title'       => $data['job_title'] ?? null,
            'organization_id' => $data['organization_id'] ?? null,
            'status'          => $data['status'] ?? 'active',
            'notes'           => $data['notes'] ?? null,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        return response()->json($this->contactWithMeta($id), 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'first_name'      => 'sometimes|string|max:100',
            'last_name'       => 'nullable|string|max:100',
            'email'           => 'nullable|email|max:255',
            'phone'           => 'nullable|string|max:50',
            'job_title'       => 'nullable|string|max:150',
            'organization_id' => 'nullable|string|exists:organizations,id',
            'status'          => 'sometimes|in:active,inactive,prospect',
            'notes'           => 'nullable|string',
        ]);

        DB::table('contacts')
            ->where('id', $id)
            ->update(array_merge($data, ['updated_at' => now()]));

        return response()->json($this->contactWithMeta($id));
    }

    public function destroy(string $id): JsonResponse
    {
        DB::table('contacts')->where('id', $id)->delete();
        return response()->json(['deleted' => true]);
    }

    // ── Deal ↔ Contact associations ───────────────────────────────────────

    public function forDeal(string $dealId): JsonResponse
    {
        $contacts = DB::table('deal_contacts as dc')
            ->join('contacts as c', 'dc.contact_id', '=', 'c.id')
            ->leftJoin('organizations as o', 'c.organization_id', '=', 'o.id')
            ->where('dc.deal_id', $dealId)
            ->select('c.*', 'o.name as org_name', 'dc.role as deal_role', 'dc.id as deal_contact_id')
            ->orderBy('c.first_name')
            ->get();

        return response()->json($contacts);
    }

    public function linkToDeal(Request $request, string $dealId): JsonResponse
    {
        $data = $request->validate([
            'contact_id' => 'required|string|exists:contacts,id',
            'role'       => 'nullable|string|max:100',
        ]);

        // Derive tenant from authenticated context, never from user input
        $tenantId = TenantContext::requireId();

        if (DB::table('deal_contacts')->where('deal_id', $dealId)->where('contact_id', $data['contact_id'])->exists()) {
            return response()->json(['error' => 'Contact already linked to this deal'], 422);
        }

        DB::table('deal_contacts')->insert([
            'id'         => (string) Str::uuid(),
            'tenant_id'  => $tenantId,
            'deal_id'    => $dealId,
            'contact_id' => $data['contact_id'],
            'role'       => $data['role'] ?? null,
            'created_at' => now(),
        ]);

        $contact = DB::table('contacts as c')
            ->leftJoin('organizations as o', 'c.organization_id', '=', 'o.id')
            ->join('deal_contacts as dc', function ($j) use ($dealId) {
                $j->on('c.id', '=', 'dc.contact_id')->where('dc.deal_id', '=', $dealId);
            })
            ->where('c.id', $data['contact_id'])
            ->select('c.*', 'o.name as org_name', 'dc.role as deal_role', 'dc.id as deal_contact_id')
            ->first();

        return response()->json($contact, 201);
    }

    public function unlinkFromDeal(string $dealId, string $contactId): JsonResponse
    {
        DB::table('deal_contacts')
            ->where('deal_id', $dealId)
            ->where('contact_id', $contactId)
            ->delete();

        return response()->json(['unlinked' => true]);
    }

    // ── Private helpers ───────────────────────────────────────────────────

    private function contactWithMeta(string $id): mixed
    {
        return DB::table('contacts as c')
            ->leftJoin('organizations as o', 'c.organization_id', '=', 'o.id')
            ->leftJoin(
                DB::raw('(SELECT contact_id, COUNT(*) as deal_count FROM deal_contacts GROUP BY contact_id) dc'),
                'c.id', '=', 'dc.contact_id'
            )
            ->where('c.id', $id)
            ->select('c.*', 'o.name as org_name', DB::raw('COALESCE(dc.deal_count, 0) as deal_count'))
            ->first();
    }
}
