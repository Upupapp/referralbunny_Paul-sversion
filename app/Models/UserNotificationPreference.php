<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserNotificationPreference extends Model
{
    protected $table = 'user_notification_preferences';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'user_id', 'in_app_enabled', 'email_enabled', 'sms_enabled',
        'daily_digest_enabled', 'weekly_digest_enabled', 'monthly_report_enabled',
        'critical_alerts_only', 'categories_json',
    ];

    protected $casts = [
        'in_app_enabled'        => 'boolean',
        'email_enabled'         => 'boolean',
        'sms_enabled'           => 'boolean',
        'daily_digest_enabled'  => 'boolean',
        'weekly_digest_enabled' => 'boolean',
        'monthly_report_enabled'=> 'boolean',
        'critical_alerts_only'  => 'boolean',
        'categories_json'       => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
