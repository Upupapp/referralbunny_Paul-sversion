<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ActivityLog extends Model
{
    protected $table = 'activity_logs';
    public $incrementing = false;
    protected $keyType = 'string';
    const UPDATED_AT = null;

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn($m) => $m->id ??= (string) Str::uuid());
    }

    protected $fillable = [
        'tenant_id', 'user_id', 'action', 'entity', 'entity_id', 'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
