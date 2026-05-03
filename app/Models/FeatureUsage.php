<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeatureUsage extends Model
{
    protected $table = 'feature_usage';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'tenant_id', 'feature_name', 'usage_count', 'period_start', 'last_used_at',
    ];

    protected $casts = [
        'period_start' => 'date',
        'last_used_at' => 'datetime',
    ];
}
