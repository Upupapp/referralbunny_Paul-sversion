<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentMethod extends Model
{
    public $incrementing = false;
    protected $keyType   = 'string';
    const UPDATED_AT     = null;

    protected $fillable = [
        'tenant_id', 'provider', 'external_payment_method_id',
        'type', 'last_four', 'brand', 'is_default',
    ];

    protected $casts = ['is_default' => 'boolean'];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class, 'tenant_id'); }
}
