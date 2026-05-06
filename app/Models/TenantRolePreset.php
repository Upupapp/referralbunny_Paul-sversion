<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TenantRolePreset extends Model
{
    protected $table      = 'tenant_role_presets';
    public    $incrementing = false;
    protected $keyType    = 'string';

    protected $fillable = [
        'id', 'tenant_id', 'role_key', 'role_label', 'description',
        'default_permissions_json', 'locked_permissions_json', 'is_system',
    ];

    protected $casts = [
        'id'                       => 'string',
        'default_permissions_json' => 'array',
        'locked_permissions_json'  => 'array',
        'is_system'                => 'boolean',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /**
     * Scope to platform-wide presets (no tenant).
     */
    public function scopeSystem($query)
    {
        return $query->whereNull('tenant_id')->where('is_system', true);
    }

    /**
     * Scope to a specific tenant's presets (including platform defaults).
     */
    public function scopeForTenant($query, string $tenantId)
    {
        return $query->where(function ($q) use ($tenantId) {
            $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id');
        });
    }
}
