<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class TenantUser extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table      = 'tenant_users';
    public    $incrementing = false;
    protected $keyType    = 'string';

    protected $fillable = [
        'id', 'first_name', 'last_name', 'email', 'password', 'status', 'remember_token',
        'nickname', 'phone_number', 'job_title', 'department', 'organization',
        'location', 'timezone', 'language', 'bio', 'profile_photo_path',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = ['id' => 'string'];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function memberships()
    {
        return $this->hasMany(TenantMembership::class, 'tenant_user_id');
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function getDisplayNameAttribute(): string
    {
        return \App\Services\UserDisplayNameService::resolve($this, false, 'tenant_admin');
    }

    public function getProfilePhotoUrlAttribute(): ?string
    {
        return \App\Services\UserDisplayNameService::photoUrl($this);
    }

    public function getInitialsAttribute(): string
    {
        return \App\Services\UserDisplayNameService::initials($this);
    }
}
