<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use App\Models\ImportRollback;
use App\Models\ImportSnapshot;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ImportBatch extends Model
{
    use BelongsToTenant;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'tenant_id', 'import_type', 'file_name', 'file_path',
        'imported_by_id', 'imported_by_role', 'status',
        'total_rows', 'successful_rows', 'updated_rows', 'skipped_rows', 'failed_rows',
        'duplicate_rows', 'unknown_referrer_rows', 'unknown_partner_rows',
        'pricing_issue_rows', 'blocked_rows', 'summary_json', 'started_at', 'completed_at',
        'possible_duplicate_rows', 'same_email_different_referrer_rows',
        'unknown_deal_rows', 'unknown_organization_rows',
        'unmapped_columns_json', 'column_actions_json', 'template_adoption_status',
        'rollback_status', 'rollback_id',
    ];

    protected $casts = [
        'summary_json'          => 'array',
        'unmapped_columns_json' => 'array',
        'column_actions_json'   => 'array',
        'started_at'            => 'datetime',
        'completed_at'          => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn ($m) => $m->id ??= (string) Str::uuid());
    }

    public function rows(): HasMany
    {
        return $this->hasMany(ImportBatchRow::class, 'import_batch_id');
    }

    public function isComplete(): bool
    {
        return in_array($this->status, ['completed', 'completed_with_warnings', 'failed']);
    }

    public function isRollbackEligible(): bool
    {
        return in_array($this->status, ['completed', 'completed_with_warnings'])
            && in_array($this->rollback_status ?? 'none', ['none', 'eligible'])
            && $this->rollback_id === null;
    }

    public function isAlreadyRolledBack(): bool
    {
        return in_array($this->rollback_status ?? 'none', ['completed', 'completed_with_warnings']);
    }

    public function rollback(): ?ImportRollback
    {
        return $this->rollback_id
            ? ImportRollback::find($this->rollback_id)
            : null;
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(ImportSnapshot::class, 'import_batch_id');
    }
}
