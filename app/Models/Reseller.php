<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reseller extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table      = 'resellers';
    public    $incrementing = false;
    protected $keyType    = 'string';
    public    $timestamps = false; // resellers table has no updated_at

    protected $fillable = [
        'tenant_id', 'name', 'email', 'status',
        'assigned_leads', 'closed_value', 'performance_score',
        'joined_date', 'phone', 'territory', 'is_anonymous',
        'password', 'setup_token', 'remember_token',
    ];

    protected $hidden = ['password', 'remember_token', 'setup_token'];

    protected $casts = [
        'joined_date'       => 'date',
        'closed_value'      => 'decimal:2',
        'assigned_leads'    => 'integer',
        'performance_score' => 'integer',
        'is_anonymous'      => 'boolean',
        'password'          => 'hashed',
    ];

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
