<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantSubIndustry extends Model
{
    protected $table = 'tenant_sub_industries';
    public $incrementing = false;
    protected $keyType = 'string';
    const UPDATED_AT = null;

    protected $fillable = ['tenant_id', 'sub_industry'];
}
