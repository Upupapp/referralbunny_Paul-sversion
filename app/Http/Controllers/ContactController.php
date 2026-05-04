<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ContactController extends Controller
{
    // ── Contact CRUD ─────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $contacts = DB::table('contacts as c')
            ->leftJoin('organizations as o', 'c.organization_id', '=', 'o.id')
            ->leftJoin(
                DB::raw('(SELECT contact_id, COUNT(*) as deal_count FROM deal_contacts GROUP BY contact_id) dc'),
                'c.id', '=', 'dc.contact_id'
            )
            ->where('c.tenant_id', $request->tenant_id)
            ->select(
                'c.*',
                'o.name as org_name',
                DB::raw('COALESCE(dc.deal_count, 0) as deal_count')
            )
            ->orderBy('c.first_name')
            ->get();

        return response()->json($contacts);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tenant_id'       => 'required|string|exists:tenants,id',
            'first_name'      => 'required|string|max:100',
            'last_name'       => 'nullable|string|max:100',
            'email'           => 'nullable|email|max:255',
            'phone'           => 'nullable|string|max:50',
            'job_title'       => 'nullable|string|max:150',
            'organization_id' => 'nullable|string|exists:organizations,id',
            'status'          => 'nullable|in:active,inactive,prospect',
            'notes'           => 'nullable|string',
        ]);

        $id = (string) Str::uuid();
        DB::table('contacts')->insert([
            'id'              => $id,
            'tenant_id'       => $data['tenant_id'],
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
            'tenant_id'  => 'required|string|exists:tenants,id',
            'contact_id' => 'required|string|exists:contacts,id',
            'role'       => 'nullable|string|max:100',
        ]);

        if (DB::table('deal_contacts')->where('deal_id', $dealId)->where('contact_id', $data['contact_id'])->exists()) {
            return response()->json(['error' => 'Contact already linked to this deal'], 422);
        }

        DB::table('deal_contacts')->insert([
            'id'         => (string) Str::uuid(),
            'tenant_id'  => $data['tenant_id'],
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
