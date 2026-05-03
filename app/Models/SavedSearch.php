<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SavedSearch extends Model
{
    public $incrementing = false;
    protected $keyType   = 'string';
    const UPDATED_AT     = null;

    protected $fillable = ['id', 'user_id', 'name', 'query', 'filters_json', 'is_pinned'];
    protected $casts    = ['filters_json' => 'array', 'is_pinned' => 'boolean'];
}
