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
        'permissions_json', 'can_manage_billing', 'can_delete_tenant',
        'can_transfer_ownership', 'is_custom_permissions', 'invited_by_user_id',
    ];

    protected $casts = [
        'id'                        => 'string',
        'joined_by_invitation'      => 'boolean',
        'password_review_completed' => 'boolean',
        'setup_completed'           => 'boolean',
        'last_accessed_at'          => 'datetime',
        'joined_at'                 => 'datetime',
        'permissions_json'          => 'array',
        'can_manage_billing'        => 'boolean',
        'can_delete_tenant'         => 'boolean',
        'can_transfer_ownership'    => 'boolean',
        'is_custom_permissions'     => 'boolean',
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
