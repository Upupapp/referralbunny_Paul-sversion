<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Deal-level approval requests: stage moves and archive requests submitted by Referrers.
 * Separate from the platform-level ApprovalRequest (used for pricing/promos).
 */
class DealApprovalRequest extends Model
{
    protected $table     = 'deal_approval_requests';
    public $incrementing = false;
    protected $keyType   = 'string';

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn($m) => $m->id = $m->id ?: (string) Str::uuid());
    }

    protected $fillable = [
        'tenant_id', 'type', 'subject_type', 'subject_id',
        'deal_id', 'requested_by_type', 'requested_by_id',
        'assigned_to_type', 'assigned_to_id', 'status',
        'request_payload', 'missing_requirements', 'reason',
        'reviewer_type', 'reviewer_id', 'reviewer_note',
        'clarification_message', 'clarification_due_at', 'visible_response',
        'approved_at', 'rejected_at', 'expires_at',
    ];

    protected $casts = [
        'request_payload'      => 'array',
        'missing_requirements' => 'array',
        'approved_at'          => 'datetime',
        'rejected_at'          => 'datetime',
        'expires_at'           => 'datetime',
        'clarification_due_at' => 'datetime',
    ];

    public function isPending(): bool              { return $this->status === 'pending'; }
    public function isApproved(): bool             { return $this->status === 'approved'; }
    public function isRejected(): bool             { return $this->status === 'rejected'; }
    public function isClarificationRequested(): bool { return $this->status === 'clarification_requested'; }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'deal_id');
    }
}
