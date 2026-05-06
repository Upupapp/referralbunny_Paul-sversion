<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Contact extends Model
{
    use BelongsToTenant;

    protected $table = 'contacts';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'tenant_id', 'organization_id',
        'owner_user_id', 'owner_role', 'owner_referrer_id', 'linked_user_id',
        'first_name', 'last_name', 'full_name', 'nickname',
        'email', 'phone', 'alternate_email', 'alternate_phone',
        'job_title', 'department', 'company_or_organization',
        'address', 'city_or_municipality', 'province', 'region', 'country',
        'timezone', 'language', 'notes', 'tags',
        'contact_type', 'intended_role', 'visibility_scope',
        'source', 'internal_reference_id',
        'consent_status', 'consent_source', 'consent_date',
        'communication_preference', 'do_not_contact',
        'status', 'imported_from_batch_id',
        'created_by_user_id', 'updated_by_user_id', 'archived_at', 'data',
    ];

    protected $casts = [
        'tags'           => 'array',
        'data'           => 'array',
        'do_not_contact' => 'boolean',
        'archived_at'    => 'datetime',
        'consent_date'   => 'date',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn ($m) => $m->id ??= (string) Str::uuid());
    }

    /**
     * Display name priority: nickname > full_name > first+last > email.
     */
    public function getDisplayNameAttribute(): string
    {
        if ($this->nickname) return $this->nickname;
        if ($this->full_name) return $this->full_name;
        $parts = array_filter([$this->first_name, $this->last_name]);
        if (!empty($parts)) return implode(' ', $parts);
        return $this->email ?? 'Unknown Contact';
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function ownerReferrer(): BelongsTo
    {
        return $this->belongsTo(Reseller::class, 'owner_referrer_id');
    }

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class, 'imported_from_batch_id');
    }

    public function isReferrerOwned(): bool
    {
        return $this->owner_referrer_id !== null;
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function belongsToReferrer(string $resellerId): bool
    {
        return $this->owner_referrer_id === $resellerId;
    }
}
