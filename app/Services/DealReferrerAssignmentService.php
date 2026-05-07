<?php

namespace App\Services;

use App\Models\Reseller;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Provides the activated Referrer list for deal creation dropdowns.
 *
 * Rules:
 * - Include: Resellers with status active|nda_signed (tenant-scoped)
 * - Include: Admin/Manager users who ALSO have a Reseller record (linked_tenant_user_id set)
 * - Exclude: Admins/Managers who do NOT have a Reseller record (no Referrer role)
 * - Exclude: Deactivated, pending/invited Referrers
 * - Exclude: Referrers from other tenants
 * - Tenant isolation is strict — never cross tenants
 */
class DealReferrerAssignmentService
{
    /**
     * Return all activated Referrers for a tenant, ready for dropdown display.
     *
     * @return Collection<array{
     *   id: string,
     *   name: string,
     *   email: string,
     *   status: string,
     *   display_name: string,
     *   role_badges: list<string>,
     *   is_multi_role: bool
     * }>
     */
    public function getActivatedReferrersForTenant(string $tenantId, ?string $search = null): Collection
    {
        $query = Reseller::where('tenant_id', $tenantId)
            ->whereIn('status', ['active', 'nda_signed'])
            ->whereNotNull('email')
            ->orderBy('name');

        if ($search) {
            $q = '%' . strtolower($search) . '%';
            $query->where(function ($qb) use ($q) {
                $qb->whereRaw('LOWER(name) LIKE ?', [$q])
                   ->orWhereRaw('LOWER(email) LIKE ?', [$q]);
            });
        }

        $resellers = $query->get();

        // Pre-fetch tenant membership roles for linked admin/manager referrers
        $linkedUserIds = $resellers->pluck('linked_tenant_user_id')->filter()->unique()->values();
        $membershipRoles = collect();

        if ($linkedUserIds->isNotEmpty()) {
            $membershipRoles = DB::table('tenant_memberships')
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->whereIn('tenant_user_id', $linkedUserIds)
                ->select('tenant_user_id', 'role')
                ->get()
                ->keyBy('tenant_user_id');
        }

        return $resellers->map(function (Reseller $r) use ($membershipRoles): array {
            $roleBadges   = ['Referrer'];
            $isMultiRole  = false;
            $tenantRole   = null;

            if ($r->linked_tenant_user_id && isset($membershipRoles[$r->linked_tenant_user_id])) {
                $tenantRole  = $membershipRoles[$r->linked_tenant_user_id]->role;
                $roleLabel   = match ($tenantRole) {
                    'owner'   => 'Tenant Owner',
                    'admin'   => 'Tenant Admin',
                    'manager' => 'Tenant Manager',
                    default   => ucfirst($tenantRole),
                };
                $roleBadges[]= $roleLabel;
                $isMultiRole = true;
            }

            $badgeStr    = implode(' · ', $roleBadges);
            $displayName = "{$r->name} — {$r->email} · {$badgeStr}";

            return [
                'id'           => $r->id,
                'name'         => $r->name,
                'email'        => $r->email,
                'status'       => $r->status,
                'display_name' => $displayName,
                'role_badges'  => $roleBadges,
                'is_multi_role'=> $isMultiRole,
                'tenant_role'  => $tenantRole,
            ];
        });
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
