<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Promotion extends Model
{
    public $incrementing = false;
    protected $keyType   = 'string';

    protected $fillable = [
        'id', 'name', 'description', 'promotion_type', 'discount_type', 'discount_value',
        'currency', 'target_scope', 'target_ids_json', 'applies_to_plan_ids_json',
        'applies_to_billing_cycles', 'valid_from', 'valid_until', 'auto_apply',
        'allow_stacking', 'max_discounts_per_invoice', 'affects_duration', 'duration_months',
        'promo_code_id', 'status', 'created_by',
    ];

    protected $casts = [
        'discount_value'             => 'decimal:2',
        'target_ids_json'            => 'array',
        'applies_to_plan_ids_json'   => 'array',
        'applies_to_billing_cycles'  => 'array',
        'valid_from'                 => 'datetime',
        'valid_until'                => 'datetime',
        'auto_apply'                 => 'boolean',
        'allow_stacking'             => 'boolean',
    ];

    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function promoCode(): BelongsTo { return $this->belongsTo(PromoCode::class); }

    public function isActive(): bool
    {
        if ($this->status !== 'active') return false;
        if ($this->valid_until && $this->valid_until->isPast()) return false;
        return true;
    }
}
