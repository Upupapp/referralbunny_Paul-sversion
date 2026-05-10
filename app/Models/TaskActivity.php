<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TaskActivity extends Model
{
    protected $table = 'task_activities';
    public $incrementing = false;
    protected $keyType = 'string';
    const UPDATED_AT = null;

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn (self $m) => $m->id ??= (string) Str::uuid());
    }

    protected $fillable = [
        'tenant_id', 'task_id',
        'actor_type', 'actor_id', 'actor_name',
        'action_type',
        'old_values', 'new_values', 'metadata',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'metadata'   => 'array',
    ];
}
