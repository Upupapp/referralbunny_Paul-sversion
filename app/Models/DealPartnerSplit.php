<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class DealPartnerSplit extends Model
{
    use SoftDeletes;

    protected $table      = 'deal_partner_splits';
    public    $incrementing = false;
    protected $keyType    = 'string';

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    protected $fillable = [
        'tenant_id', 'deal_id',
        'partner_user_id', 'partner_contact_id', 'partner_invitation_id',
        'partner_name', 'partner_email',
        'split_share_value', 'split_share_type', 'currency',
        'status', 'source',
        'created_by_user_id', 'updated_by_user_id',
        'accepted_at', 'removed_at', 'metadata',
    ];

    protected $casts = [
        'split_share_value' => 'decimal:4',
        'metadata'          => 'array',
        'accepted_at'       => 'datetime',
        'removed_at'        => 'datetime',
        'deleted_at'        => 'datetime',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'deal_id');
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'partner_user_id');
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'active'       => 'Active Partner',
            'pending_invite'=> 'Pending Invite',
            'provisional'  => 'Provisional',
            'invite_failed'=> 'Invite Failed',
            'removed'      => 'Removed',
            default        => ucfirst($this->status),
        };
    }

    public function getDisplayShareAttribute(): string
    {
        if ($this->split_share_type === 'fixed_amount') {
            return '₱' . number_format((float) $this->split_share_value, 2);
        }
        $pct = rtrim(rtrim(number_format((float) $this->split_share_value, 2, '.', ''), '0'), '.');
        return $pct . '%';
    }

    public function scopeActive($query)
    {
        return $query->whereNull('deleted_at')->where('status', '!=', 'removed');
    }
}
