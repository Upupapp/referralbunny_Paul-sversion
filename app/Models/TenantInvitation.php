<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TenantInvitation extends Model
{
    protected $table      = 'tenant_invitations';
    public    $incrementing = false;
    protected $keyType    = 'string';

    protected $fillable = [
        'id', 'tenant_id', 'email', 'role', 'token', 'permissions_preset',
        'status', 'invited_by', 'accepted_at', 'expires_at',
        'initial_email_sent_at',
        'reminder_count', 'last_reminder_sent_at', 'next_reminder_at',
        'inviter_reminder_count', 'last_inviter_reminder_at', 'next_inviter_reminder_at',
        'reminder_suppressed_at', 'reminder_suppressed_reason',
        'last_manual_resend_at',
    ];

    protected $casts = [
        'id'                        => 'string',
        'accepted_at'               => 'datetime',
        'expires_at'                => 'datetime',
        'initial_email_sent_at'     => 'datetime',
        'last_reminder_sent_at'     => 'datetime',
        'next_reminder_at'          => 'datetime',
        'last_inviter_reminder_at'  => 'datetime',
        'next_inviter_reminder_at'  => 'datetime',
        'reminder_suppressed_at'    => 'datetime',
        'last_manual_resend_at'     => 'datetime',
        'reminder_count'            => 'integer',
        'inviter_reminder_count'    => 'integer',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
            if (empty($model->token)) {
                $model->token = Str::random(48);
            }
        });
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function invitedBy()
    {
        return $this->belongsTo(TenantUser::class, 'invited_by');
    }

    public function isValid(): bool
    {
        return $this->status === 'pending' && now()->lt($this->expires_at);
    }
}
