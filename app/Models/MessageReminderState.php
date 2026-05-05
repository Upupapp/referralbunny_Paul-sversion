<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MessageReminderState extends Model
{
    protected $table    = 'message_reminder_states';
    public $incrementing = false;
    protected $keyType  = 'string';

    protected $fillable = [
        'id', 'notifiable_type', 'notifiable_id', 'tenant_id',
        'reminder_type', 'thread_id', 'deal_id', 'deduplication_key',
        'status', 'priority', 'title', 'body', 'action_url',
        'metadata', 'reminder_count', 'next_remind_at',
        'snoozed_until', 'resolved_at', 'dismissed_at',
    ];

    protected $casts = [
        'metadata'       => 'array',
        'next_remind_at' => 'datetime',
        'snoozed_until'  => 'datetime',
        'resolved_at'    => 'datetime',
        'dismissed_at'   => 'datetime',
        'reminder_count' => 'integer',
    ];

    public function thread()
    {
        return $this->belongsTo(MessageThread::class, 'thread_id');
    }

    /** Only reminders that are active and due now. */
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
            ->where(fn($q) => $q->whereNull('next_remind_at')
                                ->orWhere('next_remind_at', '<=', now()));
    }

    public function scopeForUser($query, string $type, string $id)
    {
        return $query->where('notifiable_type', $type)->where('notifiable_id', $id);
    }

    public function scopeUnresolved($query)
    {
        return $query->whereNotIn('status', ['resolved', 'dismissed']);
    }

    /** Priority sort value for ORDER BY. */
    public static function prioritySortRaw(): string
    {
        return "CASE priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END";
    }
}
