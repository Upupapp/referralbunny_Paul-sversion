<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportRow extends Model
{
    public $incrementing = false;
    protected $keyType   = 'string';

    protected $fillable = [
        'id', 'import_job_id', 'row_number', 'raw_data_json', 'mapped_data_json',
        'matched_entity_type', 'matched_entity_id', 'status', 'error_count', 'warning_count',
        'created_entity_id', 'updated_entity_id',
    ];

    protected $casts = [
        'raw_data_json'    => 'array',
        'mapped_data_json' => 'array',
    ];

    public function job(): BelongsTo    { return $this->belongsTo(ImportJob::class, 'import_job_id'); }
    public function errors(): HasMany   { return $this->hasMany(ImportRowError::class); }

    public const COLOR_MAP = [
        'valid_new'    => 'green',
        'valid_update' => 'yellow',
        'warning'      => 'orange',
        'error'        => 'red',
        'skipped'      => 'blue',
        'imported'     => 'green',
        'failed'       => 'red',
    ];
}
