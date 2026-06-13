<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantReferralProgramTemplate extends Model
{
    protected $table = 'tenant_referral_program_templates';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'industry_key', 'name', 'description', 'config',
    ];

    protected $casts = [
        'config' => 'array',
    ];
}
