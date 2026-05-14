<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class GoogleCalendarIntegration extends Model
{
    protected $table = 'google_calendar_integrations';
    public $incrementing = false;
    protected $keyType  = 'string';

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn (self $m) => $m->id ??= (string) Str::uuid());
    }

    protected $fillable = [
        'tenant_id', 'tenant_user_id',
        'access_token', 'refresh_token', 'token_expires_at',
        'google_calendar_id', 'google_email', 'scopes',
        'is_active', 'connected_at', 'last_synced_at',
    ];

    protected $casts = [
        'token_expires_at' => 'datetime',
        'connected_at'     => 'datetime',
        'last_synced_at'   => 'datetime',
        'is_active'        => 'boolean',
    ];

    // ── Encrypted token helpers ───────────────────────────────────────────────

    public function getAccessTokenPlainAttribute(): ?string
    {
        try { return $this->access_token ? Crypt::decryptString($this->access_token) : null; }
        catch (\Throwable) { return null; }
    }

    public function getRefreshTokenPlainAttribute(): ?string
    {
        try { return $this->refresh_token ? Crypt::decryptString($this->refresh_token) : null; }
        catch (\Throwable) { return null; }
    }

    public function isTokenExpired(): bool
    {
        return !$this->token_expires_at || $this->token_expires_at->isPast();
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function calendarEvents()
    {
        return $this->hasMany(GoogleCalendarEvent::class, 'integration_id');
    }
}
