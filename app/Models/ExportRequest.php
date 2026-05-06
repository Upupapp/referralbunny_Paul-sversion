<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ExportRequest extends Model
{
    protected $table      = 'export_requests';
    public    $incrementing = false;
    protected $keyType    = 'string';

    // ── Status constants ──────────────────────────────────────────────────────

    const STATUS_PENDING        = 'pending';
    const STATUS_APPROVED       = 'approved';
    const STATUS_PROCESSING     = 'processing';
    const STATUS_READY          = 'ready';
    const STATUS_REJECTED       = 'rejected';
    const STATUS_CANCELLED      = 'cancelled';
    const STATUS_EXPIRED        = 'expired';
    const STATUS_FAILED         = 'failed';
    const STATUS_DIRECT_PENDING = 'direct_pending';
    const STATUS_DIRECT_READY   = 'direct_ready';

    // ── Export type constants ─────────────────────────────────────────────────

    const EXPORT_TYPES = [
        'deals',
        'contacts',
        'organizations',
        'referrers',
        'commissions',
        'users',
        'audit_logs',
        'reports',
        'messages',
        'import_summary',
    ];

    /**
     * Export types that are considered sensitive and may require mandatory approval.
     */
    const SENSITIVE_TYPES = [
        'commissions',
        'users',
        'audit_logs',
        'messages',
    ];

    // ── Fillable ──────────────────────────────────────────────────────────────

    protected $fillable = [
        'id',
        'tenant_id',
        'requester_type',
        'requester_id',
        'requester_role',
        'export_type',
        'export_format',
        'export_scope',
        'export_fields',
        'is_sensitive',
        'records_estimate',
        'reason',
        'status',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
        'file_path',
        'file_name',
        'file_size',
        'file_expires_at',
        'downloaded_at',
        'download_count',
        'error_message',
        'retry_count',
    ];

    // ── Casts ─────────────────────────────────────────────────────────────────

    protected $casts = [
        'export_scope'    => 'array',
        'export_fields'   => 'array',
        'is_sensitive'    => 'boolean',
        'records_estimate'=> 'integer',
        'file_size'       => 'integer',
        'download_count'  => 'integer',
        'retry_count'     => 'integer',
        'approved_at'     => 'datetime',
        'rejected_at'     => 'datetime',
        'file_expires_at' => 'datetime',
        'downloaded_at'   => 'datetime',
    ];

    // ── Boot ──────────────────────────────────────────────────────────────────

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /**
     * The TenantUser who approved this request.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'approved_by');
    }

    /**
     * The TenantUser who rejected this request.
     */
    public function rejector(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'rejected_by');
    }

    // ── Status helpers ────────────────────────────────────────────────────────

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isProcessing(): bool
    {
        return $this->status === self::STATUS_PROCESSING;
    }

    public function isReady(): bool
    {
        return in_array($this->status, [self::STATUS_READY, self::STATUS_DIRECT_READY]);
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isExpired(): bool
    {
        return $this->status === self::STATUS_EXPIRED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function isDirectPending(): bool
    {
        return $this->status === self::STATUS_DIRECT_PENDING;
    }

    public function isDirectReady(): bool
    {
        return $this->status === self::STATUS_DIRECT_READY;
    }

    /**
     * Check whether the generated file has passed its expiry datetime.
     */
    public function isFileExpired(): bool
    {
        if ($this->file_expires_at === null) {
            return false;
        }

        return $this->file_expires_at->isPast();
    }

    /**
     * True when the export file is available and has not expired.
     */
    public function canBeDownloaded(): bool
    {
        return $this->isReady() && !$this->isFileExpired();
    }

    /**
     * True when the requester may still cancel the request.
     * Only pending (awaiting approval) requests may be cancelled.
     */
    public function canBeCancelled(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_DIRECT_PENDING]);
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeReady(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_READY, self::STATUS_DIRECT_READY]);
    }

    // ── Computed attributes ───────────────────────────────────────────────────

    /**
     * Human-readable label for the current status.
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING        => 'Pending Approval',
            self::STATUS_APPROVED       => 'Approved',
            self::STATUS_PROCESSING     => 'Generating',
            self::STATUS_READY          => 'Ready to Download',
            self::STATUS_REJECTED       => 'Rejected',
            self::STATUS_CANCELLED      => 'Cancelled',
            self::STATUS_EXPIRED        => 'Expired',
            self::STATUS_FAILED         => 'Failed',
            self::STATUS_DIRECT_PENDING => 'Processing',
            self::STATUS_DIRECT_READY   => 'Ready to Download',
            default                     => ucfirst($this->status),
        };
    }

    /**
     * Tailwind color string for badge rendering.
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING        => 'yellow',
            self::STATUS_APPROVED       => 'blue',
            self::STATUS_PROCESSING,
            self::STATUS_DIRECT_PENDING => 'blue',
            self::STATUS_READY,
            self::STATUS_DIRECT_READY   => 'green',
            self::STATUS_REJECTED       => 'red',
            self::STATUS_CANCELLED      => 'gray',
            self::STATUS_EXPIRED        => 'gray',
            self::STATUS_FAILED         => 'red',
            default                     => 'gray',
        };
    }
}
