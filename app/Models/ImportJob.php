<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportJob extends Model
{
    public $incrementing = false;
    protected $keyType   = 'string';

    protected $fillable = [
        'id', 'import_name', 'import_type', 'object_type', 'tenant_id', 'uploaded_by',
        'file_name', 'file_size', 'file_type', 'source_type', 'template_version', 'status',
        'total_rows', 'processed_rows', 'successful_rows', 'failed_rows', 'skipped_rows',
        'warning_rows', 'overwritten_fields_count', 'cleared_fields_count',
        'import_mode', 'overwrite_mode', 'risk_level', 'approval_required', 'approval_request_id',
        'column_mapping_json', 'headers_detected_json', 'file_language', 'date_format',
        'number_format', 'timezone', 'detected_encoding', 'selected_encoding',
        'communication_consent_confirmed', 'consent_confirmed_by', 'consent_confirmed_at',
        'automation_triggers_json', 'migration_mode_enabled', 'rollback_available_until',
        'rollback_status', 'last_processed_row', 'resumable', 'error_summary_json',
        'raw_file_path', 'started_at', 'completed_at', 'canceled_at',
    ];

    protected $casts = [
        'column_mapping_json'        => 'array',
        'headers_detected_json'      => 'array',
        'automation_triggers_json'   => 'array',
        'error_summary_json'         => 'array',
        'approval_required'          => 'boolean',
        'migration_mode_enabled'     => 'boolean',
        'resumable'                  => 'boolean',
        'communication_consent_confirmed' => 'boolean',
        'rollback_available_until'   => 'datetime',
        'consent_confirmed_at'       => 'datetime',
        'started_at'                 => 'datetime',
        'completed_at'               => 'datetime',
        'canceled_at'                => 'datetime',
    ];

    public function uploadedBy(): BelongsTo { return $this->belongsTo(User::class, 'uploaded_by'); }
    public function tenant(): BelongsTo     { return $this->belongsTo(Tenant::class, 'tenant_id'); }
    public function rows(): HasMany         { return $this->hasMany(ImportRow::class); }
    public function errors(): HasMany       { return $this->hasMany(ImportRowError::class); }
    public function fieldChanges(): HasMany { return $this->hasMany(ImportFieldChange::class); }
    public function duplicates(): HasMany   { return $this->hasMany(DuplicateReviewItem::class); }
    public function rollbacks(): HasMany    { return $this->hasMany(ImportRollback::class); }
    public function snapshots(): HasMany    { return $this->hasMany(ImportSnapshot::class); }

    public function isComplete(): bool   { return in_array($this->status, ['completed', 'completed_with_errors']); }
    public function isRunning(): bool    { return in_array($this->status, ['importing', 'validating_rows', 'parsing']); }
    public function canRollback(): bool  { return $this->rollback_status === 'available' && $this->isComplete(); }
    public function canCancel(): bool    { return !in_array($this->status, ['completed', 'completed_with_errors', 'failed', 'canceled', 'rolled_back']); }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'uploaded'             => 'Uploaded',
            'parsing'              => 'Reading File',
            'structure_validating' => 'Checking Structure',
            'mapping_required'     => 'Mapping Required',
            'validating_rows'      => 'Validating',
            'ready_for_review'     => 'Ready for Review',
            'waiting_for_approval' => 'Awaiting Approval',
            'approved'             => 'Approved',
            'importing'            => 'Importing',
            'completed'            => 'Completed',
            'completed_with_errors'=> 'Completed with Errors',
            'failed'               => 'Failed',
            'canceled'             => 'Canceled',
            'rolled_back'          => 'Rolled Back',
            'sandbox_completed'    => 'Sandbox Complete',
            default                => ucfirst($this->status),
        };
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            'completed'             => 'badge-green',
            'completed_with_errors' => 'badge-orange',
            'failed'                => 'badge-red',
            'canceled', 'rolled_back' => 'badge-gray',
            'waiting_for_approval'  => 'badge-orange',
            'importing', 'validating_rows', 'parsing' => 'badge-blue',
            default                 => 'badge-blue',
        };
    }
}
