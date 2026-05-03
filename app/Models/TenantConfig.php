<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantConfig extends Model
{
    protected $table = 'tenant_configs';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id', 'lead_type', 'lead_label',
        'fields', 'stages', 'commission',
        'primary_identifier_fields',
    ];

    protected $casts = [
        'fields'                    => 'array',
        'stages'                    => 'array',
        'commission'                => 'array',
        'primary_identifier_fields' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }
}
