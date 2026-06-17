<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ProgramConfigurationVersion extends Model
{
    public $incrementing = false;
    protected $keyType   = 'string';

    protected $fillable = [
        'tenant_id', 'program_id', 'version_number', 'status',
        'effective_from', 'published_at', 'published_by',
        'snapshot', 'change_summary', 'portal_impact_summary',
        'previous_version_id',
    ];

    protected $casts = [
        'version_number'  => 'integer',
        'snapshot'        => 'array',
        'effective_from'  => 'datetime',
        'published_at'    => 'datetime',
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

    public function program(): BelongsTo { return $this->belongsTo(Program::class); }
}
