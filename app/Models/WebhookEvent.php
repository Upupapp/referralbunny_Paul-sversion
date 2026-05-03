<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookEvent extends Model
{
    public $incrementing = false;
    protected $keyType   = 'string';
    const UPDATED_AT     = null;

    protected $fillable = ['provider', 'event_type', 'payload', 'processed', 'error'];
    protected $casts    = ['payload' => 'array', 'processed' => 'boolean'];
}
