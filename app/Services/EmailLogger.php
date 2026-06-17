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

        // Idempotency check for ALL emails (daily and one-off).
        // Prevents duplicate sends when a queued listener retries.
        $alreadyQueued = EmailLog::where('email_key', $dedupKey)
            ->whereIn('status', ['sent', 'pending', 'queued'])
            ->exists();
        if ($alreadyQueued) {
            return true;
        }

        try {
            $log = EmailLog::create([
                'email_key'      => $dedupKey,
                'recipient_email'=> $recipientEmail,
                'recipient_type' => $recipientType,
                'recipient_id'   => $recipientId,
                'tenant_id'      => $tenantId,
                'subject'        => $subject,
                'status'         => 'queued',
                'metadata'       => $metadata,
            ]);
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            // Concurrent worker already inserted this key — mail was already queued.
            return true;
        }

        if ($tenantId) {
            try {
                $tenant = \App\Models\Tenant::find($tenantId);
                $brand  = \App\Models\TenantBrandProfile::where('tenant_id', $tenantId)
                              ->where('status', 'published')->first();
                $safeHex = fn(?string $v) => preg_match('/^#[0-9A-Fa-f]{6}$/', (string)$v) ? $v : null;
                $mailable->with([
                    'emailBrandLogoUrl' => $brand?->logo_url ?? $tenant?->logo_url,
                    'emailBrandAccent'  => $safeHex($brand?->accent_color ?? $tenant?->accent_color),
                    'emailBrandName'    => $tenant?->program_name,
                ]);
            } catch (\Throwable) {
                // Never block email sending for brand resolution failures
            }
        }

        try {
            Mail::to($recipientEmail)->queue($mailable);
            $log->update(['status' => 'sent']);
            return true;
        } catch (\Throwable $e) {
            $log->update(['status' => 'failed', 'failed_at' => now(), 'error_message' => $e->getMessage()]);
            Log::warning("Email queue failed [{$emailKey}] to {$recipientEmail}: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Check if a daily email was already sent (or queued) today.
     */
    public static function sentToday(string $emailKey): bool
    {
        return EmailLog::where('email_key', $emailKey . '.' . now()->format('Y-m-d'))
            ->whereIn('status', ['sent', 'pending', 'queued'])
            ->exists();
    }
}
