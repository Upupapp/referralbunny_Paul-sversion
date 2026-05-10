<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class LeadHistory extends Model
{
    protected $table = 'lead_history';
    public $incrementing = false;
    protected $keyType = 'string';

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn (self $m) => $m->id ??= (string) Str::uuid());
    }
    const UPDATED_AT = null;

    protected $fillable = [
        'lead_id',
        'tenant_id',
        'action',
        'type',
        'category',
        'reseller',
        'actor_name',
        'actor_role',
        'old_values',
        'new_values',
        'metadata',
        'date',
    ];

    protected $casts = [
        'date'       => 'date',
        'old_values' => 'array',
        'new_values' => 'array',
        'metadata'   => 'array',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }
}
