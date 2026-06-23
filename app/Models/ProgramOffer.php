<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ProgramOffer extends Model
{
    public $incrementing = false;
    protected $keyType   = 'string';

    protected $fillable = [
        'tenant_id', 'program_id', 'program_group_id',
        'name', 'code', 'status', 'visibility',
        'current_version_id', 'created_by', 'updated_by',
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

    public function program(): BelongsTo   { return $this->belongsTo(Program::class); }
    public function group(): BelongsTo     { return $this->belongsTo(ProgramGroup::class, 'program_group_id'); }
    public function versions(): HasMany    { return $this->hasMany(ProgramOfferVersion::class, 'offer_id'); }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(ProgramOfferVersion::class, 'current_version_id');
    }

    public function scopeActive($query) { return $query->where('status', 'active'); }
}
