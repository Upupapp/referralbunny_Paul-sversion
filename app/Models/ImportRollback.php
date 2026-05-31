<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ImportRollback extends Model
{
    use BelongsToTenant;
    public $incrementing = false;
    protected $keyType   = 'string';

    protected $fillable = [
        // Legacy
        'id', 'import_job_id', 'requested_by', 'status',
        'rollback_summary_json', 'records_restored', 'records_deleted', 'completed_at',

        // Batch rollback
        'import_batch_id', 'tenant_id', 'requested_by_type', 'mode',
        'dry_run_summary', 'result_summary', 'error_summary',
        'started_at', 'records_skipped', 'records_failed', 'records_conflict',
    ];

    protected $casts = [
        'rollback_summary_json' => 'array',
        'dry_run_summary'       => 'array',
        'result_summary'        => 'array',
        'error_summary'         => 'array',
        'completed_at'          => 'datetime',
        'started_at'            => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn($m) => $m->id ??= (string) Str::uuid());
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class, 'import_batch_id');
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(ImportSnapshot::class, 'import_batch_id', 'import_batch_id');
    }

    // ── Status helpers ────────────────────────────────────────────────

    public function isProcessing(): bool  { return $this->status === 'processing'; }
    public function isCompleted(): bool   { return in_array($this->status, ['completed', 'completed_with_warnings']); }
    public function isFailed(): bool      { return $this->status === 'failed'; }

    public function markProcessing(): void
    {
        $this->update(['status' => 'processing', 'started_at' => now()]);
    }

    public function markCompleted(array $result): void
    {
        $this->update([
            'status'           => ($result['conflict'] ?? 0) > 0 || ($result['failed'] ?? 0) > 0
                ? 'completed_with_warnings' : 'completed',
            'result_summary'   => $result,
            'records_restored' => $result['restored'] ?? 0,
            'records_deleted'  => $result['removed']  ?? 0,
            'records_skipped'  => $result['skipped']  ?? 0,
            'records_failed'   => $result['failed']   ?? 0,
            'records_conflict' => $result['conflict'] ?? 0,
            'completed_at'     => now(),
        ]);
    }

    public function markFailed(string $error): void
    {
        $this->update([
            'status'        => 'failed',
            'error_summary' => ['message' => $error],
            'completed_at'  => now(),
        ]);
    }
}
