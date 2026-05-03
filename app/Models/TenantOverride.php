<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantOverride extends Model
{
    protected $table = 'tenant_overrides';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'tenant_id', 'feature_name', 'override_value',
        'expires_at', 'approved_by', 'approval_reference_id', 'notes',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function isActive(): bool
    {
        return $this->expires_at === null || $this->expires_at->isFuture();
    }
}
