<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsageMetric extends Model
{
    protected $table = 'usage_metrics';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'tenant_id', 'billing_period_start', 'billing_period_end',
        'users_count', 'resellers_count', 'leads_this_month',
        'messages_this_month', 'storage_mb_used', 'grace_period_ends_at',
    ];

    protected $casts = [
        'billing_period_start' => 'date',
        'billing_period_end'   => 'date',
        'grace_period_ends_at' => 'datetime',
        'storage_mb_used'      => 'decimal:2',
    ];
}
