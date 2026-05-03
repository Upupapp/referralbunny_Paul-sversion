<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecentSearch extends Model
{
    public $incrementing = false;
    protected $keyType   = 'string';
    public $timestamps   = false;

    protected $fillable = ['id', 'user_id', 'query', 'result_count', 'created_at'];
    protected $casts    = ['created_at' => 'datetime'];
}
