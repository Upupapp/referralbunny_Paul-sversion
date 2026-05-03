<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportSnapshot extends Model
{
    public $incrementing = false;
    protected $keyType   = 'string';
    public $timestamps   = false;

    protected $fillable = [
        'id', 'import_job_id', 'entity_type', 'entity_id',
        'old_values_json', 'new_values_json', 'action', 'created_at',
    ];

    protected $casts = [
        'old_values_json' => 'array',
        'new_values_json' => 'array',
        'created_at'      => 'datetime',
    ];
}
