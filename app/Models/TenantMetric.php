<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantMetric extends Model
{
    protected $table = 'tenant_metrics';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id', 'health_score', 'health_level',
        'last_activity_at', 'active_users_count', 'inactive_users_count',
        'leads_count', 'stale_leads_count', 'setup_completion_percentage',
        'payment_status', 'has_payment_method', 'trial_days_remaining',
        'subscription_status', 'last_payment_date',
        'dau', 'wau', 'mau', 'mrr', 'arr',
    ];

    protected $casts = [
        'last_activity_at'  => 'datetime',
        'last_payment_date' => 'date',
        'has_payment_method'=> 'boolean',
        'health_score'      => 'integer',
        'leads_count'       => 'integer',
        'stale_leads_count' => 'integer',
        'active_users_count'=> 'integer',
        'inactive_users_count' => 'integer',
        'trial_days_remaining' => 'integer',
        'dau' => 'integer',
        'wau' => 'integer',
        'mau' => 'integer',
        'mrr' => 'decimal:2',
        'arr' => 'decimal:2',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }
}
