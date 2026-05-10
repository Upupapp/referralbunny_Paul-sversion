<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class RequestFormSubmission extends Model
{
    protected $table = 'request_form_submissions';
    public $incrementing = false;
    protected $keyType = 'string';

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $m) {
            $m->id                     ??= (string) Str::uuid();
            $m->public_submission_uuid ??= (string) Str::uuid();
        });
    }

    protected $fillable = [
        'tenant_id', 'request_form_id', 'public_submission_uuid',
        'submitter_name', 'submitter_email', 'request_for', 'notes',
        'payload', 'selected_recipient_ids',
        'source_ip_hash', 'user_agent_hash',
        'status', 'duplicate_fingerprint', 'submitted_at',
    ];

    protected $casts = [
        'payload'                => 'array',
        'selected_recipient_ids' => 'array',
        'submitted_at'           => 'datetime',
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(RequestForm::class, 'request_form_id');
    }

    public function submissionRecipients(): HasMany
    {
        return $this->hasMany(RequestFormSubmissionRecipient::class, 'request_form_submission_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'source_id')
            ->where('source_type', 'request_form_submission');
    }
}
