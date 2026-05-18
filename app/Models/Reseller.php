<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reseller extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table      = 'resellers';
    public    $incrementing = false;
    protected $keyType    = 'string';
    public    $timestamps = true; // updated_at added in migration_v24

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            if (empty($model->id)) {
                $model->id = (string) \Illuminate\Support\Str::uuid();
            }
        });

        // Keep tenant contacts in sync whenever a referrer is created or updated.
        static::created(function (self $model) {
            if (!empty($model->email) && !$model->is_anonymous) {
                try {
                    app(\App\Services\ContactSyncService::class)->syncReseller($model);
                } catch (\Throwable) {}
            }
        });

        static::updated(function (self $model) {
            if (!empty($model->email) && !$model->is_anonymous) {
                try {
                    app(\App\Services\ContactSyncService::class)->syncReseller($model);
                } catch (\Throwable) {}
            }
        });
    }

    protected $fillable = [
        'tenant_id', 'name', 'email', 'status',
        'assigned_leads', 'closed_value', 'performance_score',
        'joined_date', 'phone', 'territory', 'is_anonymous', 'anonymous_onboarded_at',
        'password', 'setup_token', 'setup_token_created_at', 'remember_token',
        'nickname', 'job_title', 'department', 'organization',
        'location', 'timezone', 'language', 'bio', 'profile_photo_path',
        // Multi-role support (v40)
        'linked_tenant_user_id',
        // Invitation summary fields (v40)
        'invite_deal_ids',
        'invite_deal_count',
        'invite_sent_at',
    ];

    protected $hidden = ['password', 'remember_token', 'setup_token'];

    protected $casts = [
        'joined_date'              => 'date',
        'setup_token_created_at'   => 'datetime',
        'closed_value'          => 'decimal:2',
        'assigned_leads'        => 'integer',
        'performance_score'     => 'integer',
        'is_anonymous'          => 'boolean',
        'password'              => 'hashed',
        // Multi-role / invitation summary (v40)
        'invite_deal_ids'       => 'array',
        'invite_deal_count'     => 'integer',
        'invite_sent_at'              => 'datetime',
        'anonymous_onboarded_at'      => 'datetime',
    ];

    public function toAnonymousArray(): array
    {
        return [
            'id'                => $this->id,
            'tenant_id'         => $this->tenant_id,
            'name'              => 'Anonymous Referrer',
            'email'             => null,
            'phone'             => null,
            'status'            => $this->status,
            'assigned_leads'    => $this->assigned_leads,
            'closed_value'      => $this->closed_value,
            'performance_score' => $this->performance_score,
            'is_anonymous'      => true,
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function getDisplayNameAttribute(): string
    {
        return \App\Services\UserDisplayNameService::resolve($this, (bool) $this->is_anonymous, 'referrer');
    }

    public function getDisplayNameForAttribute(): string
    {
        // Safe version — always respects anonymity
        return \App\Services\UserDisplayNameService::resolve($this, (bool) $this->is_anonymous, 'referrer');
    }

    public function getProfilePhotoUrlAttribute(): ?string
    {
        return \App\Services\UserDisplayNameService::photoUrl($this, (bool) $this->is_anonymous);
    }

    public function getInitialsAttribute(): string
    {
        return \App\Services\UserDisplayNameService::initials($this, (bool) $this->is_anonymous);
    }
}
