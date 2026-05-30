<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalRequest extends Model
{
    protected $fillable = [
        'request_type', 'reference_id', 'reference_type',
        'requested_by', 'required_permission', 'status', 'notes',
        'request_data', 'reviewer_notes', 'approved_by', 'approved_at',
        'rejected_by', 'rejected_at',
        'tenant_id',
    ];

    protected $casts = [
        'request_data' => 'array',
        'approved_at'  => 'datetime',
        'rejected_at'  => 'datetime',
    ];

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopeForTenant($query, string $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function isPending(): bool  { return $this->status === 'pending'; }
    public function isApproved(): bool { return $this->status === 'approved'; }
}
