<?php

namespace App\Http\Controllers;

use App\Services\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrganizationController extends Controller
{
    // Island group → region prefix mapping
    private const ISLAND_REGIONS = [
        'Luzon'   => ['NCR', 'CAR', 'Region I', 'Region II', 'Region III', 'Region IV-A', 'Region IV-B', 'Region V'],
        'Visayas' => ['Region VI', 'Region VII', 'Region VIII'],
        'Mindanao'=> ['Region IX', 'Region X', 'Region XI', 'Region XII', 'Region XIII', 'BARMM'],
    ];

    public function index(Request $request): JsonResponse
    {
        $perPage  = min(50, max(10, (int) $request->get('per_page', 25)));
        $page     = max(1, (int) $request->get('page', 1));
        $tenantId = TenantContext::id() ?? TenantContext::resolveFromAuth($request->tenant_id);

        $base = DB::table('organizations as o')
            ->where('o.tenant_id', $tenantId);

        // ── Filters ──────────────────────────────────────────────
        if ($request->filled('search')) {
            $term = '%' . strtolower($request->search) . '%';
            $base->whereRaw("(lower(o.name) like ? or lower(o.city) like ?)", [$term, $term]);
        }
        if ($request->filled('island_group') && isset(self::ISLAND_REGIONS[$request->island_group])) {
            $prefixes = self::ISLAND_REGIONS[$request->island_group];
            $base->where(function ($q) use ($prefixes) {
                foreach ($prefixes as $prefix) {
                    $q->orWhereRaw("o.data->>'region' like ?", [$prefix . '%']);
                }
            });
        }
        if ($request->filled('region')) {
            $base->whereRaw("o.data->>'region' = ?", [$request->region]);
        }
        if ($request->filled('province')) {
            $base->where('o.address', $request->province);
        }
        if ($request->filled('lgu_type')) {
            $base->whereRaw("o.data->>'lgu_type' = ?", [$request->lgu_type]);
        }

        $total = (clone $base)->count();

        // Scoped sub-queries (tenant-only) keep joins fast.
        // $tenantId is server-resolved via TenantContext (never user-supplied), but we still
        // use PDO quoting to eliminate the string-interpolation pattern across the codebase.
        $quotedTenantId = DB::getPdo()->quote($tenantId);

        $orgs = (clone $base)
            ->leftJoin(
                DB::raw("(SELECT organization_id, COUNT(*) as contact_count
                          FROM contacts WHERE tenant_id = {$quotedTenantId}
                          GROUP BY organization_id) cc"),
                'o.id', '=', 'cc.organization_id'
            )
            ->leftJoin(
                DB::raw("(SELECT c2.organization_id,
                                 COUNT(DISTINCT dc.deal_id) as deal_count,
                                 COALESCE(SUM(l.deal_value), 0) as deal_value
                          FROM contacts c2
                          JOIN deal_contacts dc ON c2.id = dc.contact_id
                          JOIN leads l ON dc.deal_id = l.id AND l.tenant_id = {$quotedTenantId}
                          WHERE c2.tenant_id = {$quotedTenantId}
                          GROUP BY c2.organization_id) ds"),
                'o.id', '=', 'ds.organization_id'
            )
            ->select(
                'o.id', 'o.name', 'o.address', 'o.city', 'o.data',
                DB::raw('COALESCE(cc.contact_count, 0) as contact_count'),
                DB::raw('COALESCE(ds.deal_count, 0) as deal_count'),
                DB::raw('COALESCE(ds.deal_value, 0) as deal_value')
            )
            ->orderBy('o.address')
            ->orderBy('o.name')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        // Apply has_deal filter post-join (cheaper than a sub-sub-query)
        if ($request->filled('has_deal')) {
            $orgs = $request->has_deal === '1'
                ? $orgs->filter(fn($o) => $o->deal_count > 0)->values()
                : $orgs->filter(fn($o) => $o->deal_count == 0)->values();
        }

        return response()->json([
            'data'      => $orgs,
            'total'     => $total,
            'page'      => $page,
            'per_page'  => $perPage,
            'last_page' => (int) ceil($total / $perPage),
        ]);
    }

    public function available(Request $request): JsonResponse
    {
        $tenantId = TenantContext::id() ?? TenantContext::resolveFromAuth($request->tenant_id);
        $province = $request->province;

        // Org IDs that already have a non-expired, non-declined active deal
        // Wrapped in try/catch in case organization_id column hasn't been migrated yet
        try {
            $claimedOrgIds = DB::table('leads')
                ->where('tenant_id', $tenantId)
                ->whereNotNull('organization_id')
                ->whereNotIn('status', ['expired', 'declined'])
                ->pluck('organization_id');
        } catch (\Throwable $e) {
            $claimedOrgIds = collect(); // treat all as available if column missing
        }

        $query = DB::table('organizations as o')
            ->where('o.tenant_id', $tenantId)
            ->select('o.id', 'o.name', 'o.address', 'o.data');

        if ($province) {
            $query->where('o.address', $province);
        }

        $orgs = $query->orderByRaw("o.data->>'lgu_type' desc nulls last")
                      ->orderBy('o.name')
                      ->get()
                      ->map(function ($org) use ($claimedOrgIds) {
                          $d = [];
                          try { $d = is_string($org->data) ? json_decode($org->data, true) : (array)($org->data ?? []); } catch (\Throwable $e) {}
                          return [
                              'id'       => $org->id,
                              'name'     => $org->name,
                              'province' => $org->address,
                              'lgu_type' => $d['lgu_type'] ?? null,
                              'claimed'  => $claimedOrgIds->contains($org->id),
                          ];
                      });

        return response()->json($orgs->values());
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
