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
        'tenant_id', 'category', 'type', 'priority', 'message',
        'action_url', 'channel', 'frequency_type', 'escalation_level',
        'is_read', 'is_dismissed', 'metadata_json', 'sent_at',
    ];

    protected $casts = [
        'metadata_json'    => 'array',
        'is_read'          => 'boolean',
        'is_dismissed'     => 'boolean',
        'escalation_level' => 'integer',
        'sent_at'          => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function scopeUnread($query)
    {
        return $query->where('is_read', false)->where('is_dismissed', false);
    }

    public function scopeForTenant($query, string $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeHighPriority($query)
    {
        return $query->whereIn('priority', ['high', 'critical']);
    }
}
