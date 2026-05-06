<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PendingReferrerInvite extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'tenant_id', 'import_batch_id', 'email', 'name', 'status', 'row_ids',
    ];

    protected $casts = [
        'row_ids' => 'array',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn ($m) => $m->id ??= (string) Str::uuid());
    }
}
