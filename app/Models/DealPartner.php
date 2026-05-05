<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DealPartner extends Model
{
    protected $table    = 'deal_partners';
    public $incrementing = false;
    protected $keyType  = 'string';

    protected $fillable = [
        'id', 'tenant_id', 'deal_id', 'partner_user_id',
        'added_by_id', 'added_by_type', 'status', 'permissions',
        'invited_at', 'accepted_at', 'removed_at',
    ];

    protected $casts = [
        'permissions' => 'array',
        'invited_at'  => 'datetime',
        'accepted_at' => 'datetime',
        'removed_at'  => 'datetime',
    ];

    public function partner()
    {
        return $this->belongsTo(Partner::class, 'partner_user_id');
    }

    public function can(string $permission): bool
    {
        return (bool) ($this->permissions[$permission] ?? false);
    }
}
