<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeadAttachment extends Model
{
    protected $table = 'lead_attachments';
    public $incrementing = false;
    protected $keyType = 'string';
    const UPDATED_AT = null;

    protected $fillable = ['lead_id', 'name', 'url'];
}
