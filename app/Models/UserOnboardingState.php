<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class UserOnboardingState extends Model
{
    protected $table      = 'user_onboarding_states';
    public    $incrementing = false;
    protected $keyType    = 'string';

    protected $fillable = [
        'id', 'user_type', 'user_id', 'tenant_id', 'role_key',
        'walkthrough_status', 'walkthrough_step',
        'completed_tasks', 'dismissed_prompts', 'snoozed_until',
        'is_fully_ready', 'first_seen_at', 'walkthrough_completed_at',
        'fully_ready_at', 'last_prompted_at',
    ];

    protected $casts = [
        'completed_tasks'        => 'array',
        'dismissed_prompts'      => 'array',
        'is_fully_ready'         => 'boolean',
        'snoozed_until'          => 'datetime',
        'first_seen_at'          => 'datetime',
        'walkthrough_completed_at' => 'datetime',
        'fully_ready_at'         => 'datetime',
        'last_prompted_at'       => 'datetime',
        'walkthrough_step'       => 'integer',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $m) {
            if (empty($m->id)) {
                $m->id = (string) Str::uuid();
            }
            if (empty($m->first_seen_at)) {
                $m->first_seen_at = now();
            }
        });
    }

    public function hasCompletedTask(string $key): bool
    {
        return in_array($key, $this->completed_tasks ?? []);
    }

    public function markTaskComplete(string $key): void
    {
        $tasks = $this->completed_tasks ?? [];
        if (! in_array($key, $tasks)) {
            $tasks[] = $key;
            $this->completed_tasks = $tasks;
            $this->save();
        }
    }

    public function dismissPrompt(string $key): void
    {
        $dismissed = $this->dismissed_prompts ?? [];
        if (! in_array($key, $dismissed)) {
            $dismissed[] = $key;
            $this->dismissed_prompts = $dismissed;
            $this->save();
        }
    }

    public function isPromptDismissed(string $key): bool
    {
        return in_array($key, $this->dismissed_prompts ?? []);
    }

    public function isSnoozed(): bool
    {
        return $this->snoozed_until && now()->lt($this->snoozed_until);
    }
}
