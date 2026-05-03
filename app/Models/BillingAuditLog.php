<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingAuditLog extends Model
{
    public $incrementing = false;
    protected $keyType   = 'string';
    const UPDATED_AT     = null;

    protected $fillable = [
        'tenant_id', 'action', 'entity_type', 'entity_id',
        'performed_by', 'reason', 'before_json', 'after_json',
    ];

    protected $casts = ['before_json' => 'array', 'after_json' => 'array'];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class, 'tenant_id'); }

    public static function log(string $action, array $options = []): self
    {
        return static::create([
            'tenant_id'    => $options['tenant_id']    ?? null,
            'action'       => $action,
            'entity_type'  => $options['entity_type']  ?? null,
            'entity_id'    => $options['entity_id']    ?? null,
            'performed_by' => $options['performed_by'] ?? null,
            'reason'       => $options['reason']       ?? null,
            'before_json'  => $options['before']       ?? [],
            'after_json'   => $options['after']        ?? [],
        ]);
    }
}
