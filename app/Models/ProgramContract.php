<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ProgramContract extends Model
{
    public $incrementing = false;
    protected $keyType   = 'string';

    protected $fillable = [
        'tenant_id', 'program_id', 'membership_type', 'membership_id',
        'offer_version_id', 'program_terms_version_id',
        'group_terms_version_id', 'individual_override_version_id',
        'status', 'proposed_at', 'accepted_at', 'declined_at',
        'effective_from', 'effective_until', 'ended_at',
        'acceptance_ip', 'acceptance_user_agent',
        'acceptance_method', 'acceptance_evidence',
        'previous_contract_id',
    ];

    protected $casts = [
        'acceptance_evidence' => 'array',
        'proposed_at'         => 'datetime',
        'accepted_at'         => 'datetime',
        'declined_at'         => 'datetime',
        'effective_from'      => 'datetime',
        'effective_until'     => 'datetime',
        'ended_at'            => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function program(): BelongsTo    { return $this->belongsTo(Program::class); }
    public function offerVersion(): BelongsTo { return $this->belongsTo(ProgramOfferVersion::class, 'offer_version_id'); }

    public function isActive(): bool    { return $this->status === 'active'; }
    public function isProposed(): bool  { return $this->status === 'proposed'; }
}
