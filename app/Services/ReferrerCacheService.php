<?php

namespace App\Services;

use App\Models\Reseller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * ReferrerCacheService
 *
 * Busts referrer-side caches ("active deals" stats, activity lists, and
 * critical-action badges) for a lead's primary referrer and any
 * commission_splits co-referrers after a status-affecting change
 * (e.g. an extension request approved or an expired deal reactivated).
 */
class ReferrerCacheService
{
    /**
     * @return Reseller|null The resolved primary referrer (id + name), or null if
     *                        $primaryResellerId was provided (already resolved by
     *                        the caller) or no matching reseller was found by name.
     */
    public function bustForLead(string $tenantId, string $leadId, string $primaryResellerName, ?string $primaryResellerId = null): ?Reseller
    {
        $primary = null;

        if ($primaryResellerId) {
            Cache::forget("referrer_perf:{$tenantId}:{$primaryResellerId}");
            Cache::forget("reseller_leadids:{$tenantId}:{$primaryResellerId}");
        } else {
            $primary = Reseller::where('tenant_id', $tenantId)
                ->whereRaw('LOWER(name) = ?', [strtolower($primaryResellerName)])
                ->first(['id', 'name']);

            if ($primary) {
                Cache::forget("referrer_perf:{$tenantId}:{$primary->id}");
                Cache::forget("reseller_leadids:{$tenantId}:{$primary->id}");
            }
        }

        Cache::forget("ca_reseller:{$tenantId}:" . md5($primaryResellerName . ':' . ($primaryResellerId ?? $primary?->id ?? '')));

        $coReferrerNames = DB::table('commission_splits')
            ->where('lead_id', $leadId)
            ->pluck('reseller_name')
            ->map(fn($n) => trim((string) $n))
            ->filter()
            ->unique()
            ->reject(fn($n) => strtolower($n) === strtolower($primaryResellerName));

        foreach ($coReferrerNames as $rName) {
            $r = Reseller::where('tenant_id', $tenantId)
                ->whereRaw('LOWER(name) = ?', [strtolower($rName)])
                ->first(['id']);

            if ($r) {
                Cache::forget("referrer_perf:{$tenantId}:{$r->id}");
                Cache::forget("reseller_leadids:{$tenantId}:{$r->id}");
            }
            Cache::forget("ca_reseller:{$tenantId}:" . md5($rName . ':' . ($r?->id ?? '')));
        }

        return $primary;
    }

    /**
     * Batched version of bustForLead() for multiple leads in the same tenant.
     * Pre-fetches commission_splits and reseller records for the whole batch in
     * two queries instead of per-lead, then busts referrer-side caches for each
     * lead's primary referrer and co-referrers.
     *
     * @param iterable<\App\Models\Lead> $leads
     * @return array<string, ?Reseller> Map of lead id => resolved primary referrer (or null)
     */
    public function bustForLeads(string $tenantId, iterable $leads): array
    {
        $leads = collect($leads);
        if ($leads->isEmpty()) {
            return [];
        }

        $coReferrersByLead = DB::table('commission_splits')
            ->whereIn('lead_id', $leads->pluck('id'))
            ->get(['lead_id', 'reseller_name'])
            ->groupBy('lead_id');

        $allNames = $leads->pluck('reseller_name')
            ->merge($coReferrersByLead->flatten(1)->pluck('reseller_name'))
            ->map(fn($n) => trim((string) $n))
            ->filter()
            ->unique()
            ->values();

        $resellersByName = collect();
        if ($allNames->isNotEmpty()) {
            $placeholders    = implode(',', array_fill(0, $allNames->count(), '?'));
            $resellersByName = Reseller::where('tenant_id', $tenantId)
                ->whereRaw("LOWER(name) IN ({$placeholders})", $allNames->map(fn($n) => strtolower($n))->all())
                ->get(['id', 'name'])
                ->keyBy(fn($r) => strtolower($r->name));
        }

        $primaries = [];

        foreach ($leads as $lead) {
            $names = collect([$lead->reseller_name])
                ->merge(($coReferrersByLead->get($lead->id) ?? collect())->pluck('reseller_name'))
                ->map(fn($n) => trim((string) $n))
                ->filter()
                ->unique();

            $primary = null;

            foreach ($names as $rName) {
                $r = $resellersByName->get(strtolower($rName));

                if ($r) {
                    Cache::forget("referrer_perf:{$tenantId}:{$r->id}");
                    Cache::forget("reseller_leadids:{$tenantId}:{$r->id}");
                    if (strtolower($rName) === strtolower((string) $lead->reseller_name)) {
                        $primary = $r;
                    }
                }
                Cache::forget("ca_reseller:{$tenantId}:" . md5($rName . ':' . ($r?->id ?? '')));
            }

            $primaries[$lead->id] = $primary;
        }

        return $primaries;
    }
}
