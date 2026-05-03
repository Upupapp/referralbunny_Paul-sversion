<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportRollback extends Model
{
    public $incrementing = false;
    protected $keyType   = 'string';

    protected $fillable = [
        'id', 'import_job_id', 'requested_by', 'status',
        'rollback_summary_json', 'records_restored', 'records_deleted', 'completed_at',
    ];

    protected $casts = [
        'rollback_summary_json' => 'array',
        'completed_at'          => 'datetime',
    ];

    public function job(): BelongsTo { return $this->belongsTo(ImportJob::class, 'import_job_id'); }
}
