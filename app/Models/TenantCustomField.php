<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class TenantCustomField extends Model
{
    protected $table = 'tenant_custom_fields';
    public    $incrementing = false;
    protected $keyType      = 'string';

    protected $fillable = [
        'id',
        'tenant_id',
        'destination_type',
        'field_key',
        'field_label',
        'data_type',
        'is_required',
        'is_importable',
        'is_exportable',
        'is_visible',
        'created_by_user_id',
    ];

    protected $casts = [
        'is_required'   => 'boolean',
        'is_importable' => 'boolean',
        'is_exportable' => 'boolean',
        'is_visible'    => 'boolean',
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

    public function values(): HasMany
    {
        return $this->hasMany(TenantCustomFieldValue::class, 'custom_field_id');
    }

    // ── Scopes ────────────────────────────────────────────────────

    /**
     * Return all custom fields belonging to a specific tenant.
     */
    public static function forTenant(string $tenantId)
    {
        return static::where('tenant_id', $tenantId);
    }
}
