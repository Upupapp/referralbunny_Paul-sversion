<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ThreadMessage extends Model
{
    protected $table    = 'thread_messages';
    public $incrementing = false;
    protected $keyType  = 'string';

    protected $fillable = [
        'id', 'thread_id', 'tenant_id',
        'sender_type', 'sender_id', 'sender_name',
        'body', 'is_read', 'read_at',
    ];

    protected $casts = [
        'is_read'    => 'boolean',
        'read_at'    => 'datetime',
        'created_at' => 'datetime',
    ];

    public function thread()
    {
        return $this->belongsTo(MessageThread::class, 'thread_id');
    }
}
