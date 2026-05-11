<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommissionSplit extends Model
{
    protected $table = 'commission_splits';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'lead_id', 'reseller_name', 'percentage', 'role', 'activity_status',
    ];

    protected $casts = [
        'percentage' => 'decimal:4',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }
}
