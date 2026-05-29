<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ContactRoleInvitation extends Model
{
    protected $table = 'contact_role_invitations';

    public    $incrementing = false;
    protected $keyType      = 'string';

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn($m) => $m->id ??= (string) Str::uuid());
    }

    protected $fillable = [
        'id', 'tenant_id', 'contact_id', 'invited_email', 'invited_role',
        'invited_by_user_id', 'invited_by_role',
        'status', 'token', 'expires_at', 'accepted_at',
        'revoked_at', 'revoked_by_user_id',
        'associated_deal_id', 'permissions_json', 'message',
        'reminder_count', 'last_reminder_sent_at',
        'linked_reseller_id', 'linked_tenant_user_id', 'linked_partner_id',
    ];

    protected $casts = [
        'expires_at'            => 'datetime',
        'accepted_at'           => 'datetime',
        'revoked_at'            => 'datetime',
        'last_reminder_sent_at' => 'datetime',
        'permissions_json'      => 'array',
        'reminder_count'        => 'integer',
    ];

    // ── Relationships ─────────────────────────────────────────────────────

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'associated_deal_id');
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    public function isValid(): bool
    {
        return $this->status === 'pending'
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function roleLabel(): string
    {
        return match ($this->invited_role) {
            'referrer'       => 'Referrer',
            'tenant_manager' => 'Tenant Manager',
            'tenant_staff'   => 'Tenant Staff',
            'partner'        => 'Partner',
            default          => ucfirst($this->invited_role),
        };
    }
}
