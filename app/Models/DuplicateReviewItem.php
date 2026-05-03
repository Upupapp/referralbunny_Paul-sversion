<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DuplicateReviewItem extends Model
{
    public $incrementing = false;
    protected $keyType   = 'string';
    const UPDATED_AT     = null;

    protected $fillable = [
        'id', 'import_job_id', 'object_type', 'uploaded_row_data_json',
        'possible_match_entity_id', 'possible_match_data_json', 'match_score',
        'matched_fields_json', 'status', 'reviewed_by', 'reviewed_at',
    ];

    protected $casts = [
        'uploaded_row_data_json'  => 'array',
        'possible_match_data_json'=> 'array',
        'matched_fields_json'     => 'array',
        'reviewed_at'             => 'datetime',
    ];

    public function job(): BelongsTo { return $this->belongsTo(ImportJob::class, 'import_job_id'); }
}
