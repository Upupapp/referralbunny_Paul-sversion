<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lead extends Model
{
    use BelongsToTenant;

    protected $table = 'leads';
    public $incrementing = false;
    protected $keyType = 'string';

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $model) {
            if (empty($model->id)) {
                $model->id = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }

    protected $fillable = [
        'tenant_id', 'name', 'stage', 'status', 'days_left',
        'reseller_name', 'commission_status',
        'base_cost', 'added_amount', 'deal_value',
        'data', 'organization_id',
    ];

    protected $casts = [
        'data'         => 'array',
        'base_cost'    => 'decimal:2',
        'added_amount' => 'decimal:2',
        'deal_value'   => 'decimal:2',
        'days_left'    => 'integer',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function commissionSplits(): HasMany
    {
        return $this->hasMany(CommissionSplit::class, 'lead_id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(LeadNote::class, 'lead_id')->orderBy('created_at', 'asc');
    }

    public function history(): HasMany
    {
        return $this->hasMany(LeadHistory::class, 'lead_id')->orderBy('created_at', 'asc');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(LeadAttachment::class, 'lead_id');
    }

    public function links(): HasMany
    {
        return $this->hasMany(LeadLink::class, 'lead_id');
    }

    public function dealPartners(): HasMany
    {
        return $this->hasMany(DealPartner::class, 'deal_id');
    }

    public function partnerSplits(): HasMany
    {
        return $this->hasMany(DealPartnerSplit::class, 'deal_id')->whereNull('deleted_at');
    }

    public function extensionRequests(): HasMany
    {
        return $this->hasMany(DealAssignmentExtensionRequest::class, 'deal_id');
    }
}
