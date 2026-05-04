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
        'id', 'tenant_id', 'email', 'role', 'token',
        'status', 'invited_by', 'accepted_at', 'expires_at',
    ];

    protected $casts = [
        'id'          => 'string',
        'accepted_at' => 'datetime',
        'expires_at'  => 'datetime',
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
