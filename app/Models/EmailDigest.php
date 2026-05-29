<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class EmailDigest extends Model
{
    protected $table = 'email_digests';
    public $incrementing = false;
    protected $keyType = 'string';

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn($m) => $m->id ??= (string) Str::uuid());
    }

    protected $fillable = [
        'id', 'tenant_id', 'recipient_email', 'recipient_name', 'recipient_type',
        'topic', 'topic_label', 'items', 'window_hours',
        'scheduled_at', 'sent_at', 'failed_at', 'retry_count',
    ];

    protected $casts = [
        'items'        => 'array',
        'scheduled_at' => 'datetime',
        'sent_at'      => 'datetime',
        'failed_at'    => 'datetime',
    ];

    public function scopePending($q)
    {
        return $q->whereNull('sent_at')->whereNull('failed_at');
    }

    public function scopeDue($q)
    {
        return $q->pending()->where('scheduled_at', '<=', now());
    }
}
