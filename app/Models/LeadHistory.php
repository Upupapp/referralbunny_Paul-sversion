<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeadHistory extends Model
{
    protected $table = 'lead_history';
    public $incrementing = false;
    protected $keyType = 'string';
    const UPDATED_AT = null;

    protected $fillable = ['lead_id', 'action', 'type', 'reseller', 'date'];
    protected $casts = ['date' => 'date'];
}
