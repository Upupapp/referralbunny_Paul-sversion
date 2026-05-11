<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TenantLegalAgreement extends Model
{
    protected $keyType    = 'string';
    public    $incrementing = false;

    protected $fillable = [
        'id', 'tenant_id', 'type', 'title', 'content',
        'applicable_roles', 'is_required', 'is_active',
        'version', 'effective_date', 'display_order',
    ];

    protected $casts = [
        'applicable_roles' => 'array',
        'is_required'      => 'boolean',
        'is_active'        => 'boolean',
        'effective_date'   => 'date:Y-m-d',
    ];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function acceptances()
    {
        return $this->hasMany(TenantLegalAgreementAcceptance::class, 'tenant_legal_agreement_id');
    }

    public function appliesToRole(string $role): bool
    {
        if (empty($this->applicable_roles)) return true; // null = applies to all roles
        return in_array($role, $this->applicable_roles);
    }

    public function scopeForTenant($query, string $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }
}
