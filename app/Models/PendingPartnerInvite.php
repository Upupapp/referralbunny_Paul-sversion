<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PendingPartnerInvite extends Model
{
    protected $table = 'pending_partner_invites';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'tenant_id', 'import_batch_id', 'contact_id',
        'email', 'name', 'deal_id', 'added_by_referrer_id', 'status',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn ($m) => $m->id ??= (string) Str::uuid());
    }
}
