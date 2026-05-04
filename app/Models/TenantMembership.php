<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TenantMembership extends Model
{
    protected $table      = 'tenant_memberships';
    public    $incrementing = false;
    protected $keyType    = 'string';

    protected $fillable = [
        'id', 'tenant_id', 'tenant_user_id', 'role', 'status',
        'joined_by_invitation', 'password_review_completed',
        'setup_completed', 'last_accessed_at', 'joined_at',
    ];

    protected $casts = [
        'id'                        => 'string',
        'joined_by_invitation'      => 'boolean',
        'password_review_completed' => 'boolean',
        'setup_completed'           => 'boolean',
        'last_accessed_at'          => 'datetime',
        'joined_at'                 => 'datetime',
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

    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function tenantUser()
    {
        return $this->belongsTo(TenantUser::class, 'tenant_user_id');
    }
}
