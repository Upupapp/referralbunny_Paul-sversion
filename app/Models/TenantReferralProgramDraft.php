<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class TenantReferralProgramDraft extends Model
{
    protected $table = 'tenant_referral_program_drafts';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id', 'status', 'mode', 'config', 'current_step',
        'published_at', 'created_by',
    ];

    protected $casts = [
        'config'       => 'array',
        'published_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn (self $m) => $m->id ??= (string) Str::uuid());
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(TenantReferralProgramVersion::class, 'draft_id')->orderByDesc('published_at');
    }
}
