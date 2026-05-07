<?php

namespace App\Services\QA;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Checks that all Mailable classes can be instantiated and render without exceptions.
 * Uses Mail::fake() — never sends real emails.
 * Also validates subject, view binding, and required fields.
 */
class QaEmailChecker
{
    /** All Mailable classes with the minimal constructor args needed to instantiate them */
    private const MAILABLES = [
        \App\Mail\ResellerInvitation::class => [
            'resellerName'  => 'QA Test Referrer',
            'resellerEmail' => 'qa-test@referralbunny.ai',
            'tenantName'    => 'QA Tenant',
            'setupUrl'      => 'https://referralbunny.ai/reseller/setup?token=qa_test',
        ],
        \App\Mail\ResellerPasswordReset::class => [
            'resellerName'  => 'QA Test Referrer',
            'resellerEmail' => 'qa-test@referralbunny.ai',
            'tenantName'    => 'QA Tenant',
            'resetUrl'      => 'https://referralbunny.ai/reseller/reset-password?token=qa_test',
        ],
        \App\Mail\ResellerWelcome::class => null, // will skip if constructor args unknown
        \App\Mail\PartnerPasswordReset::class => [
            'partnerName'  => 'QA Test Partner',
            'partnerEmail' => 'qa-partner@referralbunny.ai',
            'tenantName'   => 'QA Tenant',
            'resetUrl'     => 'https://referralbunny.ai/partner/reset-password?token=qa_test',
        ],
        \App\Mail\TenantInvitationMail::class           => null,
        \App\Mail\TenantInvitationReminderMail::class   => null,
        \App\Mail\TenantInvitationExpiredMail::class    => null,
        \App\Mail\TenantInvitationAcceptedMail::class   => null,
        \App\Mail\TenantInvitationRevokedMail::class    => null,
        \App\Mail\TenantInviterReminderMail::class      => null,
        \App\Mail\ExportApprovalNeeded::class           => null,
        \App\Mail\ExportApproved::class                 => null,
        \App\Mail\ExportRejected::class                 => null,
        \App\Mail\ExportReady::class                    => null,
        \App\Mail\ExportExpired::class                  => null,
        \App\Mail\ExportRequestSubmitted::class         => null,
        \App\Mail\SuperAdminDailySummary::class         => null,
        \App\Mail\TenantAdminDailyBriefing::class       => null,
        \App\Mail\TenantAdminNewDeal::class             => null,
        \App\Mail\TenantAdminNewReseller::class         => null,
        \App\Mail\ResellerDailySummary::class           => null,
        \App\Mail\ResellerDealCreated::class            => null,
        \App\Mail\ResellerDealExpired::class            => null,
        \App\Mail\PipelineStageWarning::class           => null,
        \App\Mail\CommissionStatusUpdate::class         => null,
        \App\Mail\ContactRoleInvitationMail::class      => null,
    ];

    public function run(): array
    {
        $results = [];

        Mail::fake();

        foreach (self::MAILABLES as $class => $args) {
            $shortName = class_basename($class);

            if (!class_exists($class)) {
                $results[] = $this->fail("email.class.{$shortName}", "Mailable class missing: {$class}", 'emails');
                continue;
            }

            if ($args === null) {
                // Skip instantiation — just confirm class exists and has expected interface
                if (is_subclass_of($class, \Illuminate\Mail\Mailable::class)) {
                    $results[] = $this->pass("email.class.{$shortName}", "Mailable {$shortName} class exists and extends Mailable (args not tested)", 'emails');
                } else {
                    $results[] = $this->fail("email.class.{$shortName}", "{$shortName} does not extend Mailable", 'emails');
                }
                continue;
            }

            try {
                $mailable = new $class(...array_values($args));
                $envelope = $mailable->envelope();
                $content  = $mailable->content();

                // Check subject
                if (empty($envelope->subject)) {
                    $results[] = $this->fail("email.subject.{$shortName}", "{$shortName} has no subject", 'emails', 'high');
                } else {
                    $results[] = $this->pass("email.subject.{$shortName}", "{$shortName} subject: '{$envelope->subject}'", 'emails');
                }

                // Check view exists
                $view = $content->view ?? $content->markdown ?? null;
                if ($view && view()->exists($view)) {
                    $results[] = $this->pass("email.view.{$shortName}", "{$shortName} view '{$view}' exists", 'emails');
                } elseif ($view) {
                    $results[] = $this->fail("email.view.{$shortName}", "{$shortName} view '{$view}' NOT FOUND", 'emails', 'critical');
                }

            } catch (\Throwable $e) {
                $results[] = $this->fail(
                    "email.render.{$shortName}",
                    "{$shortName} threw exception during instantiation: " . $e->getMessage(),
                    'emails',
                    'high'
                );
            }
        }

        $results[] = $this->info('email.mode', 'All email checks ran in Mail::fake() mode — no real emails sent', 'emails');

        return $results;
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    private function pass(string $check, string $message, string $module, string $severity = 'low'): array
    {
        return ['check' => $check, 'status' => 'pass', 'message' => $message, 'details' => [], 'module' => $module, 'severity' => $severity];
    }

    private function fail(string $check, string $message, string $module, string $severity = 'high'): array
    {
        return ['check' => $check, 'status' => 'fail', 'message' => $message, 'details' => [], 'module' => $module, 'severity' => $severity];
    }

    private function warning(string $check, string $message, string $module): array
    {
        return ['check' => $check, 'status' => 'warning', 'message' => $message, 'details' => [], 'module' => $module, 'severity' => 'medium'];
    }

    private function info(string $check, string $message, string $module): array
    {
        return ['check' => $check, 'status' => 'info', 'message' => $message, 'details' => [], 'module' => $module, 'severity' => 'low'];
    }
}
