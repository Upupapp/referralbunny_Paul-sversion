<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MessageThread extends Model
{
    protected $table    = 'message_threads';
    public $incrementing = false;
    protected $keyType  = 'string';

    protected $fillable = [
        'id', 'tenant_id', 'reseller_id',
        'last_message_at', 'last_message_preview',
        'admin_unread', 'reseller_unread',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    public function messages()
    {
        return $this->hasMany(ThreadMessage::class, 'thread_id');
    }

    public function reseller()
    {
        return $this->belongsTo(Reseller::class, 'reseller_id');
    }
}
