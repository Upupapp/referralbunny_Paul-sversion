<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromoRedemptionAttempt extends Model
{
    public $incrementing = false;
    protected $keyType   = 'string';
    public $timestamps   = false;

    protected $fillable = [
        'id', 'promo_code_id', 'code_attempted', 'tenant_id', 'failure_reason', 'attempt_data', 'attempted_at',
    ];

    protected $casts = [
        'attempt_data' => 'array',
        'attempted_at' => 'datetime',
    ];

    public function promoCode(): BelongsTo { return $this->belongsTo(PromoCode::class); }
    public function tenant(): BelongsTo    { return $this->belongsTo(Tenant::class, 'tenant_id'); }
}
