<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class TenantCustomFieldValue extends Model
{
    protected $table = 'tenant_custom_field_values';
    public    $incrementing = false;
    protected $keyType      = 'string';

    protected $fillable = [
        'id',
        'tenant_id',
        'custom_field_id',
        'entity_type',
        'entity_id',
        'value_text',
        'value_number',
        'value_date',
        'value_boolean',
        'value_json',
    ];

    protected $casts = [
        'value_number'  => 'decimal:4',
        'value_boolean' => 'boolean',
        'value_json'    => 'array',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn ($m) => $m->id ??= (string) Str::uuid());
    }

    // ── Relationships ─────────────────────────────────────────────

    public function customField(): BelongsTo
    {
        return $this->belongsTo(TenantCustomField::class, 'custom_field_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }
}
