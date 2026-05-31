<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class DealComment extends Model
{
    use SoftDeletes;
    protected $table      = 'deal_comments';
    public    $incrementing = false;
    protected $keyType    = 'string';

    protected $fillable = [
        'tenant_id', 'deal_id', 'author_user_id', 'author_role',
        'body', 'visibility', 'parent_comment_id', 'edited_at',
        'client_request_id',
    ];

    protected $casts = [
        'edited_at'  => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn($m) => $m->id ??= (string) Str::uuid());
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'deal_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(DealComment::class, 'parent_comment_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(DealNoteAttachment::class, 'deal_comment_id')->orderBy('created_at');
    }

    public function mentions(): HasMany
    {
        return $this->hasMany(DealNoteMention::class, 'deal_comment_id')->orderBy('created_at');
    }

    public function isDeleted(): bool
    {
        return $this->deleted_at !== null;
    }

    public function isInternal(): bool
    {
        return $this->visibility === 'internal_admin';
    }
}
