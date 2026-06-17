<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ProgramOfferVersion extends Model
{
    public $incrementing = false;
    protected $keyType   = 'string';

    protected $fillable = [
        'tenant_id', 'program_id', 'offer_id', 'version_number', 'status',
        'currency', 'reward_model', 'qualifying_event',
        'fixed_amount', 'percentage_rate', 'cap_amount', 'reward_rules',
        'effective_from', 'effective_until',
        'published_by', 'published_at', 'immutable_snapshot',
    ];

    protected $casts = [
        'version_number'    => 'integer',
        'fixed_amount'      => 'decimal:4',
        'percentage_rate'   => 'decimal:4',
        'cap_amount'        => 'decimal:4',
        'reward_rules'      => 'array',
        'immutable_snapshot'=> 'array',
        'effective_from'    => 'datetime',
        'effective_until'   => 'datetime',
        'published_at'      => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function offer(): BelongsTo   { return $this->belongsTo(ProgramOffer::class); }
    public function program(): BelongsTo { return $this->belongsTo(Program::class); }
}
