<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ProgramGroup extends Model
{
    public $incrementing = false;
    protected $keyType   = 'string';

    protected $fillable = [
        'tenant_id', 'program_id', 'membership_type',
        'name', 'slug', 'description', 'status',
        'is_default', 'application_mode', 'default_offer_id',
        'portal_welcome_content', 'visibility',
        'manager_assignment_mode', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'is_default' => 'boolean',
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

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
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

    public function scopeForProgram($query, string $programId)
    {
        return $query->where('program_id', $programId);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
