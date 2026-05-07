<?php

namespace App\Services\QA;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Checks scheduler registration, queue health, and critical command availability.
 */
class QaSchedulerChecker
{
    /** Commands that MUST be registered and runnable */
    private const REQUIRED_COMMANDS = [
        'leads:expire'             => 'Deal expiry — marks expired/expiring deals daily',
        'leads:notify-expiring'    => 'Expiring deal notifications — daily digest',
        'invitations:send-reminders' => 'Invitation reminders — hourly',
        'metrics:calculate'        => 'Tenant metrics recalculation — daily',
        'exports:cleanup-expired'  => 'Export file cleanup — daily',
    ];

    /** Commands that should ideally be registered */
    private const RECOMMENDED_COMMANDS = [
        'leads:check-pipeline-limits'  => 'Pipeline stage limit enforcement',
        'notifications:escalate'       => 'Notification escalation',
        'search:reindex'               => 'Search index rebuild',
        'billing:process-trials'       => 'Trial expiry processing',
        'billing:retry-payments'       => 'Payment retry',
    ];

    public function run(): array
    {
        $results = [];

        $results = array_merge($results, $this->checkCommandsExist());
        $results = array_merge($results, $this->checkSchedulerRegistration());
        $results = array_merge($results, $this->checkRecentFailedJobs());

        return $results;
    }

    private function checkCommandsExist(): array
    {
        $results = [];

        // Get all registered artisan commands
        $all = array_keys(Artisan::all());

        foreach (self::REQUIRED_COMMANDS as $cmd => $description) {
            if (in_array($cmd, $all)) {
                $results[] = $this->pass("scheduler.command.{$cmd}", "Required command '{$cmd}' is registered: {$description}", 'scheduler');
            } else {
                $results[] = $this->critical("scheduler.command.{$cmd}", "REQUIRED command '{$cmd}' is NOT registered: {$description}", 'scheduler');
            }
        }

        foreach (self::RECOMMENDED_COMMANDS as $cmd => $description) {
            if (in_array($cmd, $all)) {
                $results[] = $this->pass("scheduler.recommended.{$cmd}", "Recommended command '{$cmd}' is registered", 'scheduler');
            } else {
                $results[] = $this->warning("scheduler.recommended.{$cmd}", "Recommended command '{$cmd}' not found: {$description}", 'scheduler');
            }
        }

        // Check new diagnostic commands
        $diagnostics = [
            'referralbunny:diagnose-partner-splits',
            'referralbunny:diagnose-extension-requests',
            'referralbunny:diagnose-expiry-digests',
            'referralbunny:diagnose-multirole-referrers',
            'referralbunny:diagnose-referrer-invite-dedup',
            'referralbunny:qa-audit',
        ];

        foreach ($diagnostics as $cmd) {
            if (in_array($cmd, $all)) {
                $results[] = $this->pass("scheduler.diagnostic.{$cmd}", "Diagnostic command '{$cmd}' registered", 'scheduler');
            } else {
                $results[] = $this->warning("scheduler.diagnostic.{$cmd}", "Diagnostic command '{$cmd}' not yet registered", 'scheduler');
            }
        }

        return $results;
    }

    private function checkSchedulerRegistration(): array
    {
        $results = [];

        // Check console.php exists and has schedule definitions
        $consolePath = base_path('routes/console.php');

        if (!file_exists($consolePath)) {
            $results[] = $this->warning('scheduler.console_php', 'routes/console.php not found — scheduler may not be configured', 'scheduler');
            return $results;
        }

        $content = file_get_contents($consolePath);

        $expectedScheduled = [
            'leads:expire'             => 'leads:expire',
            'leads:notify-expiring'    => 'leads:notify-expiring',
            'invitations:send-reminders'=> 'invitations:send-reminders',
        ];

        foreach ($expectedScheduled as $check => $search) {
            if (str_contains($content, $search)) {
                $results[] = $this->pass("scheduler.registered.{$check}", "'{$search}' is scheduled in console.php", 'scheduler');
            } else {
                $results[] = $this->fail("scheduler.registered.{$check}", "'{$search}' NOT found in console.php schedule — daily notifications may not fire", 'scheduler', 'high');
            }
        }

        return $results;
    }

    private function checkRecentFailedJobs(): array
    {
        $results = [];

        if (!Schema::hasTable('failed_jobs')) {
            return [];
        }

        // Jobs failed in last 24 hours
        $recentFailed = DB::table('failed_jobs')
            ->where('failed_at', '>', now()->subHours(24))
            ->count();

        if ($recentFailed === 0) {
            $results[] = $this->pass('scheduler.failed_jobs_24h', 'No failed jobs in last 24 hours', 'scheduler');
        } else {
            $topFailed = DB::table('failed_jobs')
                ->where('failed_at', '>', now()->subHours(24))
                ->orderByDesc('failed_at')
                ->limit(5)
                ->get(['queue', 'failed_at', 'exception']);

            $details = $topFailed->map(fn($j) => $j->queue . ' @ ' . $j->failed_at . ': ' . substr($j->exception, 0, 80))->toArray();

            $results[] = $this->fail('scheduler.failed_jobs_24h', "{$recentFailed} jobs failed in last 24 hours", 'scheduler', 'high', $details);
        }

        return $results;
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    private function pass(string $check, string $message, string $module): array
    {
        return ['check' => $check, 'status' => 'pass', 'message' => $message, 'details' => [], 'module' => $module, 'severity' => 'low'];
    }

    private function fail(string $check, string $message, string $module, string $severity = 'high', array $details = []): array
    {
        return ['check' => $check, 'status' => 'fail', 'message' => $message, 'details' => $details, 'module' => $module, 'severity' => $severity];
    }

    private function warning(string $check, string $message, string $module): array
    {
        return ['check' => $check, 'status' => 'warning', 'message' => $message, 'details' => [], 'module' => $module, 'severity' => 'medium'];
    }

    private function critical(string $check, string $message, string $module): array
    {
        return ['check' => $check, 'status' => 'critical', 'message' => $message, 'details' => [], 'module' => $module, 'severity' => 'critical'];
    }
}
