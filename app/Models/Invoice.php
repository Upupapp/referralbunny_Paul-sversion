<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    public $incrementing = false;
    protected $keyType   = 'string';

    protected $fillable = [
        'tenant_id', 'subscription_id', 'invoice_number',
        'base_amount_php', 'display_amount', 'display_currency',
        'exchange_rate_used', 'tax_rate', 'tax_type', 'tax_amount',
        'discount_amount', 'credits_applied', 'final_amount',
        'status', 'due_date', 'paid_at', 'line_items_json', 'notes',
    ];

    protected $casts = [
        'line_items_json'   => 'array',
        'due_date'          => 'date',
        'paid_at'           => 'datetime',
        'base_amount_php'   => 'decimal:2',
        'display_amount'    => 'decimal:2',
        'final_amount'      => 'decimal:2',
        'tax_amount'        => 'decimal:2',
        'discount_amount'   => 'decimal:2',
        'credits_applied'   => 'decimal:2',
        'exchange_rate_used'=> 'float',
        'tax_rate'          => 'float',
    ];

    public function tenant(): BelongsTo       { return $this->belongsTo(Tenant::class, 'tenant_id'); }
    public function subscription(): BelongsTo { return $this->belongsTo(Subscription::class, 'subscription_id'); }
    public function payments(): HasMany        { return $this->hasMany(Payment::class, 'invoice_id'); }
    public function credits(): HasMany         { return $this->hasMany(Credit::class, 'applied_to_invoice_id'); }

    public static function generateNumber(): string
    {
        return 'INV-' . strtoupper(date('Ym')) . '-' . str_pad((string) (static::whereMonth('created_at', now()->month)->count() + 1), 4, '0', STR_PAD_LEFT);
    }
}
