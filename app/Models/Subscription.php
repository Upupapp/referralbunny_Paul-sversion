<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    public $incrementing = false;
    protected $keyType   = 'string';

    protected $fillable = [
        'tenant_id', 'plan_id', 'status', 'billing_cycle',
        'start_date', 'trial_end_date', 'next_billing_date',
        'canceled_at', 'external_subscription_id', 'payment_provider',
        // v35 billing metadata
        'billing_status', 'manual_note', 'assigned_by',
        'extended_at', 'extended_by', 'extension_reason',
        'subscription_end_date', 'payment_required', 'auto_renew',
    ];

    protected $casts = [
        'start_date'            => 'date',
        'trial_end_date'        => 'date',
        'next_billing_date'     => 'date',
        'subscription_end_date' => 'date',
        'canceled_at'           => 'datetime',
        'extended_at'           => 'datetime',
        'payment_required'      => 'boolean',
        'auto_renew'            => 'boolean',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class, 'tenant_id'); }
    public function plan(): BelongsTo   { return $this->belongsTo(Plan::class, 'plan_id'); }
    public function invoices(): HasMany  { return $this->hasMany(Invoice::class, 'subscription_id'); }
    public function payments(): HasMany  { return $this->hasMany(Payment::class, 'subscription_id'); }

    public function isTrialing(): bool  { return $this->status === 'trial'; }
    public function isActive(): bool    { return in_array($this->status, ['active', 'comped', 'internal', 'manual']); }
    public function isSuspended(): bool { return $this->status === 'suspended'; }
    public function isInternal(): bool  { return in_array($this->billing_status ?? '', ['internal', 'comped']); }
    public function isPaymentRequired(): bool { return (bool) ($this->payment_required ?? true); }

    public function trialDaysRemaining(): int
    {
        if (!$this->trial_end_date) return 0;
        return max(0, (int) now()->diffInDays($this->trial_end_date, false));
    }
}
