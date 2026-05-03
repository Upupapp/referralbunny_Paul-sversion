<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReindexJob extends Model
{
    public $incrementing = false;
    protected $keyType   = 'string';

    protected $fillable = [
        'id', 'entity_type', 'status', 'records_indexed', 'error', 'last_run',
    ];

    protected $casts = ['last_run' => 'datetime'];
}
