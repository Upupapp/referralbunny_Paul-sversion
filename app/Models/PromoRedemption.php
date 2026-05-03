<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromoRedemption extends Model
{
    public $incrementing = false;
    protected $keyType   = 'string';
    const UPDATED_AT     = null;

    protected $fillable = [
        'id', 'promo_code_id', 'tenant_id', 'subscription_id', 'invoice_id',
        'discount_amount', 'currency', 'exchange_rate_used', 'redeemed_by', 'redeemed_at',
    ];

    protected $casts = [
        'discount_amount'   => 'decimal:2',
        'exchange_rate_used'=> 'decimal:6',
        'redeemed_at'       => 'datetime',
    ];

    public function promoCode(): BelongsTo    { return $this->belongsTo(PromoCode::class); }
    public function tenant(): BelongsTo       { return $this->belongsTo(Tenant::class, 'tenant_id'); }
    public function subscription(): BelongsTo { return $this->belongsTo(Subscription::class); }
    public function invoice(): BelongsTo      { return $this->belongsTo(Invoice::class); }
}
