<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PricingHistory extends Model
{
    public $incrementing = false;
    protected $keyType   = 'string';
    const UPDATED_AT     = null;

    protected $fillable = [
        'id', 'plan_id', 'old_price_monthly', 'new_price_monthly',
        'old_price_yearly', 'new_price_yearly', 'currency', 'update_rule',
        'change_reason', 'effective_date', 'changed_by', 'approval_request_id',
    ];

    protected $casts = [
        'effective_date'    => 'date',
        'old_price_monthly' => 'decimal:2',
        'new_price_monthly' => 'decimal:2',
        'old_price_yearly'  => 'decimal:2',
        'new_price_yearly'  => 'decimal:2',
    ];

    public function plan(): BelongsTo    { return $this->belongsTo(Plan::class); }
    public function changedBy(): BelongsTo { return $this->belongsTo(User::class, 'changed_by'); }
}
