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
        'joined_date', 'phone', 'territory',
    ];

    protected $casts = [
        'joined_date'      => 'date',
        'closed_value'     => 'decimal:2',
        'assigned_leads'   => 'integer',
        'performance_score'=> 'integer',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }
}
