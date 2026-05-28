<?php

namespace App\Models;

use App\Services\UserDisplayNameService;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Partner extends Authenticatable
{
    use Notifiable;

    protected $table    = 'partner_users';
    public $incrementing = false;
    protected $keyType  = 'string';

    protected static function boot(): void
    {
        parent::boot();

        // Keep tenant contacts in sync whenever a partner is created or updated.
        static::created(function (self $model) {
            if (!empty($model->email)) {
                try {
                    app(\App\Services\ContactSyncService::class)->syncPartner($model);
                } catch (\Throwable) {}
            }
        });

        static::updated(function (self $model) {
            if (!empty($model->email)) {
                try {
                    app(\App\Services\ContactSyncService::class)->syncPartner($model);
                } catch (\Throwable) {}
            }
        });
    }

    protected $fillable = [
        'id', 'tenant_id', 'email', 'password', 'setup_token', 'reset_token', 'reset_token_expires_at', 'remember_token', 'status',
        'first_name', 'last_name', 'nickname', 'phone_number',
        'organization', 'location', 'timezone', 'language', 'bio', 'profile_photo_path',
        'invited_by_type', 'invited_by_id', 'setup_completed_at', 'last_login_at',
    ];

    protected $hidden = ['password', 'remember_token', 'setup_token', 'reset_token'];

    protected $casts = [
        'setup_completed_at'      => 'datetime',
        'last_login_at'           => 'datetime',
        'reset_token_expires_at'  => 'datetime',
        'password'                => 'hashed',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function dealPartners()
    {
        return $this->hasMany(DealPartner::class, 'partner_user_id');
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function getDisplayNameAttribute(): string
    {
        return UserDisplayNameService::resolve($this, false, 'partner');
    }

    public function getProfilePhotoUrlAttribute(): ?string
    {
        return UserDisplayNameService::photoUrl($this);
    }

    public function getInitialsAttribute(): string
    {
        return UserDisplayNameService::initials($this);
    }

    public function isSetupComplete(): bool
    {
        return $this->setup_completed_at !== null;
    }
}
