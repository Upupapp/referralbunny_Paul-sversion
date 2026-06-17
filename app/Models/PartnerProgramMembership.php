<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class PartnerProgramMembership extends Model
{
    public $incrementing = false;
    protected $keyType   = 'string';

    protected $fillable = [
        'tenant_id', 'program_id', 'partner_id', 'program_group_id',
        'status', 'source', 'invitation_id',
        'active_contract_id', 'assigned_manager_id',
        'tags', 'metadata',
        'joined_at', 'approved_at', 'activated_at',
        'suspended_at', 'removed_at', 'expired_at', 'last_activity_at',
    ];

    protected $casts = [
        'tags'             => 'array',
        'metadata'         => 'array',
        'joined_at'        => 'datetime',
        'approved_at'      => 'datetime',
        'activated_at'     => 'datetime',
        'suspended_at'     => 'datetime',
        'removed_at'       => 'datetime',
        'expired_at'       => 'datetime',
        'last_activity_at' => 'datetime',
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

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(ProgramGroup::class, 'program_group_id');
    }

    public function activeContract(): BelongsTo
    {
        return $this->belongsTo(ProgramContract::class, 'active_contract_id');
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(ProgramContract::class, 'membership_id')
            ->where('membership_type', 'partner');
    }

    public function actionItems(): HasMany
    {
        return $this->hasMany(MemberActionItem::class, 'membership_id')
            ->where('membership_type', 'partner');
    }

    public function scopeActive($query)        { return $query->where('status', 'active'); }
    public function scopeForProgram($q, $pid)  { return $q->where('program_id', $pid); }
    public function scopeForPartner($q, $pid)  { return $q->where('partner_id', $pid); }

    public function isActive(): bool { return $this->status === 'active'; }
}
