<?php

namespace App\Policies;

use App\Models\Program;
use App\Models\TenantMembership;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\PermissionService;

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
 */
class ProgramPolicy
{
    public function viewAny(User|TenantUser $actor): bool
    {
        if ($actor instanceof User) return true;
        return $this->hasPermission($actor, 'view_programs', $this->routeTenantId());
    }

    public function view(User|TenantUser $actor, Program $program): bool
    {
        if ($actor instanceof User) return true;
        return $this->hasPermission($actor, 'view_programs', $program->tenant_id);
    }

    public function create(User|TenantUser $actor): bool
    {
        if ($actor instanceof User) return true;
        return $this->hasPermission($actor, 'manage_programs', $this->routeTenantId());
    }

    public function update(User|TenantUser $actor, Program $program): bool
    {
        if ($actor instanceof User) return true;
        return $this->hasPermission($actor, 'manage_programs', $program->tenant_id);
    }

    public function delete(User|TenantUser $actor, Program $program): bool
    {
        if ($actor instanceof User) return true;
        if ($program->status !== 'draft') return false;
        return $this->hasPermission($actor, 'manage_programs', $program->tenant_id);
    }

    public function launch(User|TenantUser $actor, Program $program): bool
    {
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
        if ($actor instanceof User) return true;
        return $this->hasPermission($actor, 'manage_programs', $program->tenant_id);
    }

    public function managePeople(User|TenantUser $actor, Program $program): bool
    {
        if ($actor instanceof User) return true;
        return $this->hasPermission($actor, 'manage_programs', $program->tenant_id);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

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
