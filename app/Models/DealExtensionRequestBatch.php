<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class DealExtensionRequestBatch extends Model
{
    protected $table      = 'deal_extension_request_batches';
    public    $incrementing = false;
    protected $keyType    = 'string';

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
            if (empty($model->batch_reference)) {
                $model->batch_reference = 'BER-' . strtoupper(Str::random(8));
            }
        });
    }

    protected $fillable = [
        'tenant_id',
        'requested_by_reseller_id',
        'requested_by_role',
        'batch_reference',
        'shared_reason',
        'requested_extension_days',
        'status',
        'total_items',
        'pending_count',
        'approved_count',
        'declined_count',
        'skipped_count',
        'submitted_at',
        'resolved_at',
        'last_decision_at',
        'metadata',
    ];

    protected $casts = [
        'submitted_at'             => 'datetime',
        'resolved_at'              => 'datetime',
        'last_decision_at'         => 'datetime',
        'metadata'                 => 'array',
        'requested_extension_days' => 'integer',
        'total_items'              => 'integer',
        'pending_count'            => 'integer',
        'approved_count'           => 'integer',
        'declined_count'           => 'integer',
        'skipped_count'            => 'integer',
    ];

    // ── Relationships ──────────────────────────────────────────────

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function requestedByReseller(): BelongsTo
    {
        return $this->belongsTo(Reseller::class, 'requested_by_reseller_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(DealAssignmentExtensionRequest::class, 'batch_id');
    }

    public function pendingItems(): HasMany
    {
        return $this->hasMany(DealAssignmentExtensionRequest::class, 'batch_id')
            ->where('status', 'pending_review');
    }

    public function approvedItems(): HasMany
    {
        return $this->hasMany(DealAssignmentExtensionRequest::class, 'batch_id')
            ->where('status', 'approved');
    }

    public function declinedItems(): HasMany
    {
        return $this->hasMany(DealAssignmentExtensionRequest::class, 'batch_id')
            ->where('status', 'rejected');
    }

    public function skippedItems(): HasMany
    {
        return $this->hasMany(DealAssignmentExtensionRequest::class, 'batch_id')
            ->where('status', 'skipped');
    }

    // ── Scopes ────────────────────────────────────────────────────

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeForTenant($query, string $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    // ── Accessors ─────────────────────────────────────────────────

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pending'             => 'Pending Review',
            'partially_approved'  => 'Partially Approved',
            'approved'            => 'Approved',
            'declined'            => 'Declined',
            'partially_declined'  => 'Partially Declined',
            'cancelled'           => 'Cancelled',
            default               => ucfirst($this->status),
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'pending'             => 'yellow',
            'partially_approved'  => 'blue',
            'approved'            => 'green',
            'declined'            => 'red',
            'partially_declined'  => 'orange',
            'cancelled'           => 'gray',
            default               => 'gray',
        };
    }

    public function getIsResolvedAttribute(): bool
    {
        return in_array($this->status, ['approved', 'declined', 'cancelled']);
    }

    public function getHasPendingItemsAttribute(): bool
    {
        return $this->pending_count > 0;
    }
}
