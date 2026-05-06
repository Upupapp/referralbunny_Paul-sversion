<?php
namespace App\Traits;

use App\Services\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Add to any model that belongs to a single tenant.
 * Automatically scopes queries to the active tenant.
 * Does NOT break Super Admin queries — SA bypasses scope.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        // Auto-set tenant_id on create when context is available
        static::creating(function (Model $model) {
            if (empty($model->tenant_id) && TenantContext::id()) {
                $model->tenant_id = TenantContext::id();
            }
        });
    }

    // ── Scopes ─────────────────────────────────────────────────

    /**
     * Scope to a specific tenant (explicit, for SA or system use).
     */
    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where($this->getTable() . '.tenant_id', $tenantId);
    }

    /**
     * Enforce tenant scope from current TenantContext.
     * Call this explicitly in controllers rather than a global scope,
     * to preserve Super Admin visibility.
     */
    public static function tenantScoped(): Builder
    {
        $tenantId = TenantContext::id();
        if (!$tenantId) {
            // Super Admin with no tenant selected — return nothing by default
            // SA should explicitly pass tenant_id
            if (TenantContext::isSuperAdmin()) {
                return (new static)->newQuery();
            }
            abort(403, 'No tenant context for query.');
        }
        return (new static)->newQuery()->where((new static)->getTable() . '.tenant_id', $tenantId);
    }

    /**
     * Check if this model belongs to the given tenant.
     */
    public function belongsToTenantId(string $tenantId): bool
    {
        return (string) $this->tenant_id === (string) $tenantId;
    }

    /**
     * Abort 404 if model doesn't belong to the active tenant context.
     */
    public function assertBelongsToCurrentTenant(): void
    {
        $tenantId = TenantContext::id();
        if ($tenantId && (string) $this->tenant_id !== (string) $tenantId) {
            abort(404); // 404 not 403 — do not reveal existence
        }
    }
}
