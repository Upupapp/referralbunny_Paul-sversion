<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class EmailLog extends Model
{
    protected $table    = 'email_logs';
    public $incrementing = false;
    protected $keyType  = 'string';

    protected $fillable = [
        'id', 'email_key', 'recipient_email', 'recipient_type', 'recipient_id',
        'tenant_id', 'subject', 'status', 'sent_at', 'failed_at',
        'error_message', 'metadata',
    ];

    protected $casts = [
        'metadata'  => 'array',
        'sent_at'   => 'datetime',
        'failed_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn($m) => $m->id ??= (string) Str::uuid());
    }
}
