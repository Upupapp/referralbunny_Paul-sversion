<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ImportSnapshot extends Model
{
    public $incrementing = false;
    protected $keyType   = 'string';
    public $timestamps   = false; // manage created_at manually; updated_at nullable

    protected $fillable = [
        // Legacy ImportJob columns
        'id', 'import_job_id', 'entity_type', 'entity_id',
        'old_values_json', 'new_values_json', 'action', 'created_at',

        // Batch import columns
        'import_batch_id', 'import_batch_row_id', 'operation_type',
        'before_data', 'after_data', 'changed_fields',
        'can_rollback', 'rollback_status', 'rollback_reason', 'rolled_back_at',
        'updated_at',
    ];

    protected $casts = [
        'old_values_json' => 'array',
        'new_values_json' => 'array',
        'before_data'     => 'array',
        'after_data'      => 'array',
        'changed_fields'  => 'array',
        'can_rollback'    => 'boolean',
        'created_at'      => 'datetime',
        'rolled_back_at'  => 'datetime',
        'updated_at'      => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function ($m) {
            $m->id ??= (string) Str::uuid();
            $m->created_at ??= now();
        });
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class, 'import_batch_id');
    }

    public function batchRow(): BelongsTo
    {
        return $this->belongsTo(ImportBatchRow::class, 'import_batch_row_id');
    }

    // ── Status helpers ────────────────────────────────────────────────

    public function isPending(): bool   { return $this->rollback_status === 'pending'; }
    public function isConflict(): bool  { return $this->rollback_status === 'conflict'; }
    public function isRolledBack(): bool { return $this->rollback_status === 'rolled_back'; }

    public function markRolledBack(string $reason = ''): void
    {
        $this->update([
            'rollback_status' => 'rolled_back',
            'rollback_reason' => $reason ?: null,
            'rolled_back_at'  => now(),
            'updated_at'      => now(),
        ]);
    }

    public function markConflict(string $reason): void
    {
        $this->update([
            'rollback_status' => 'conflict',
            'rollback_reason' => $reason,
            'updated_at'      => now(),
        ]);
    }

    public function markSkipped(string $reason = ''): void
    {
        $this->update([
            'rollback_status' => 'skipped',
            'rollback_reason' => $reason ?: null,
            'updated_at'      => now(),
        ]);
    }

    public function markFailed(string $reason = ''): void
    {
        $this->update([
            'rollback_status' => 'failed',
            'rollback_reason' => $reason ?: null,
            'updated_at'      => now(),
        ]);
    }
}
