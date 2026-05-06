<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class TenantImportSettings extends Model
{
    protected $table = 'tenant_import_settings';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'tenant_id',
        'industry_template_key',
        'required_fields',
        'optional_fields',
        'default_stage',
        'default_status',
        'default_currency',
        'allow_referrer_import',
        'allow_referrer_new_deals',
        'allow_referrer_partner_add',
        'duplicate_handling',
        'unknown_org_behavior',
        'unknown_referrer_behavior',
        'unknown_partner_behavior',
        'lgu_ids_locked',
    ];

    protected $casts = [
        'required_fields'            => 'array',
        'optional_fields'            => 'array',
        'allow_referrer_import'      => 'boolean',
        'allow_referrer_new_deals'   => 'boolean',
        'allow_referrer_partner_add' => 'boolean',
        'lgu_ids_locked'             => 'boolean',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn ($m) => $m->id ??= (string) Str::uuid());
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /**
     * Get or create import settings for a tenant.
     * Provides safe defaults on first use without requiring a separate seeder.
     */
    public static function forTenant(string $tenantId): self
    {
        return static::firstOrCreate(
            ['tenant_id' => $tenantId],
            ['id' => (string) Str::uuid()]
        );
    }
}
