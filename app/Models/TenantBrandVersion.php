<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantBrandVersion extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'logo_url',
        'logo_path',
        'accent_color',
        'sidebar_color',
        'health_score',
        'created_at',
    ];

    protected $casts = [
        'created_at'   => 'datetime',
        'health_score' => 'integer',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
