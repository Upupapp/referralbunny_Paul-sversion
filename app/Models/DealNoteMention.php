<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class DealNoteMention extends Model
{
    protected $table = 'deal_note_mentions';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id', 'deal_comment_id',
        'mentionable_type', 'mentionable_id', 'display_name_snapshot',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn($m) => $m->id ??= (string) Str::uuid());
    }

    public function comment(): BelongsTo
    {
        return $this->belongsTo(DealComment::class, 'deal_comment_id');
    }
}
