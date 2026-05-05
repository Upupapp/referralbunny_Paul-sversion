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
        return "{$this->first_name} {$this->last_name}";
    }
}
