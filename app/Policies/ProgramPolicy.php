<?php

namespace App\Policies;

use App\Models\Program;
use App\Models\TenantMembership;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\PermissionService;
use App\Support\ProtectedTenants;

/**
 * Server-side authorization for Program resources.
 *
 * Callers: ProgramController, ProgramWorkspaceController, and any job/API that
 * touches a Program. Browser-side visibility is NOT authorization.
 *
 * Guards that may reach these methods:
 *   - auth('web')    → User (platform staff / super-admin)
 *   - auth('tenant') → TenantUser (tenant admin/manager)
 *
 * Referrers and Partners never interact with Programs through this policy;
 * they use scoped read-model queries in their own portal controllers.
 *
 * Note: TenantUser has no tenant_id column. Tenant context comes from:
 *   - $program->tenant_id  (for resource-scoped methods)
 *   - request()->route('tenantId')  (for collection methods like viewAny/create)
 * The tenant.access middleware guarantees the route tenantId belongs to the actor.
 *
 * Guardrail: ProtectedTenants (e.g. lgu-ids) are blocked from Programs V4
 * entirely, including for User (super-admin) actors who otherwise bypass
 * every other check here — Programs V4 must never touch a protected
 * tenant's data regardless of who's driving it. PROGRAMS_V4_ENABLED is a
 * global, not per-tenant, flag, so this is the single choke point that
 * keeps a protected tenant out even if the flag is ever turned on.
 */
class ProgramPolicy
{
    public function viewAny(User|TenantUser $actor): bool
    {
        if ($this->blockedForProtectedTenant($this->routeTenantId())) return false;
        if ($actor instanceof User) return true;
        return $this->hasPermission($actor, 'view_programs', $this->routeTenantId());
    }

    public function view(User|TenantUser $actor, Program $program): bool
    {
        if ($this->blockedForProtectedTenant($program->tenant_id)) return false;
        if ($actor instanceof User) return true;
        return $this->hasPermission($actor, 'view_programs', $program->tenant_id);
    }

    public function create(User|TenantUser $actor): bool
    {
        if ($this->blockedForProtectedTenant($this->routeTenantId())) return false;
        if ($actor instanceof User) return true;
        return $this->hasPermission($actor, 'manage_programs', $this->routeTenantId());
    }

    public function update(User|TenantUser $actor, Program $program): bool
    {
        if ($this->blockedForProtectedTenant($program->tenant_id)) return false;
        if ($actor instanceof User) return true;
        return $this->hasPermission($actor, 'manage_programs', $program->tenant_id);
    }

    public function delete(User|TenantUser $actor, Program $program): bool
    {
        if ($this->blockedForProtectedTenant($program->tenant_id)) return false;
        if ($actor instanceof User) return true;
        if ($program->status !== 'draft') return false;
        return $this->hasPermission($actor, 'manage_programs', $program->tenant_id);
    }

    public function launch(User|TenantUser $actor, Program $program): bool
    {
        if ($this->blockedForProtectedTenant($program->tenant_id)) return false;
        if ($actor instanceof User) return true;
        return $this->hasPermission($actor, 'manage_programs', $program->tenant_id);
    }

    public function pause(User|TenantUser $actor, Program $program): bool
    {
        return $this->launch($actor, $program);
    }

    public function end(User|TenantUser $actor, Program $program): bool
    {
        return $this->launch($actor, $program);
    }

    public function archive(User|TenantUser $actor, Program $program): bool
    {
        if ($this->blockedForProtectedTenant($program->tenant_id)) return false;
        if ($actor instanceof User) return true;
        return $this->hasPermission($actor, 'manage_programs', $program->tenant_id);
    }

    public function managePeople(User|TenantUser $actor, Program $program): bool
    {
        if ($this->blockedForProtectedTenant($program->tenant_id)) return false;
        if ($actor instanceof User) return true;
        return $this->hasPermission($actor, 'manage_programs', $program->tenant_id);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function blockedForProtectedTenant(?string $tenantId): bool
    {
        return $tenantId !== null && ProtectedTenants::isProtected($tenantId);
    }

    /** Per-request membership cache to avoid N+1 on pages with multiple @can() calls. */
    private static array $membershipCache = [];

    private function hasPermission(TenantUser $actor, string $permission, ?string $tenantId): bool
    {
        if (!$tenantId) return false;
        try {
            $cacheKey = $actor->id . ':' . $tenantId;
            if (!array_key_exists($cacheKey, self::$membershipCache)) {
                self::$membershipCache[$cacheKey] = TenantMembership::where('tenant_user_id', $actor->id)
                    ->where('tenant_id', $tenantId)
                    ->where('status', 'active')
                    ->first();
            }
            $membership = self::$membershipCache[$cacheKey];
            if (!$membership) return false;
            return (new PermissionService)->can($membership, $permission);
        } catch (\Throwable) {
            return false;
        }
    }

    private function routeTenantId(): ?string
    {
        return request()->route('tenantId');
    }
}
