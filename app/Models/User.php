<?php

namespace App\Models;

use App\Services\UserDisplayNameService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password',
        'nickname', 'phone_number', 'job_title', 'department',
        'location', 'timezone', 'language', 'bio', 'profile_photo_path',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }

    public function getDisplayNameAttribute(): string
    {
        return UserDisplayNameService::resolve($this, false, 'super_admin');
    }

    public function getProfilePhotoUrlAttribute(): ?string
    {
        return UserDisplayNameService::photoUrl($this);
    }

    public function getInitialsAttribute(): string
    {
        return UserDisplayNameService::initials($this);
    }
}
