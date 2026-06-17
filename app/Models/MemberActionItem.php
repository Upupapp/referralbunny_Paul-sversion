<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class MemberActionItem extends Model
{
    public $incrementing = false;
    protected $keyType   = 'string';

    protected $fillable = [
        'tenant_id', 'program_id', 'membership_type', 'membership_id',
        'action_type', 'target_type', 'target_id',
        'status', 'due_at', 'completed_at',
        'source_configuration_version_id', 'notification_status',
    ];

    protected $casts = [
        'due_at'       => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function program(): BelongsTo { return $this->belongsTo(Program::class); }

    public function isPending(): bool   { return $this->status === 'pending'; }
    public function isCompleted(): bool { return $this->status === 'completed'; }
    public function isOverdue(): bool
    {
        return $this->isPending() && $this->due_at && $this->due_at->isPast();
    }
}
