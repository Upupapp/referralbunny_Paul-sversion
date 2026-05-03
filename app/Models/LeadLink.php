<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeadLink extends Model
{
    protected $table = 'lead_links';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = ['lead_id', 'title', 'url'];
}
