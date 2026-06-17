<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Program extends Model
{
    use SoftDeletes;

    public $incrementing = false;
    protected $keyType   = 'string';

    protected $fillable = [
        'tenant_id', 'name', 'slug', 'internal_code',
        'short_description', 'full_description',
        'program_type', 'objective_type',
        'industry_key', 'sub_industry_key',
        'status', 'is_default', 'is_featured',
        'timezone', 'default_currency', 'locale',
        'starts_at', 'ends_at',
        'enrollment_opens_at', 'enrollment_closes_at',
        'referral_period_opens_at', 'referral_period_closes_at',
        'evergreen', 'public_visibility',
        'application_mode', 'approval_mode',
        'attribution_model', 'attribution_window_days',
        'referral_expiry_days',
        'duplicate_referral_policy', 'organization_uniqueness_policy',
        'existing_customer_policy', 'self_referral_policy',
        'created_by', 'updated_by',
        'launched_by', 'launched_at',
        'paused_by', 'paused_at', 'paused_reason',
        'ended_by', 'ended_at',
        'archived_by', 'archived_at',
        'current_configuration_version_id',
        'lock_version',
    ];

    protected $casts = [
        'is_default'               => 'boolean',
        'is_featured'              => 'boolean',
        'evergreen'                => 'boolean',
        'attribution_window_days'  => 'integer',
        'referral_expiry_days'     => 'integer',
        'lock_version'             => 'integer',
        'starts_at'                => 'datetime',
        'ends_at'                  => 'datetime',
        'enrollment_opens_at'      => 'datetime',
        'enrollment_closes_at'     => 'datetime',
        'referral_period_opens_at' => 'datetime',
        'referral_period_closes_at'=> 'datetime',
        'launched_at'              => 'datetime',
        'paused_at'                => 'datetime',
        'ended_at'                 => 'datetime',
        'archived_at'              => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
            if (empty($model->slug) && $model->name) {
                $model->slug = Str::slug($model->name);
            }
        });
    }

    // ── Relationships ────────────────────────────────────────────────────────

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function groups(): HasMany
    {
        return $this->hasMany(ProgramGroup::class);
    }

    public function referrerMemberships(): HasMany
    {
        return $this->hasMany(ReferrerProgramMembership::class);
    }

    public function partnerMemberships(): HasMany
    {
        return $this->hasMany(PartnerProgramMembership::class);
    }

    public function offers(): HasMany
    {
        return $this->hasMany(ProgramOffer::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(ProgramContract::class);
    }

    public function configurationVersions(): HasMany
    {
        return $this->hasMany(ProgramConfigurationVersion::class);
    }

    public function currentConfigurationVersion(): BelongsTo
    {
        return $this->belongsTo(ProgramConfigurationVersion::class, 'current_configuration_version_id');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function memberActionItems(): HasMany
    {
        return $this->hasMany(MemberActionItem::class);
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    public function scopeForTenant($query, string $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeVisible($query)
    {
        return $query->whereNotIn('status', ['archived']);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    public function isActive(): bool     { return $this->status === 'active'; }
    public function isDraft(): bool      { return $this->status === 'draft'; }
    public function isScheduled(): bool  { return $this->status === 'scheduled'; }
    public function isPaused(): bool     { return $this->status === 'paused'; }
    public function isEnded(): bool      { return $this->status === 'ended'; }
    public function isArchived(): bool   { return $this->status === 'archived'; }

    public function isPubliclyVisible(): bool
    {
        return in_array($this->public_visibility, ['public', 'unlisted'], true)
            && in_array($this->status, ['active', 'scheduled', 'paused', 'ended'], true);
    }

    public static function allStatuses(): array
    {
        return ['draft', 'scheduled', 'active', 'paused', 'ended', 'archived'];
    }

    public static function allTypes(): array
    {
        return ['referral', 'affiliate', 'partner', 'ambassador', 'employee', 'customer', 'event', 'custom'];
    }
}
