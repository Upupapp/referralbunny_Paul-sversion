<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionHistory extends Model
{
    public $incrementing = false;
    protected $keyType   = 'string';
    public $timestamps   = false;

    protected $fillable = [
        'tenant_id', 'old_plan_id', 'new_plan_id',
        'old_status', 'new_status', 'changed_by', 'reason',
    ];

    protected $casts = ['changed_at' => 'datetime'];

    public function tenant(): BelongsTo   { return $this->belongsTo(Tenant::class, 'tenant_id'); }
    public function oldPlan(): BelongsTo  { return $this->belongsTo(Plan::class, 'old_plan_id'); }
    public function newPlan(): BelongsTo  { return $this->belongsTo(Plan::class, 'new_plan_id'); }
}
