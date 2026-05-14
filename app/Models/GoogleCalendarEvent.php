<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class GoogleCalendarEvent extends Model
{
    protected $table = 'google_calendar_events';
    public $incrementing = false;
    protected $keyType  = 'string';

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn (self $m) => $m->id ??= (string) Str::uuid());
    }

    protected $fillable = [
        'tenant_id', 'integration_id',
        'entity_type', 'entity_id',
        'google_event_id', 'google_calendar_id', 'synced_at',
    ];

    protected $casts = [
        'synced_at' => 'datetime',
    ];

    public function integration()
    {
        return $this->belongsTo(GoogleCalendarIntegration::class, 'integration_id');
    }
}
