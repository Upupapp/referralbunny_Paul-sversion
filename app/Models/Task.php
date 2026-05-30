<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Task extends Model
{
    use SoftDeletes;

    protected $table = 'tasks';
    public $incrementing = false;
    protected $keyType = 'string';

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn (self $m) => $m->id ??= (string) Str::uuid());
    }

    protected $fillable = [
        'tenant_id', 'title', 'description', 'status', 'priority', 'category',
        'assigned_to_type', 'assigned_to_id',
        'assigned_by_type', 'assigned_by_id',
        'created_by_type',  'created_by_id',
        'taskable_type', 'taskable_id',
        'source_type', 'source_id',
        'requestor_name', 'requestor_email',
        'due_at', 'started_at', 'completed_at',
        'completed_by_type', 'completed_by_id',
        'cancelled_at', 'metadata', 'visibility',
    ];

    protected $casts = [
        'metadata'     => 'array',
        'due_at'       => 'datetime',
        'started_at'   => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function activities(): HasMany
    {
        return $this->hasMany(TaskActivity::class, 'task_id')->orderBy('created_at');
    }

    public function completionResponses(): HasMany
    {
        return $this->hasMany(TaskCompletionResponse::class, 'task_id')->orderByDesc('created_at');
    }

    public function taskable(): MorphTo
    {
        return $this->morphTo();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function isCompletable(): bool
    {
        return in_array($this->status, ['open', 'in_progress', 'waiting']);
    }

    public function isOverdue(): bool
    {
        return $this->due_at && $this->due_at->isPast() && !in_array($this->status, ['completed', 'cancelled', 'archived']);
    }

    public function resolveRequestorEmail(): ?string
    {
        if ($this->requestor_email) return $this->requestor_email;

        if ($this->source_type === 'request_form_submission' && $this->source_id) {
            return RequestFormSubmission::where('id', $this->source_id)
                ->where('tenant_id', $this->tenant_id)
                ->value('submitter_email');
        }

        return null;
    }

    public function resolveRequestorName(): ?string
    {
        if ($this->requestor_name) return $this->requestor_name;

        if ($this->source_type === 'request_form_submission' && $this->source_id) {
            return RequestFormSubmission::where('id', $this->source_id)
                ->where('tenant_id', $this->tenant_id)
                ->value('submitter_name');
        }

        return null;
    }
}
