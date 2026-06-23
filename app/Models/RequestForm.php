<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class RequestForm extends Model
{
    use SoftDeletes;

    protected $table = 'request_forms';
    public $incrementing = false;
    protected $keyType = 'string';

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $m) {
            $m->id           ??= (string) Str::uuid();
            $m->public_token ??= Str::random(48);
            if (empty($m->slug)) {
                $m->slug = Str::slug($m->title) . '-' . Str::random(6);
            }
        });
    }

    protected $fillable = [
        'tenant_id', 'program_id', 'created_by_type', 'created_by_id',
        'title', 'slug', 'public_token',
        'description', 'success_message', 'status',
        'is_public', 'allow_multiple_recipients', 'max_recipients',
        'settings', 'published_at', 'archived_at',
    ];

    protected $casts = [
        'is_public'                  => 'boolean',
        'allow_multiple_recipients'  => 'boolean',
        'settings'                   => 'array',
        'published_at'               => 'datetime',
        'archived_at'                => 'datetime',
    ];

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function scopeForProgram($query, string $programId)
    {
        return $query->where('program_id', $programId);
    }

    public function fields(): HasMany
    {
        return $this->hasMany(RequestFormField::class, 'request_form_id')->orderBy('sort_order');
    }

    public function recipientOptions(): HasMany
    {
        return $this->hasMany(RequestFormRecipientOption::class, 'request_form_id')
            ->where('is_active', true)
            ->orderBy('sort_order');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(RequestFormSubmission::class, 'request_form_id')->orderByDesc('submitted_at');
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function publicUrl(): string
    {
        return url('/request/' . $this->public_token);
    }
}
