<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reseller extends Model
{
    protected $table = 'resellers';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id', 'name', 'email', 'status',
        'assigned_leads', 'closed_value', 'performance_score',
        'joined_date', 'phone', 'territory', 'is_anonymous',
    ];

    protected $casts = [
        'joined_date'      => 'date',
        'closed_value'     => 'decimal:2',
        'assigned_leads'   => 'integer',
        'performance_score'=> 'integer',
        'is_anonymous'     => 'boolean',
    ];

    /**
     * Mask sensitive fields for non-admin viewers (e.g. other resellers).
     * Tenant admins always receive the full record — never call this for admin views.
     */
    public function toAnonymousArray(): array
    {
        return [
            'id'                => $this->id,
            'tenant_id'         => $this->tenant_id,
            'name'              => 'Anonymous Referrer',
            'email'             => null,
            'phone'             => null,
            'status'            => $this->status,
            'assigned_leads'    => $this->assigned_leads,
            'closed_value'      => $this->closed_value,
            'performance_score' => $this->performance_score,
            'is_anonymous'      => true,
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }
}
