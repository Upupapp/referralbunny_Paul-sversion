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
    ];

    protected $casts = [
        'start_date'       => 'date',
        'trial_end_date'   => 'date',
        'next_billing_date'=> 'date',
        'canceled_at'      => 'datetime',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class, 'tenant_id'); }
    public function plan(): BelongsTo   { return $this->belongsTo(Plan::class, 'plan_id'); }
    public function invoices(): HasMany  { return $this->hasMany(Invoice::class, 'subscription_id'); }
    public function payments(): HasMany  { return $this->hasMany(Payment::class, 'subscription_id'); }

    public function isTrialing(): bool  { return $this->status === 'trial'; }
    public function isActive(): bool    { return $this->status === 'active'; }
    public function isSuspended(): bool { return $this->status === 'suspended'; }

    public function trialDaysRemaining(): int
    {
        if (!$this->trial_end_date) return 0;
        return max(0, (int) now()->diffInDays($this->trial_end_date, false));
    }
}
