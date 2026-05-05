<?php

namespace App\Services;

use App\Models\EmailLog;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EmailLogger
{
    /**
     * Send a Mailable and log it. Returns true on success.
     * Skips if email_key was already sent today (idempotency for scheduled emails).
     */
    public static function send(
        Mailable $mailable,
        string   $recipientEmail,
        string   $recipientType,
        string   $emailKey,
        string   $subject,
        ?string  $recipientId = null,
        ?string  $tenantId    = null,
        array    $metadata    = [],
        bool     $dailyDedup  = false,
    ): bool {
        $dedupKey = $dailyDedup ? $emailKey . '.' . now()->format('Y-m-d') : $emailKey;

        // Idempotency check for scheduled/daily emails
        if ($dailyDedup) {
            $alreadySent = EmailLog::where('email_key', $dedupKey)
                ->whereIn('status', ['sent', 'pending'])
                ->exists();
            if ($alreadySent) {
                return true;
            }
        }

        $log = EmailLog::create([
            'email_key'      => $dedupKey,
            'recipient_email'=> $recipientEmail,
            'recipient_type' => $recipientType,
            'recipient_id'   => $recipientId,
            'tenant_id'      => $tenantId,
            'subject'        => $subject,
            'status'         => 'pending',
            'metadata'       => $metadata,
        ]);

        try {
            Mail::send($mailable);
            $log->update(['status' => 'sent', 'sent_at' => now()]);
            return true;
        } catch (\Throwable $e) {
            $log->update(['status' => 'failed', 'failed_at' => now(), 'error_message' => $e->getMessage()]);
            Log::warning("Email failed [{$emailKey}] to {$recipientEmail}: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Check if a daily email was already sent today.
     */
    public static function sentToday(string $emailKey): bool
    {
        return EmailLog::where('email_key', $emailKey . '.' . now()->format('Y-m-d'))
            ->whereIn('status', ['sent', 'pending'])
            ->exists();
    }
}
