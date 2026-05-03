<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Payment extends Model
{
    public $incrementing = false;
    protected $keyType   = 'string';

    protected $fillable = [
        'tenant_id', 'subscription_id', 'invoice_id', 'plan_id',
        'provider', 'amount', 'currency', 'base_amount_php',
        'display_amount', 'display_currency', 'exchange_rate_used',
        'status', 'external_payment_id', 'payment_method',
        'failure_reason', 'retry_count', 'next_retry_at', 'metadata_json',
    ];

    protected $casts = [
        'metadata_json'     => 'array',
        'amount'            => 'decimal:2',
        'base_amount_php'   => 'decimal:2',
        'display_amount'    => 'decimal:2',
        'exchange_rate_used'=> 'float',
        'retry_count'       => 'integer',
        'next_retry_at'     => 'datetime',
    ];

    public function tenant(): BelongsTo       { return $this->belongsTo(Tenant::class, 'tenant_id'); }
    public function subscription(): BelongsTo { return $this->belongsTo(Subscription::class, 'subscription_id'); }
    public function invoice(): BelongsTo      { return $this->belongsTo(Invoice::class, 'invoice_id'); }
    public function refund(): HasOne          { return $this->hasOne(Refund::class, 'payment_id'); }
}
