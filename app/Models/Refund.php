<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Refund extends Model
{
    public $incrementing = false;
    protected $keyType   = 'string';

    protected $fillable = [
        'payment_id', 'tenant_id', 'amount', 'currency',
        'reason', 'status', 'processed_by', 'approved_by', 'notes',
    ];

    protected $casts = ['amount' => 'decimal:2'];

    public function payment(): BelongsTo  { return $this->belongsTo(Payment::class, 'payment_id'); }
    public function tenant(): BelongsTo   { return $this->belongsTo(Tenant::class, 'tenant_id'); }
}
