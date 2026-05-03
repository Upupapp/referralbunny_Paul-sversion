<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Credit extends Model
{
    public $incrementing = false;
    protected $keyType   = 'string';
    const UPDATED_AT     = null;

    protected $fillable = [
        'tenant_id', 'amount', 'currency', 'reason',
        'applied_to_invoice_id', 'created_by',
    ];

    protected $casts = ['amount' => 'decimal:2'];

    public function tenant(): BelongsTo  { return $this->belongsTo(Tenant::class, 'tenant_id'); }
    public function invoice(): BelongsTo { return $this->belongsTo(Invoice::class, 'applied_to_invoice_id'); }
}
