<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantCustomPricing extends Model
{
    public $incrementing = false;
    protected $keyType   = 'string';
    const UPDATED_AT     = null;

    protected $fillable = [
        'tenant_id', 'plan_id', 'custom_price_monthly', 'custom_price_yearly',
        'currency', 'effective_from', 'effective_until', 'approved_by', 'notes',
    ];

    protected $casts = [
        'effective_from'       => 'date',
        'effective_until'      => 'date',
        'custom_price_monthly' => 'decimal:2',
        'custom_price_yearly'  => 'decimal:2',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class, 'tenant_id'); }
    public function plan(): BelongsTo   { return $this->belongsTo(Plan::class, 'plan_id'); }
}
