<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $table = 'messages';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'sender_id', 'target_type', 'target_ids_json',
        'channel', 'subject', 'body', 'status',
        'scheduled_at', 'sent_at',
    ];

    protected $casts = [
        'target_ids_json' => 'array',
        'scheduled_at'    => 'datetime',
        'sent_at'         => 'datetime',
    ];
}
