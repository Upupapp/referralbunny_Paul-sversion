<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TenantAccessRequest extends Model
{
    protected $table      = 'tenant_access_requests';
    public    $incrementing = false;
    protected $keyType    = 'string';

    protected $fillable = [
        'id', 'tenant_id', 'email', 'first_name', 'last_name',
        'requested_role', 'reason', 'status', 'reviewed_by', 'reviewed_at',
    ];

    protected $casts = [
        'id'          => 'string',
        'reviewed_at' => 'datetime',
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
}
