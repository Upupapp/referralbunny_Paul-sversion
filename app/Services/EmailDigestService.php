<?php

namespace App\Services;

use App\Mail\EmailDigestMail;
use App\Models\EmailDigest;
use App\Services\EmailLogger;
use Illuminate\Support\Facades\Log;

/**
 * Email Digest Batching Service
 *
 * Instead of sending one email per event, this service batches events of the
 * same topic for the same recipient within a configurable time window.
 *
 * Usage:
 *   app(EmailDigestService::class)->queue(
 *       email:      'reseller@example.com',
 *       name:       'Jane Referrer',
 *       topic:      'deal_created',            // machine key for dedup
 *       topicLabel: 'New Deal',                // human-readable for email subject
 *       item:       ['deal_name' => 'LGU Cebu', 'stage' => 'Introduction'],
 *       tenantId:   $tenantId,
 *       windowHours: 1,                        // wait up to 1h before sending
 *   );
 *
 * The scheduled command `php artisan email:send-digests` (runs every 30 min)
 * picks up due digests and sends one summary email per pending digest.
 */
class EmailDigestService
{
    /**
     * Queue an email item for digest delivery.
     * If a pending digest already exists for this recipient+topic within the window,
     * the item is appended to it. Otherwise a new digest is created.
     */
    public function queue(
        string  $email,
        string  $name,
        string  $topic,
        string  $topicLabel,
        array   $item,
        ?string $tenantId      = null,
        int     $windowHours   = 1,
        string  $recipientType = 'reseller',
    ): void {
        $email = strtolower(trim($email));
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return; // silently skip invalid addresses
        }

        // Look for an existing pending digest for the same recipient+topic
        $existing = EmailDigest::where('recipient_email', $email)
            ->where('topic', $topic)
            ->pending()
            ->where('scheduled_at', '>', now()) // still in the future
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->orderByDesc('created_at')
            ->first();

        if ($existing) {
            $items   = $existing->items ?? [];
            $items[] = $item;
            $existing->update(['items' => $items, 'updated_at' => now()]);
        } else {
            EmailDigest::create([
                'tenant_id'      => $tenantId,
                'recipient_email'=> $email,
                'recipient_name' => $name,
                'recipient_type' => $recipientType,
                'topic'          => $topic,
                'topic_label'    => $topicLabel,
                'items'          => [$item],
                'window_hours'   => $windowHours,
                'scheduled_at'   => now()->addHours($windowHours),
            ]);
        }
    }

    /**
     * Send all due digests. Called by the scheduled command.
     * Returns [sent, failed] counts.
     */
    public function sendDue(): array
    {
        $due    = EmailDigest::due()->orderBy('scheduled_at')->limit(200)->get();
        $sent   = 0;
        $failed = 0;

        foreach ($due as $digest) {
            try {
                EmailLogger::send(
                    mailable:       new EmailDigestMail($digest),
                    recipientEmail: $digest->recipient_email,
                    recipientType:  $digest->recipient_type ?? 'reseller',
                    emailKey:       'email_digest.' . $digest->id,
                    subject:        $digest->topic_label ?? 'Your activity summary',
                    tenantId:       $digest->tenant_id,
                );
                $digest->update(['sent_at' => now()]);
                $sent++;
            } catch (\Throwable $e) {
                $digest->increment('retry_count');
                if ($digest->retry_count >= 3) {
                    $digest->update(['failed_at' => now()]);
                    Log::error('EmailDigestService: digest permanently failed', [
                        'digest_id' => $digest->id,
                        'email'     => $digest->recipient_email,
                        'topic'     => $digest->topic,
                        'error'     => $e->getMessage(),
                    ]);
                } else {
                    // Push scheduled_at forward by 15 min for retry
                    $digest->update(['scheduled_at' => now()->addMinutes(15)]);
                    Log::warning('EmailDigestService: digest failed, will retry', [
                        'digest_id'   => $digest->id,
                        'retry_count' => $digest->retry_count,
                    ]);
                }
                $failed++;
            }
        }

        return [$sent, $failed];
    }
}
