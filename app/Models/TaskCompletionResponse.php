<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TaskCompletionResponse extends Model
{
    protected $table = 'task_completion_responses';
    public $incrementing = false;
    protected $keyType = 'string';

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn (self $m) => $m->id ??= (string) Str::uuid());
    }

    protected $fillable = [
        'tenant_id', 'task_id', 'request_form_submission_id',
        'sender_type', 'sender_id', 'sender_name',
        'recipient_email', 'recipient_name',
        'subject', 'body', 'status',
        'attachment_paths',
        'sent_at', 'failed_at', 'failure_reason',
        'client_request_id', 'metadata',
    ];

    protected $casts = [
        'attachment_paths' => 'array',
        'metadata'         => 'array',
        'sent_at'          => 'datetime',
        'failed_at'        => 'datetime',
    ];
}
