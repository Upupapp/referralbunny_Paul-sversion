<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Tenant extends Model
{
    protected $table = 'tenants';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'name', 'slug', 'program_name', 'business_name',
        'contact_person', 'contact_email', 'contact_phone',
        'logo_url', 'industry', 'description', 'status',
        'accent_color', 'admin_name', 'admin_email',
    ];

    public function config(): HasOne
    {
        return $this->hasOne(TenantConfig::class, 'tenant_id');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class, 'tenant_id');
    }

    public function resellers(): HasMany
    {
        return $this->hasMany(Reseller::class, 'tenant_id');
    }

    public function subIndustries(): HasMany
    {
        return $this->hasMany(TenantSubIndustry::class, 'tenant_id');
    }

    public function metric(): HasOne
    {
        return $this->hasOne(TenantMetric::class, 'tenant_id');
    }
}
