<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'name', 'description', 'price_monthly', 'price_yearly',
        'currency', 'billing_cycle_options', 'plan_limits_json',
        'plan_features_json', 'is_active',
    ];

    protected $casts = [
        'billing_cycle_options' => 'array',
        'plan_limits_json'      => 'array',
        'plan_features_json'    => 'array',
        'is_active'             => 'boolean',
        'price_monthly'         => 'decimal:2',
        'price_yearly'          => 'decimal:2',
    ];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'plan_id');
    }
}
