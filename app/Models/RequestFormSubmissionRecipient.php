<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class RequestFormSubmissionRecipient extends Model
{
    protected $table = 'request_form_submission_recipients';
    public $incrementing = false;
    protected $keyType = 'string';

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn (self $m) => $m->id ??= (string) Str::uuid());
    }

    protected $fillable = [
        'tenant_id', 'request_form_submission_id',
        'recipient_type', 'recipient_id',
        'recipient_email', 'recipient_name',
        'task_id', 'assignment_status',
    ];
}
