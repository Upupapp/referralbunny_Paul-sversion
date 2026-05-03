<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PromoCode extends Model
{
    public $incrementing = false;
    protected $keyType   = 'string';

    protected $fillable = [
        'id', 'code', 'name', 'description', 'discount_type', 'discount_value',
        'currency', 'max_redemptions', 'redemptions_count', 'valid_from', 'valid_until',
        'applies_to_plan_ids_json', 'applies_to_billing_cycle', 'eligibility_rules_json',
        'allow_stacking', 'status', 'created_by',
    ];

    protected $casts = [
        'discount_value'          => 'decimal:2',
        'valid_from'              => 'datetime',
        'valid_until'             => 'datetime',
        'applies_to_plan_ids_json'=> 'array',
        'eligibility_rules_json'  => 'array',
        'allow_stacking'          => 'boolean',
    ];

    public function createdBy(): BelongsTo  { return $this->belongsTo(User::class, 'created_by'); }
    public function redemptions(): HasMany  { return $this->hasMany(PromoRedemption::class, 'promo_code_id'); }
    public function attempts(): HasMany     { return $this->hasMany(PromoRedemptionAttempt::class, 'promo_code_id'); }

    public function isActive(): bool
    {
        if ($this->status !== 'active') return false;
        if ($this->valid_until && $this->valid_until->isPast()) return false;
        if ($this->max_redemptions !== null && $this->redemptions_count >= $this->max_redemptions) return false;
        return true;
    }

    public function isUnlimited(): bool
    {
        return $this->max_redemptions === null;
    }
}
