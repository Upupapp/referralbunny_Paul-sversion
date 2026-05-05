<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MessageDraft extends Model
{
    protected $table    = 'message_drafts';
    public $incrementing = false;
    protected $keyType  = 'string';

    protected $fillable = ['id', 'thread_id', 'tenant_id', 'author_type', 'author_id', 'body'];

    public function thread()
    {
        return $this->belongsTo(MessageThread::class, 'thread_id');
    }
}
