<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommissionSplit extends Model
{
    protected $table = 'commission_splits';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'lead_id', 'reseller_name', 'percentage', 'role', 'activity_status',
    ];
}
