<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Notification;
use App\Models\Reseller;
use App\Models\TenantMembership;
use App\Models\TenantUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Manages tenant-scoped role assignments.
 * Enforces multi-role support: one user may hold Admin + Referrer simultaneously.
 */
class TenantRoleService
{
    /**
     * Add Referrer role to an existing Tenant Admin or Manager.
     *
     * Creates a Reseller record linked to their TenantUser account.
     * Sets status = 'active' immediately (no setup invite needed — they already have access).
     * Sends a role-added in-app notification.
     * Does NOT send an account setup email.
     * Does NOT remove their existing Admin/Manager role.
     *
     * @return array{reseller: Reseller, created: bool, already_referrer: bool}
     */
    public function addReferrerRoleToTenantUser(
        string $tenantId,
        string $tenantUserId,
        string $actorId,
        string $actorRole,
        ?string $dealId = null
    ): array {
        $user = TenantUser::findOrFail($tenantUserId);

        // Verify the user actually belongs to this tenant with an active membership
        $membership = TenantMembership::where('tenant_id', $tenantId)
            ->where('tenant_user_id', $tenantUserId)
            ->where('status', 'active')
            ->first();

        if (!$membership) {
            throw new \InvalidArgumentException("User {$tenantUserId} has no active membership in tenant {$tenantId}.");
        }

        $normalizedEmail = strtolower($user->email);

        // Check if already has a Reseller record for this tenant (by linked_id or email)
        $existing = Reseller::where('tenant_id', $tenantId)
            ->where(function ($q) use ($tenantUserId, $normalizedEmail) {
                $q->where('linked_tenant_user_id', $tenantUserId)
                  ->orWhereRaw('LOWER(email) = ?', [$normalizedEmail]);
            })
            ->first();

        if ($existing) {
            // Already a Referrer — just link tenant user if not linked, update deal
            if (!$existing->linked_tenant_user_id) {
                $existing->update(['linked_tenant_user_id' => $tenantUserId, 'status' => 'active']);
            }
            if ($dealId) {
                app(ReferrerInvitationDeduplicationService::class)->addDealToSummary($existing, $dealId);
            }
            $this->auditRoleChange($tenantId, $tenantUserId, $actorId, $actorRole, 'referrer_already_exists', $dealId, $membership->role);
            return ['reseller' => $existing->fresh(), 'created' => false, 'already_referrer' => true];
        }

        $fullName = trim("{$user->first_name} {$user->last_name}") ?: $user->email;

        $reseller = Reseller::create([
            'tenant_id'             => $tenantId,
            'name'                  => $fullName,
            'email'                 => $normalizedEmail,
            'status'                => 'active',
            'linked_tenant_user_id' => $tenantUserId,
            'invite_deal_ids'       => $dealId ? [$dealId] : [],
            'invite_deal_count'     => $dealId ? 1 : 0,
            'is_anonymous'          => false,
            'assigned_leads'        => 0,
            'closed_value'          => 0,
            'performance_score'     => 0,
            'joined_date'           => now()->toDateString(),
        ]);

        $this->auditRoleChange($tenantId, $tenantUserId, $actorId, $actorRole, 'referrer_role_added_to_existing_' . $membership->role, $dealId, $membership->role);
        $this->notifyUserRoleAdded($tenantId, $tenantUserId, $membership->role, $dealId);

        return ['reseller' => $reseller, 'created' => true, 'already_referrer' => false];
    }

    /**
     * Return all roles this email holds in the given tenant.
     *
     * @return list<string>  e.g. ['admin', 'referrer'] or ['referrer']
     */
    public function getUserRolesInTenant(string $tenantId, string $email): array
    {
        $roles = [];
        $normalized = strtolower(trim($email));

        $membership = DB::table('tenant_memberships as tm')
            ->join('tenant_users as u', 'tm.tenant_user_id', '=', 'u.id')
            ->where('tm.tenant_id', $tenantId)
            ->where('tm.status', 'active')
            ->whereRaw('LOWER(u.email) = ?', [$normalized])
            ->select('tm.role', 'u.id as tenant_user_id')
            ->first();

        if ($membership) {
            $roles[] = $membership->role; // owner | admin | manager
        }

        $resellerStatus = Reseller::where('tenant_id', $tenantId)
            ->whereRaw('LOWER(email) = ?', [$normalized])
            ->whereIn('status', ['active', 'nda_signed', 'invited'])
            ->value('status');

        if ($resellerStatus) {
            $roles[] = 'referrer';
        }

        return array_values(array_unique($roles));
    }

    /**
     * Check whether an email has an active Referrer role in the given tenant.
     */
    public function userHasReferrerRole(string $tenantId, string $email): bool
    {
        return Reseller::where('tenant_id', $tenantId)
            ->whereRaw('LOWER(email) = ?', [strtolower(trim($email))])
            ->whereIn('status', ['active', 'nda_signed'])
            ->exists();
    }

    /**
     * Resolve the tenant_user_id for a given email+tenant combination.
     * Returns null when no matching active tenant user exists.
     */
    public function resolveTenantUserId(string $tenantId, string $email): ?string
    {
        return DB::table('tenant_memberships as tm')
            ->join('tenant_users as u', 'tm.tenant_user_id', '=', 'u.id')
            ->where('tm.tenant_id', $tenantId)
            ->where('tm.status', 'active')
            ->whereRaw('LOWER(u.email) = ?', [strtolower(trim($email))])
            ->value('u.id');
    }

    // ── Private helpers ───────────────────────────────────────────────────

    private function auditRoleChange(
        string $tenantId,
        string $targetUserId,
        string $actorId,
        string $actorRole,
        string $event,
        ?string $dealId,
        string $existingRole
    ): void {
        try {
            ActivityLog::create([
                'id'        => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'user_id'   => $actorId,
                'action'    => $event,
                'entity'    => 'tenant_user',
                'entity_id' => $targetUserId,
                'metadata'  => [
                    'actor_role'     => $actorRole,
                    'existing_roles' => [$existingRole],
                    'role_added'     => 'referrer',
                    'deal_id'        => $dealId,
                    'timestamp'      => now()->toIso8601String(),
                ],
            ]);
        } catch (\Throwable) {}
    }

    private function notifyUserRoleAdded(
        string $tenantId,
        string $tenantUserId,
        string $existingRole,
        ?string $dealId
    ): void {
        try {
            $roleLabel = match ($existingRole) {
                'owner'   => 'Tenant Owner',
                'admin'   => 'Tenant Admin',
                'manager' => 'Tenant Manager',
                default   => ucfirst($existingRole),
            };

            $dealPart = $dealId ? ' A deal has been associated with your Referrer account.' : '';

            Notification::create([
                'id'                => (string) Str::uuid(),
                'tenant_id'         => $tenantId,
                'notifiable_type'   => 'tenant_admin',
                'notifiable_id'     => $tenantUserId,
                'category'          => 'reseller_referrer',
                'type'              => 'info',
                'priority'          => 'normal',
                'title'             => 'Referrer role added to your account',
                'message'           => "Your account now includes the Referrer role in addition to your {$roleLabel} access.{$dealPart}",
                'deduplication_key' => "reseller_referrer:{$tenantId}:{$tenantUserId}:role_added:" . now()->format('Ymd'),
                'is_read'           => false,
                'is_dismissed'      => false,
                'sent_at'           => now(),
            ]);
        } catch (\Throwable) {}
    }
}
