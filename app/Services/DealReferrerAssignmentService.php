<?php

namespace App\Services;

use App\Models\Reseller;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Provides the Referrer dropdown list for deal creation and assignment.
 *
 * Dropdown eligibility rules:
 * - Include: Resellers with status active|nda_signed (tenant-scoped)
 * - Include: Resellers with status invited/pending (shown with Pending badge)
 * - Include: Tenant Owners, Admins, Managers who have NO Reseller record
 *            (selecting them stores reseller_name only — role is never changed)
 * - Exclude: Deactivated Referrers
 * - Exclude: Users from other tenants
 * - Tenant isolation is strict — never cross tenants
 */
class DealReferrerAssignmentService
{
    /**
     * Return all dropdown-eligible users for a tenant's Referrer assignment field.
     *
     * Includes (in priority order):
     *   1. Active / NDA-signed Referrers
     *   2. Invited / pending Referrers (so admins can assign without re-inviting)
     *   3. Tenant Owners, Admins, and Managers who do NOT already have a Reseller record
     *      (selecting them stores their name as reseller_name without role conversion)
     *
     * Tenant isolation is strict — never crosses tenant boundaries.
     *
     * @return Collection<array{
     *   id: string,
     *   name: string,
     *   email: string,
     *   status: string,
     *   type: string,
     *   source: string,
     *   display_name: string,
     *   role_badges: list<string>,
     *   is_multi_role: bool,
     *   tenant_role: string|null
     * }>
     */
    public function getActivatedReferrersForTenant(string $tenantId, ?string $search = null): Collection
    {
        $q = $search ? '%' . strtolower($search) . '%' : null;

        // ── 1. Active / NDA-signed Referrers ─────────────────────────────
        $activeResellers = Reseller::where('tenant_id', $tenantId)
            ->whereIn('status', ['active', 'nda_signed'])
            ->whereNotNull('email')
            ->when($q, fn($query) => $query->where(function ($qb) use ($q) {
                $qb->whereRaw('LOWER(name) LIKE ?', [$q])
                   ->orWhereRaw('LOWER(email) LIKE ?', [$q]);
            }))
            ->orderBy('name')
            ->get();

        // ── 2. Invited / pending Referrers ───────────────────────────────
        $invitedResellers = Reseller::where('tenant_id', $tenantId)
            ->where('status', 'invited')
            ->whereNotNull('email')
            ->when($q, fn($query) => $query->where(function ($qb) use ($q) {
                $qb->whereRaw('LOWER(name) LIKE ?', [$q])
                   ->orWhereRaw('LOWER(email) LIKE ?', [$q]);
            }))
            ->orderBy('name')
            ->get();

        // ── 3. Tenant Owners / Admins / Managers (without Reseller record) ─
        // Wrapped in try-catch: schema differences across environments are handled gracefully.
        $tenantUsers = collect();
        try {
            $tenantUserQuery = DB::table('tenant_memberships as tm')
                ->join('tenant_users as u', 'tm.tenant_user_id', '=', 'u.id')
                ->where('tm.tenant_id', $tenantId)
                ->where('tm.status', 'active')
                ->whereIn('tm.role', ['owner', 'admin', 'manager'])
                ->whereNotNull('u.email')
                ->selectRaw("u.id as tenant_user_id, TRIM(CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,''))) as name, u.email, tm.role as tenant_role");

            if ($q) {
                $tenantUserQuery->where(function ($qb) use ($q) {
                    $qb->whereRaw("LOWER(TRIM(CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,'')))) LIKE ?", [$q])
                       ->orWhereRaw('LOWER(u.email) LIKE ?', [$q]);
                });
            }

            $tenantUsers = $tenantUserQuery->orderByRaw("TRIM(CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,'')))")->get();
        } catch (\Throwable) {
            // Schema unavailable in this environment — admin/manager enrichment skipped
        }

        // ── Pre-fetch membership roles for all linked Reseller records ────
        $linkedUserIds = $activeResellers->pluck('linked_tenant_user_id')
            ->merge($invitedResellers->pluck('linked_tenant_user_id'))
            ->filter()->unique()->values();

        $membershipRoles = collect();
        if ($linkedUserIds->isNotEmpty()) {
            try {
                $membershipRoles = DB::table('tenant_memberships')
                    ->where('tenant_id', $tenantId)
                    ->where('status', 'active')
                    ->whereIn('tenant_user_id', $linkedUserIds)
                    ->select('tenant_user_id', 'role')
                    ->get()
                    ->keyBy('tenant_user_id');
            } catch (\Throwable) {
                // Schema unavailable — role badge enrichment skipped
            }
        }

        // ── Build email coverage map to avoid duplicates ──────────────────
        $coveredEmails = $activeResellers->pluck('email')
            ->merge($invitedResellers->pluck('email'))
            ->map(fn($e) => strtolower(trim($e ?? '')))
            ->filter()
            ->flip()
            ->all();

        $results = collect();

        // ── Map active Referrers ──────────────────────────────────────────
        foreach ($activeResellers as $r) {
            $roleBadges  = ['Referrer'];
            $isMultiRole = false;
            $tenantRole  = null;

            if ($r->linked_tenant_user_id && isset($membershipRoles[$r->linked_tenant_user_id])) {
                $tenantRole  = $membershipRoles[$r->linked_tenant_user_id]->role;
                $roleLabel   = match ($tenantRole) {
                    'owner'   => 'Tenant Owner',
                    'admin'   => 'Tenant Admin',
                    'manager' => 'Tenant Manager',
                    default   => ucfirst($tenantRole),
                };
                $roleBadges[] = $roleLabel;
                $isMultiRole  = true;
            }

            $results->push([
                'id'           => $r->id,
                'name'         => $r->name,
                'email'        => $r->email,
                'status'       => $r->status,
                'type'         => 'referrer',
                'source'       => 'existing_referrer',
                'display_name' => "{$r->name} — {$r->email} · " . implode(' · ', $roleBadges),
                'role_badges'  => $roleBadges,
                'is_multi_role'=> $isMultiRole,
                'tenant_role'  => $tenantRole,
            ]);
        }

        // ── Map invited / pending Referrers ───────────────────────────────
        foreach ($invitedResellers as $r) {
            $roleBadges = ['Pending Referrer'];
            $tenantRole = null;

            if ($r->linked_tenant_user_id && isset($membershipRoles[$r->linked_tenant_user_id])) {
                $tenantRole = $membershipRoles[$r->linked_tenant_user_id]->role;
                $roleLabel  = match ($tenantRole) {
                    'owner'   => 'Tenant Owner',
                    'admin'   => 'Tenant Admin',
                    'manager' => 'Tenant Manager',
                    default   => ucfirst($tenantRole),
                };
                $roleBadges[] = $roleLabel;
            }

            $results->push([
                'id'           => $r->id,
                'name'         => $r->name,
                'email'        => $r->email,
                'status'       => 'invited',
                'type'         => 'pending_referrer',
                'source'       => 'pending_referrer',
                'display_name' => "{$r->name} — {$r->email} · Pending Referrer",
                'role_badges'  => $roleBadges,
                'is_multi_role'=> count($roleBadges) > 1,
                'tenant_role'  => $tenantRole,
            ]);
        }

        // ── Map Tenant Admins/Managers not already covered by a Reseller record ──
        // Selecting these stores reseller_name only — their system role is never changed.
        foreach ($tenantUsers as $u) {
            if (isset($coveredEmails[strtolower(trim($u->email ?? ''))])) {
                continue; // Already represented via their Reseller record above
            }

            $roleLabel = match ($u->tenant_role) {
                'owner'   => 'Tenant Owner',
                'admin'   => 'Tenant Admin',
                'manager' => 'Tenant Manager',
                default   => ucfirst($u->tenant_role),
            };

            $results->push([
                'id'           => 'tu_' . $u->tenant_user_id,
                'name'         => $u->name,
                'email'        => $u->email,
                'status'       => 'active',
                'type'         => $u->tenant_role,
                'source'       => 'tenant_user',
                'display_name' => "{$u->name} — {$u->email} · {$roleLabel}",
                'role_badges'  => [$roleLabel],
                'is_multi_role'=> false,
                'tenant_role'  => $u->tenant_role,
            ]);
        }

        return $results;
    }

    /**
     * Determine what happens when a given email is used as a Referrer in deal creation.
     *
     * Returns a status classification and action recommendation:
     *
     * active_referrer       — email belongs to an active Reseller, assign directly
     * pending_referrer      — email belongs to pending invited Reseller, update summary
     * active_admin_manager  — email belongs to active Admin/Manager WITHOUT Referrer role
     *                          → prompt: "Add Referrer role and assign?"
     * new_referrer          — email not in system, create new invitation
     * invalid_email         — malformed email
     *
     * @return array{
     *   status: string,
     *   reseller_id: string|null,
     *   tenant_user_id: string|null,
     *   existing_roles: list<string>,
     *   action_required: string
     * }
     */
    public function classifyReferrerEmail(string $tenantId, string $email): array
    {
        $normalized = strtolower(trim($email));

        if (!filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
            return [
                'status'          => 'invalid_email',
                'reseller_id'     => null,
                'tenant_user_id'  => null,
                'existing_roles'  => [],
                'action_required' => 'reject_invalid_email',
            ];
        }

        // Check active/nda_signed Referrer
        $activeReseller = Reseller::where('tenant_id', $tenantId)
            ->whereRaw('LOWER(email) = ?', [$normalized])
            ->whereIn('status', ['active', 'nda_signed'])
            ->first();

        if ($activeReseller) {
            return [
                'status'          => 'active_referrer',
                'reseller_id'     => $activeReseller->id,
                'tenant_user_id'  => $activeReseller->linked_tenant_user_id,
                'existing_roles'  => ['referrer'],
                'action_required' => 'assign_deal_no_email',
            ];
        }

        // Check pending invited Referrer
        $pendingReseller = Reseller::where('tenant_id', $tenantId)
            ->whereRaw('LOWER(email) = ?', [$normalized])
            ->where('status', 'invited')
            ->first();

        if ($pendingReseller) {
            return [
                'status'          => 'pending_referrer',
                'reseller_id'     => $pendingReseller->id,
                'tenant_user_id'  => $pendingReseller->linked_tenant_user_id,
                'existing_roles'  => ['referrer_pending'],
                'action_required' => 'update_summary_throttled_email',
            ];
        }

        // Check active Admin/Manager without Referrer role
        $membership = DB::table('tenant_memberships as tm')
            ->join('tenant_users as u', 'tm.tenant_user_id', '=', 'u.id')
            ->where('tm.tenant_id', $tenantId)
            ->where('tm.status', 'active')
            ->whereRaw('LOWER(u.email) = ?', [$normalized])
            ->select('tm.role', 'u.id as tenant_user_id')
            ->first();

        if ($membership) {
            return [
                'status'          => 'active_admin_manager',
                'reseller_id'     => null,
                'tenant_user_id'  => $membership->tenant_user_id,
                'existing_roles'  => [$membership->role],
                'action_required' => 'prompt_add_referrer_role',
            ];
        }

        return [
            'status'          => 'new_referrer',
            'reseller_id'     => null,
            'tenant_user_id'  => null,
            'existing_roles'  => [],
            'action_required' => 'create_invitation_send_email',
        ];
    }
}
