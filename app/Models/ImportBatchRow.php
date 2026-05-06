<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ImportBatchRow extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'import_batch_id', 'row_number',
        'raw_data', 'normalized_data', 'computed_data',
        'validation_status', 'issue_codes', 'row_action',
        'existing_deal_id', 'created_deal_id', 'organization_id',
        'approved_by_id', 'approved_at', 'error_message',
    ];

    protected $casts = [
        'raw_data'        => 'array',
        'normalized_data' => 'array',
        'computed_data'   => 'array',
        'issue_codes'     => 'array',
        'approved_at'     => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn ($m) => $m->id ??= (string) Str::uuid());
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class, 'import_batch_id');
    }
}
