<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\UserNotificationPreference;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    public function send(
        string  $category,
        string  $type,
        string  $priority,
        string  $message,
        ?string $tenantId   = null,
        ?string $actionUrl  = null,
        string  $channel    = 'in_app',
        array   $metadata   = []
    ): Notification {
        $notification = Notification::create([
            'tenant_id'    => $tenantId,
            'category'     => $category,
            'type'         => $type,
            'priority'     => $priority,
            'message'      => $message,
            'action_url'   => $actionUrl,
            'channel'      => $channel,
            'metadata_json'=> $metadata,
            'sent_at'      => now(),
        ]);

        // Send via appropriate channels
        if (in_array($channel, ['email', 'all'])) {
            $this->sendEmail($notification);
        }

        if (in_array($channel, ['sms', 'all']) && in_array($priority, ['high', 'critical'])) {
            $this->sendSms($notification);
        }

        return $notification;
    }

    public function notifyTenantInactive(string $tenantId, int $daysSinceActivity): void
    {
        $priority = match (true) {
            $daysSinceActivity >= 30 => 'critical',
            $daysSinceActivity >= 14 => 'high',
            default                  => 'medium',
        };

        $this->send(
            category:  'tenant_health',
            type:      'warning',
            priority:  $priority,
            message:   "Tenant has been inactive for {$daysSinceActivity} days.",
            tenantId:  $tenantId,
            actionUrl: "/admin/tenants/{$tenantId}",
            channel:   $daysSinceActivity >= 14 ? 'all' : 'in_app',
            metadata:  ['days_inactive' => $daysSinceActivity],
        );
    }

    public function notifyHealthDrop(string $tenantId, int $oldScore, int $newScore): void
    {
        $this->send(
            category:  'tenant_health',
            type:      'action_required',
            priority:  $newScore < 50 ? 'high' : 'medium',
            message:   "Health score dropped from {$oldScore} to {$newScore}.",
            tenantId:  $tenantId,
            actionUrl: "/admin/tenants/{$tenantId}",
            channel:   $newScore < 50 ? 'email' : 'in_app',
            metadata:  ['old_score' => $oldScore, 'new_score' => $newScore],
        );
    }

    public function notifyPaymentFailed(string $tenantId, int $daysSinceFail = 0): void
    {
        [$priority, $channel] = match (true) {
            $daysSinceFail >= 7 => ['critical', 'all'],
            $daysSinceFail >= 3 => ['high',     'all'],
            $daysSinceFail >= 1 => ['high',     'email'],
            default             => ['medium',   'in_app'],
        };

        $this->send(
            category:  'billing',
            type:      'action_required',
            priority:  $priority,
            message:   "Payment failed for this tenant. Day {$daysSinceFail} of non-payment.",
            tenantId:  $tenantId,
            actionUrl: "/admin/tenants/{$tenantId}/billing",
            channel:   $channel,
            metadata:  ['days_since_fail' => $daysSinceFail],
        );
    }

    public function notifyTrialExpiring(string $tenantId, int $daysLeft): void
    {
        $this->send(
            category:  'billing',
            type:      'warning',
            priority:  $daysLeft <= 1 ? 'critical' : 'high',
            message:   "Trial expires in {$daysLeft} day" . ($daysLeft !== 1 ? 's' : '') . ".",
            tenantId:  $tenantId,
            actionUrl: "/admin/tenants/{$tenantId}",
            channel:   $daysLeft <= 3 ? 'all' : 'email',
            metadata:  ['days_left' => $daysLeft],
        );
    }

    public function notifySystemAlert(string $message, string $priority = 'high'): void
    {
        $this->send(
            category: 'system',
            type:     'system_alert',
            priority: $priority,
            message:  $message,
            channel:  $priority === 'critical' ? 'all' : 'in_app',
        );
    }

    public function markAsRead(string $notificationId): void
    {
        Notification::where('id', $notificationId)->update(['is_read' => true]);
    }

    public function dismiss(string $notificationId): void
    {
        Notification::where('id', $notificationId)->update(['is_dismissed' => true]);
    }

    public function markAllRead(?string $tenantId = null): void
    {
        $query = Notification::where('is_read', false);
        if ($tenantId) $query->where('tenant_id', $tenantId);
        $query->update(['is_read' => true]);
    }

    public function escalate(): void
    {
        // Escalate unread high/critical notifications older than 24h
        Notification::where('is_read', false)
            ->where('is_dismissed', false)
            ->whereIn('priority', ['high', 'critical'])
            ->where('created_at', '<', now()->subHours(24))
            ->where('escalation_level', '<', 3)
            ->chunkById(100, function ($notifications) {
                foreach ($notifications as $notif) {
                    $notif->increment('escalation_level');
                    if ($notif->escalation_level >= 2 && $notif->channel !== 'sms') {
                        $this->sendSms($notif);
                    }
                }
            });
    }

    private function sendEmail(Notification $notification): void
    {
        try {
            $admins = User::where('email', 'admin@referralbunny.com')->get();
            foreach ($admins as $admin) {
                $prefs = UserNotificationPreference::where('user_id', $admin->id)->first();
                if ($prefs && !$prefs->email_enabled) continue;
                if ($prefs && $prefs->critical_alerts_only && $notification->priority !== 'critical') continue;

                // Route through the email digest service to prevent spam.
                // Notifications of the same category for the same admin are batched
                // into a single digest email (sent within 1 hour).
                app(\App\Services\EmailDigestService::class)->queue(
                    email:         $admin->email,
                    name:          $admin->name ?? 'Admin',
                    topic:         'platform_' . ($notification->category ?? 'general'),
                    topicLabel:    ucwords(str_replace('_', ' ', $notification->category ?? 'Platform Update')),
                    item:          [
                        'title'      => $notification->message,
                        'body'       => $notification->message,
                        'priority'   => $notification->priority,
                        'action_url' => $notification->action_url,
                    ],
                    tenantId:      $notification->tenant_id,
                    windowHours:   $notification->priority === 'critical' ? 0 : 1,
                    recipientType: 'super_admin',
                );
            }
        } catch (\Throwable $e) {
            Log::error("Failed to queue notification email for digest: " . $e->getMessage());
        }
    }

    private function sendSms(Notification $notification): void
    {
        try {
            // SMS stub — integrate Semaphore/Twilio here
            $smsText = "Referral Bunny: " . substr($notification->message, 0, 140);
            Log::info("SMS: [{$notification->priority}] {$smsText}");
        } catch (\Throwable $e) {
            Log::error("Failed to send SMS: " . $e->getMessage());
        }
    }
}
