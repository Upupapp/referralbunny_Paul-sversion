<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class TenantImportTemplate extends Model
{
    protected $table = 'tenant_import_templates';
    public    $incrementing = false;
    protected $keyType      = 'string';

    protected $fillable = [
        'id',
        'tenant_id',
        'destination_type',
        'template_name',
        'template_key',
        'industry_key',
        'is_default',
        'is_locked',
        'fields_json',
        'required_fields_json',
        'aliases_json',
        'sample_headers_json',
        'created_from_import_batch_id',
        'created_by_user_id',
        'approved_by_user_id',
        'double_authenticated_at',
        'version_number',
        'status',
    ];

    protected $casts = [
        'fields_json'            => 'array',
        'required_fields_json'   => 'array',
        'aliases_json'           => 'array',
        'sample_headers_json'    => 'array',
        'is_default'             => 'boolean',
        'is_locked'              => 'boolean',
        'double_authenticated_at'=> 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn ($m) => $m->id ??= (string) Str::uuid());
    }

    // ── Relationships ─────────────────────────────────────────────

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'created_by_user_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'approved_by_user_id');
    }

    // ── Scopes ────────────────────────────────────────────────────

    /**
     * Scope to active default templates.
     */
    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true)->where('status', 'active');
    }

    /**
     * Scope to a specific tenant's templates.
     */
    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    // ── Static helpers ────────────────────────────────────────────

    /**
     * Archive all current default templates for a tenant + destination type
     * in preparation for setting a new default.
     */
    public static function archivePreviousDefault(string $tenantId, string $destType): void
    {
        static::where('tenant_id', $tenantId)
            ->where('destination_type', $destType)
            ->where('is_default', true)
            ->update([
                'status'     => 'archived',
                'is_default' => false,
            ]);
    }
}
