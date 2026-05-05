<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    protected $table = 'notifications';
    public $incrementing = false;
    protected $keyType = 'string';
    const UPDATED_AT = null;

    protected $fillable = [
        'tenant_id', 'notifiable_type', 'notifiable_id',
        'category', 'type', 'priority',
        'title', 'message', 'action_url', 'action_label',
        'channel', 'frequency_type', 'escalation_level',
        'is_read', 'is_dismissed',
        'deduplication_key', 'metadata_json',
        'archived_at', 'expires_at', 'sent_at',
    ];

    protected $casts = [
        'metadata_json'    => 'array',
        'is_read'          => 'boolean',
        'is_dismissed'     => 'boolean',
        'escalation_level' => 'integer',
        'sent_at'          => 'datetime',
        'archived_at'      => 'datetime',
        'expires_at'       => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function scopeUnread($query)
    {
        return $query->where('is_read', false)->where('is_dismissed', false);
    }

    public function scopeActive($query)
    {
        return $query->whereNull('archived_at')
                     ->where(fn($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function scopeForTenant($query, string $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeForUser($query, string $type, string $id)
    {
        return $query->where('notifiable_type', $type)->where('notifiable_id', $id);
    }

    public function scopeHighPriority($query)
    {
        return $query->whereIn('priority', ['high', 'critical', 'urgent']);
    }

    /** True title for display: uses title field if set, falls back to message. */
    public function getDisplayTitleAttribute(): string
    {
        return $this->title ?? \Illuminate\Support\Str::limit($this->message, 60);
    }

    /** Normalised priority CSS class for UI. */
    public function getPriorityColorAttribute(): string
    {
        return match($this->priority) {
            'urgent', 'critical' => 'red',
            'high'               => 'orange',
            'normal', 'medium'   => 'blue',
            default              => 'gray',
        };
    }
}
