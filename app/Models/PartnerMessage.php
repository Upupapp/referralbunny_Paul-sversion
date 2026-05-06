<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PartnerMessage extends Model
{
    protected static function boot(): void
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }
    protected $table = 'partner_messages';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'thread_id', 'tenant_id', 'deal_id',
        'sender_type', 'sender_id', 'sender_name',
        'body', 'is_read', 'read_at',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'read_at' => 'datetime',
    ];

    public function thread(): BelongsTo
    {
        return $this->belongsTo(PartnerThread::class, 'thread_id');
    }
}
