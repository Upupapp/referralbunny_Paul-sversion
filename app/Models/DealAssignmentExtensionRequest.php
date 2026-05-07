<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class DealAssignmentExtensionRequest extends Model
{
    protected $table      = 'deal_assignment_extension_requests';
    public    $incrementing = false;
    protected $keyType    = 'string';

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    protected $fillable = [
        'tenant_id', 'deal_id',
        'requested_by_user_id', 'requested_by_role',
        'current_stage', 'current_expiry_at', 'current_days_left',
        'requested_days', 'requested_new_expiry_at',
        'approved_days', 'approved_new_expiry_at',
        'reason', 'admin_note', 'status',
        'reviewed_by_user_id', 'reviewed_at', 'metadata',
    ];

    protected $casts = [
        'current_expiry_at'       => 'datetime',
        'requested_new_expiry_at' => 'datetime',
        'approved_new_expiry_at'  => 'datetime',
        'reviewed_at'             => 'datetime',
        'metadata'                => 'array',
        'requested_days'          => 'integer',
        'approved_days'           => 'integer',
        'current_days_left'       => 'integer',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'deal_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending_review');
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pending_review'          => 'Pending Review',
            'approved'                => 'Approved',
            'rejected'                => 'Rejected',
            'clarification_requested' => 'Clarification Requested',
            'cancelled'               => 'Cancelled',
            'expired'                 => 'Expired',
            default                   => ucfirst($this->status),
        };
    }
}
