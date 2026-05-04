<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrganizationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $orgs = DB::table('organizations as o')
            ->leftJoin(
                DB::raw('(SELECT organization_id, COUNT(*) as contact_count FROM contacts GROUP BY organization_id) cc'),
                'o.id', '=', 'cc.organization_id'
            )
            ->leftJoin(
                DB::raw('(
                    SELECT c2.organization_id,
                           COUNT(DISTINCT dc.deal_id) as deal_count,
                           COALESCE(SUM(l.deal_value), 0) as deal_value
                    FROM contacts c2
                    JOIN deal_contacts dc ON c2.id = dc.contact_id
                    JOIN leads l ON dc.deal_id = l.id
                    GROUP BY c2.organization_id
                ) ds'),
                'o.id', '=', 'ds.organization_id'
            )
            ->where('o.tenant_id', $request->tenant_id)
            ->select(
                'o.*',
                DB::raw('COALESCE(cc.contact_count, 0) as contact_count'),
                DB::raw('COALESCE(ds.deal_count, 0) as deal_count'),
                DB::raw('COALESCE(ds.deal_value, 0) as deal_value')
            )
            ->orderBy('o.name')
            ->get();

        return response()->json($orgs);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tenant_id' => 'required|string|exists:tenants,id',
            'name'      => 'required|string|max:255',
            'industry'  => 'nullable|string|max:150',
            'website'   => 'nullable|string|max:500',
            'address'   => 'nullable|string',
            'city'      => 'nullable|string|max:100',
            'country'   => 'nullable|string|max:100',
            'notes'     => 'nullable|string',
        ]);

        $id = (string) Str::uuid();
        DB::table('organizations')->insert([
            'id'        => $id,
            'tenant_id' => $data['tenant_id'],
            'name'      => $data['name'],
            'industry'  => $data['industry'] ?? null,
            'website'   => $data['website'] ?? null,
            'address'   => $data['address'] ?? null,
            'city'      => $data['city'] ?? null,
            'country'   => $data['country'] ?? 'Philippines',
            'notes'     => $data['notes'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json($this->orgWithMeta($id), 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'name'     => 'sometimes|string|max:255',
            'industry' => 'nullable|string|max:150',
            'website'  => 'nullable|string|max:500',
            'address'  => 'nullable|string',
            'city'     => 'nullable|string|max:100',
            'country'  => 'nullable|string|max:100',
            'notes'    => 'nullable|string',
        ]);

        DB::table('organizations')
            ->where('id', $id)
            ->update(array_merge($data, ['updated_at' => now()]));

        return response()->json($this->orgWithMeta($id));
    }

    public function destroy(string $id): JsonResponse
    {
        DB::table('organizations')->where('id', $id)->delete();
        return response()->json(['deleted' => true]);
    }

    private function orgWithMeta(string $id): mixed
    {
        return DB::table('organizations as o')
            ->leftJoin(
                DB::raw('(SELECT organization_id, COUNT(*) as contact_count FROM contacts GROUP BY organization_id) cc'),
                'o.id', '=', 'cc.organization_id'
            )
            ->where('o.id', $id)
            ->select('o.*', DB::raw('COALESCE(cc.contact_count, 0) as contact_count'),
                DB::raw('0 as deal_count'), DB::raw('0 as deal_value'))
            ->first();
    }
}
