<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportRowError extends Model
{
    public $incrementing = false;
    protected $keyType   = 'string';
    const UPDATED_AT     = null;

    protected $fillable = [
        'id', 'import_job_id', 'import_row_id', 'row_number', 'column_name',
        'error_type', 'error_message', 'severity', 'suggested_fix',
    ];
}
