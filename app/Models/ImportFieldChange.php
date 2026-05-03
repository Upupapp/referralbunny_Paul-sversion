<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportFieldChange extends Model
{
    public $incrementing = false;
    protected $keyType   = 'string';
    const UPDATED_AT     = null;

    protected $fillable = [
        'id', 'import_job_id', 'import_row_id', 'entity_type', 'entity_id',
        'field_name', 'old_value', 'new_value', 'change_type', 'color_code', 'will_overwrite',
    ];

    protected $casts = ['will_overwrite' => 'boolean'];

    public const COLOR_CODES = [
        'new'        => '#10B981', // green
        'overwritten'=> '#F59E0B', // yellow
        'cleared'    => '#8B5CF6', // purple
        'warning'    => '#F97316', // orange
        'error'      => '#EF4444', // red
        'skipped'    => '#3B82F6', // blue
        'unchanged'  => '#9CA3AF', // gray
    ];
}
