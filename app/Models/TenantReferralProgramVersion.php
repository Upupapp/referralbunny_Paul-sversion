<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class TenantReferralProgramVersion extends Model
{
    protected $table = 'tenant_referral_program_versions';
    public $incrementing = false;
    protected $keyType = 'string';
    const UPDATED_AT = null;

    protected $fillable = [
        'tenant_id', 'draft_id', 'config', 'published_at', 'created_by',
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

    public function draft(): BelongsTo
    {
        return $this->belongsTo(TenantReferralProgramDraft::class, 'draft_id');
    }
}
