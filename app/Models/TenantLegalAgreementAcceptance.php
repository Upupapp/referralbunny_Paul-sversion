<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TenantLegalAgreementAcceptance extends Model
{
    protected $keyType    = 'string';
    public    $incrementing = false;

    protected $fillable = [
        'id', 'tenant_legal_agreement_id', 'tenant_id',
        'user_type', 'user_id', 'user_role',
        'accepted_at', 'ip_address', 'user_agent',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function agreement()
    {
        return $this->belongsTo(TenantLegalAgreement::class, 'tenant_legal_agreement_id');
    }
}
