<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Services\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DealMentionSearchController extends Controller
{
    // ── GET /api/deals/{dealId}/mentions/search?q=
    // Returns tenant-scoped mentionable entities: admins, referrers, partners, contacts.
    // Cross-tenant leakage is blocked by TenantContext.

    public function search(Request $request, string $dealId): JsonResponse
    {
        $tenantId = TenantContext::requireId();

        // Verify deal belongs to tenant
        $deal = Lead::where('id', $dealId)->where('tenant_id', $tenantId)->firstOrFail();

        // Resolve actor role to restrict what they can see
        $role = $this->resolveRole();

        $q       = trim($request->get('q', ''));
        $limit   = 10;
        $results = [];

        // Tenant admins + managers (tenant_users table)
        if (in_array($role, ['tenant_admin', 'super_admin'])) {
            $adminQuery = DB::table('tenant_users as tu')
                ->join('tenant_memberships as tm', 'tu.id', '=', 'tm.tenant_user_id')
                ->where('tm.tenant_id', $tenantId)
                ->where('tm.status', 'active')
                ->select('tu.id', 'tu.first_name', 'tu.last_name', 'tu.email', 'tu.nickname', 'tm.role');

            if ($q !== '') {
                $adminQuery->where(function ($sub) use ($q) {
                    $sub->whereRaw("lower(concat(tu.first_name, ' ', tu.last_name)) like ?", ['%' . strtolower($q) . '%'])
                        ->orWhereRaw("lower(tu.email) like ?", ['%' . strtolower($q) . '%'])
                        ->orWhereRaw("lower(tu.nickname) like ?", ['%' . strtolower($q) . '%']);
                });
            }

            foreach ($adminQuery->limit($limit)->get() as $u) {
                $name = trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? '')) ?: $u->email;
                if ($u->nickname) $name = $u->nickname;
                $results[] = [
                    'id'    => $u->id,
                    'type'  => 'tenant_admin',
                    'name'  => $name,
                    'email' => $u->email,
                    'badge' => ucfirst(str_replace('_', ' ', $u->role ?? 'Admin')),
                ];
            }
        }

        // Referrers — resellers assigned to the deal or all active in tenant
        $resellerQuery = DB::table('resellers')
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->select('id', 'name', 'email');

        if ($q !== '') {
            $resellerQuery->where(function ($sub) use ($q) {
                $sub->whereRaw("lower(name) like ?", ['%' . strtolower($q) . '%'])
                    ->orWhereRaw("lower(email) like ?", ['%' . strtolower($q) . '%']);
            });
        }

        foreach ($resellerQuery->limit($limit)->get() as $r) {
            $results[] = [
                'id'    => $r->id,
                'type'  => 'referrer',
                'name'  => $r->name,
                'email' => $r->email,
                'badge' => 'Referrer',
            ];
        }

        // Partners — only partners associated with this specific deal
        $partnerQuery = DB::table('partner_users as pu')
            ->join('deal_partners as dp', 'pu.id', '=', 'dp.partner_user_id')
            ->where('dp.deal_id', $dealId)
            ->whereIn('dp.status', ['active', 'invited'])
            ->select('pu.id', 'pu.first_name', 'pu.last_name', 'pu.email');

        if ($q !== '') {
            $partnerQuery->where(function ($sub) use ($q) {
                $sub->whereRaw("lower(concat(pu.first_name, ' ', pu.last_name)) like ?", ['%' . strtolower($q) . '%'])
                    ->orWhereRaw("lower(pu.email) like ?", ['%' . strtolower($q) . '%']);
            });
        }

        foreach ($partnerQuery->limit(5)->get() as $p) {
            $name = trim(($p->first_name ?? '') . ' ' . ($p->last_name ?? '')) ?: $p->email;
            $results[] = [
                'id'    => $p->id,
                'type'  => 'partner',
                'name'  => $name,
                'email' => $p->email,
                'badge' => 'Partner',
            ];
        }

        // All tenant contacts — not limited to deal-linked ones so users can tag any contact
        if (in_array($role, ['tenant_admin', 'super_admin'])) {
            $contactQuery = DB::table('contacts as c')
                ->where('c.tenant_id', $tenantId)
                ->where(function ($sub) {
                    $sub->whereNull('c.status')
                        ->orWhere('c.status', '!=', 'archived');
                })
                ->select('c.id', 'c.first_name', 'c.last_name', 'c.email', 'c.job_title', 'c.nickname');

            if ($q !== '') {
                $contactQuery->where(function ($sub) use ($q) {
                    $term = '%' . strtolower($q) . '%';
                    $sub->whereRaw("lower(coalesce(c.first_name,'') || ' ' || coalesce(c.last_name,'')) like ?", [$term])
                        ->orWhereRaw("lower(coalesce(c.email,'')) like ?", [$term])
                        ->orWhereRaw("lower(coalesce(c.nickname,'')) like ?", [$term]);
                });
            } else {
                // When no query, show recently created contacts as suggestions
                $contactQuery->orderByDesc('c.created_at');
            }

            foreach ($contactQuery->limit(8)->get() as $c) {
                $name = $c->nickname
                    ?? trim(($c->first_name ?? '') . ' ' . ($c->last_name ?? ''))
                    ?: ($c->email ?? '—');
                $results[] = [
                    'id'    => $c->id,
                    'type'  => 'contact',
                    'name'  => $name,
                    'email' => $c->email,
                    'badge' => 'Contact',
                ];
            }
        }

        // Deduplicate by id+type, limit total
        $seen   = [];
        $unique = [];
        foreach ($results as $r) {
            $key = $r['type'] . ':' . $r['id'];
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $unique[]   = $r;
                if (count($unique) >= 15) break;
            }
        }

        return response()->json($unique);
    }

    private function resolveRole(): string
    {
        foreach (['tenant', 'web', 'reseller', 'partner'] as $guard) {
            if (Auth::guard($guard)->check()) {
                return match ($guard) {
                    'tenant'   => 'tenant_admin',
                    'web'      => 'super_admin',
                    'reseller' => 'referrer',
                    'partner'  => 'partner',
                    default    => 'unknown',
                };
            }
        }
        return 'unknown';
    }
}
