<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportMappingProfile extends Model
{
    public $incrementing = false;
    protected $keyType   = 'string';

    protected $fillable = [
        'id', 'user_id', 'tenant_id', 'object_type', 'profile_name',
        'mapping_json', 'header_fingerprint',
    ];

    protected $casts = ['mapping_json' => 'array'];
}
