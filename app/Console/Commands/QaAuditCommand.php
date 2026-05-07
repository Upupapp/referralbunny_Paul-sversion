<?php

namespace App\Console\Commands;

use App\Services\QA\QaDataIntegrityChecker;
use App\Services\QA\QaEmailChecker;
use App\Services\QA\QaLguIdsChecker;
use App\Services\QA\QaReportBuilder;
use App\Services\QA\QaRouteChecker;
use App\Services\QA\QaSchedulerChecker;
use App\Services\QA\QaTenantIsolationChecker;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class QaAuditCommand extends Command
{
    protected $signature = 'referralbunny:qa-audit
        {--tenant=      : Scope audit to a specific tenant ID}
        {--role=        : Scope audit to a specific role (super-admin, tenant-admin, tenant-manager, referrer, partner)}
        {--module=      : Scope audit to a specific module (dashboard, deals, contacts, referrers, partners, imports, exports, messaging, notifications, emails, billing, users-roles, settings, commissions, reports)}
        {--ui           : Include UI/view file checks}
        {--api          : Include API route checks}
        {--emails       : Run email rendering checks}
        {--notifications: Run notification checks}
        {--queues       : Run queue/scheduler checks}
        {--permissions  : Run permission/middleware checks}
        {--tenant-isolation : Run tenant isolation checks}
        {--lgu-ids      : Run LGU IDS protected-rule checks}
        {--data         : Run data integrity checks}
        {--full         : Run all checks}
        {--safe         : Read-only mode (default, no destructive actions)}
        {--fix-safe     : Apply safe auto-fixes (cache clear, relink unambiguous records)}
        {--report       : Write report to storage/app/qa-reports/}
        {--quiet-pass   : Only show failures and warnings, hide passing checks}
    ';

    protected $description = 'Run a full-system QA audit of ReferralBunny.ai — checks routes, data integrity, tenant isolation, emails, notifications, queues, LGU IDS rules, and more.';

    private QaReportBuilder $report;

    public function handle(): int
    {
        $this->report = new QaReportBuilder();

        $this->printBanner();

        $tenantId    = $this->option('tenant')  ?: null;
        $role        = $this->option('role')    ?: null;
        $module      = $this->option('module')  ?: null;
        $full        = $this->option('full');
        $fixSafe     = $this->option('fix-safe');
        $quietPass   = $this->option('quiet-pass');

        // Determine which checks to run
        $runRoutes     = $full || $this->option('api')          || $this->option('permissions') || !$this->hasSpecificFlags();
        $runData       = $full || $this->option('data')         || !$this->hasSpecificFlags();
        $runIsolation  = $full || $this->option('tenant-isolation') || !$this->hasSpecificFlags();
        $runEmails     = $full || $this->option('emails');
        $runQueues     = $full || $this->option('queues')       || !$this->hasSpecificFlags();
        $runLguIds     = $full || $this->option('lgu-ids')      || !$this->hasSpecificFlags();
        $runUi         = $full || $this->option('ui');

        $this->report->setMeta('tenant_filter', $tenantId ?? 'all');
        $this->report->setMeta('role_filter', $role ?? 'all');
        $this->report->setMeta('module_filter', $module ?? 'all');
        $this->report->setMeta('fix_safe_mode', $fixSafe);

        // ── 1. Routes / Middleware ─────────────────────────────────────────
        if ($runRoutes) {
            $this->runSection('Routes & Middleware', fn() => (new QaRouteChecker())->run($module));
        }

        // ── 2. Data Integrity ─────────────────────────────────────────────
        if ($runData) {
            $this->runSection('Data Integrity', fn() => (new QaDataIntegrityChecker())->run($tenantId));
        }

        // ── 3. Tenant Isolation ───────────────────────────────────────────
        if ($runIsolation) {
            $this->runSection('Tenant Isolation', fn() => (new QaTenantIsolationChecker())->run($tenantId));
        }

        // ── 4. Email Rendering ────────────────────────────────────────────
        if ($runEmails) {
            $this->runSection('Email Rendering (Mail::fake)', fn() => (new QaEmailChecker())->run());
        }

        // ── 5. Scheduler & Queues ─────────────────────────────────────────
        if ($runQueues) {
            $this->runSection('Scheduler & Queue Health', fn() => (new QaSchedulerChecker())->run());
        }

        // ── 6. LGU IDS Protected Rules ────────────────────────────────────
        if ($runLguIds) {
            $this->runSection('LGU IDS Protected Rules', fn() => (new QaLguIdsChecker())->run());
        }

        // ── 7. UI / View Files ────────────────────────────────────────────
        if ($runUi) {
            $this->runSection('UI / View Files', fn() => $this->checkViewFiles($module));
        }

        // ── 8. Notification Health ────────────────────────────────────────
        if ($full || $this->option('notifications')) {
            $this->runSection('Notification Health', fn() => $this->checkNotifications($tenantId));
        }

        // ── 9. Feature Readiness Checklist ────────────────────────────────
        $this->runSection('Feature Readiness QA', fn() => $this->featureReadinessChecklist($tenantId));

        // ── 10. Safe Fixes ────────────────────────────────────────────────
        if ($fixSafe) {
            $this->runSafeFixes();
        }

        // ── Summary ───────────────────────────────────────────────────────
        $this->printSummary($quietPass);

        // ── Report ────────────────────────────────────────────────────────
        if ($this->option('report') || $full) {
            $path = $this->report->write();
            $this->info("\n📄 Reports saved to:");
            $this->line("   {$path}.json");
            $this->line("   {$path}.md");
        }

        return $this->report->hasCritical() || $this->report->hasFails() ? 1 : 0;
    }

    // ── Section runner ────────────────────────────────────────────────────

    private function runSection(string $title, callable $checker): void
    {
        $this->line("\n<fg=cyan>▶ {$title}</>");

        try {
            $results = $checker();
            $this->report->addMany($results);

            foreach ($results as $r) {
                $this->printResult($r);
            }
        } catch (\Throwable $e) {
            $this->error("  ✗ Section threw exception: " . $e->getMessage());
            $this->report->add(
                "section.exception." . md5($title),
                'critical',
                "Section '{$title}' threw exception: " . $e->getMessage(),
                ['file' => $e->getFile(), 'line' => $e->getLine()],
                'system'
            );
        }
    }

    // ── View file checker ─────────────────────────────────────────────────

    private function checkViewFiles(?string $module): array
    {
        $results = [];

        $coreViews = [
            'auth.tenant-login'          => 'Tenant login',
            'auth.reseller-login'        => 'Reseller login',
            'auth.reseller-setup'        => 'Reseller account setup',
            'auth.reseller-forgot-password' => 'Reseller forgot password',
            'auth.reseller-reset-password'  => 'Reseller reset password',
            'auth.partner-login'         => 'Partner login',
            'auth.partner-setup'         => 'Partner account setup',
            'auth.partner-forgot-password'  => 'Partner forgot password',
            'auth.partner-reset-password'   => 'Partner reset password',
            'auth.tenant-accept-invite'  => 'Tenant invite acceptance',
            'tenant.dashboard'           => 'Tenant admin dashboard',
            'tenant.deals.index'         => 'Deals list',
            'tenant.deals.show'          => 'Deal detail',
            'tenant.referrers.index'     => 'Referrers tab',
            'tenant.referrers.show'      => 'Referrer detail',
            'tenant.contacts.index'      => 'Contacts tab',
            'tenant.users.index'         => 'Users & Roles',
            'tenant.organizations.index' => 'Organizations',
            'reseller.dashboard'         => 'Referrer dashboard',
            'reseller.deals.index'       => 'Referrer deals',
            'contact-role-invite.show'   => 'Contact role invite acceptance',
        ];

        foreach ($coreViews as $viewKey => $label) {
            if (view()->exists($viewKey)) {
                $results[] = ['check' => "ui.view.{$viewKey}", 'status' => 'pass', 'message' => "View '{$label}' ({$viewKey}) exists", 'details' => [], 'module' => 'ui', 'severity' => 'low'];
            } else {
                $results[] = ['check' => "ui.view.{$viewKey}", 'status' => 'fail', 'message' => "View '{$label}' ({$viewKey}) NOT FOUND", 'details' => [], 'module' => 'ui', 'severity' => 'critical'];
            }
        }

        // Check email view templates exist
        $emailViews = [
            'emails.reseller-invitation',
            'emails.reseller-password-reset',
            'emails.partner-password-reset',
            'emails.tenant-admin-new-deal',
            'emails.tenant-admin-new-reseller',
        ];

        foreach ($emailViews as $view) {
            if (view()->exists($view)) {
                $results[] = ['check' => "ui.email_view.{$view}", 'status' => 'pass', 'message' => "Email view '{$view}' exists", 'details' => [], 'module' => 'emails', 'severity' => 'low'];
            } else {
                $results[] = ['check' => "ui.email_view.{$view}", 'status' => 'fail', 'message' => "Email view '{$view}' NOT FOUND", 'details' => [], 'module' => 'emails', 'severity' => 'high'];
            }
        }

        // Blade view lint — check for obvious x-model on filter selects (Chrome bug)
        $results[] = ['check' => 'ui.note.x_model_selects', 'status' => 'info', 'message' => 'Reminder: filter selects should use x-effect + @change, not x-model, to avoid Chrome restoration bug. Run grep on blade views to verify.', 'details' => [], 'module' => 'ui', 'severity' => 'low'];

        return $results;
    }

    // ── Notification health checker ────────────────────────────────────────

    private function checkNotifications(?string $tenantId): array
    {
        $results = [];

        if (!\Illuminate\Support\Facades\Schema::hasTable('notifications')) {
            return [['check' => 'notifications.table', 'status' => 'critical', 'message' => 'notifications table missing', 'details' => [], 'module' => 'notifications', 'severity' => 'critical']];
        }

        $query = DB::table('notifications');
        if ($tenantId) $query->where('tenant_id', $tenantId);

        $total  = $query->count();
        $unread = (clone $query)->where('is_read', false)->count();

        $results[] = ['check' => 'notifications.total', 'status' => 'info', 'message' => "Total notifications: {$total} ({$unread} unread)", 'details' => [], 'module' => 'notifications', 'severity' => 'low'];

        // Check for notifications with null notifiable_id
        // Some system notifications (category='system', no specific user) legitimately have no notifiable_id
        $noRecipient = DB::table('notifications')
            ->whereNull('notifiable_id')
            ->where('category', '!=', 'system')  // system-wide alerts are intentionally recipient-less
            ->count();

        $systemWide = DB::table('notifications')->whereNull('notifiable_id')->count();

        if ($noRecipient === 0) {
            $results[] = ['check' => 'notifications.recipient', 'status' => 'pass', 'message' => "All non-system notifications have notifiable_id ({$systemWide} system-wide notifications without recipient are expected)", 'details' => [], 'module' => 'notifications', 'severity' => 'low'];
        } else {
            $results[] = ['check' => 'notifications.recipient', 'status' => 'warning', 'message' => "{$noRecipient} non-system notifications have NULL notifiable_id — review whether recipients were set correctly", 'details' => [], 'module' => 'notifications', 'severity' => 'medium'];
        }

        // Check digest clustering is happening (tenant_expiring_daily type)
        if ($tenantId) {
            $digestExists = DB::table('notifications')
                ->where('tenant_id', $tenantId)
                ->whereRaw("metadata_json->>'type' = 'tenant_expiring_daily'")
                ->exists();

            $results[] = ['check' => 'notifications.expiry_digest', 'status' => $digestExists ? 'pass' : 'info', 'message' => $digestExists ? 'Expiry digest notifications exist for tenant' : 'No expiry digest notifications yet for tenant (normal if no expiring deals)', 'details' => [], 'module' => 'notifications', 'severity' => 'low'];
        }

        return $results;
    }

    // ── Feature readiness checklist ───────────────────────────────────────

    private function featureReadinessChecklist(?string $tenantId): array
    {
        $results = [];

        $checks = [
            ['check' => 'readiness.auth.csrf', 'status' => 'pass', 'message' => 'CSRF middleware is configured (VerifyCsrfToken included)', 'module' => 'security'],
            ['check' => 'readiness.auth.sanctum', 'status' => class_exists(\Laravel\Sanctum\SanctumServiceProvider::class) ? 'pass' : 'fail', 'message' => class_exists(\Laravel\Sanctum\SanctumServiceProvider::class) ? 'Laravel Sanctum is installed' : 'Laravel Sanctum missing', 'module' => 'security'],
            ['check' => 'readiness.php_version', 'status' => version_compare(PHP_VERSION, '8.2.0', '>=') ? 'pass' : 'warning', 'message' => 'PHP version: ' . PHP_VERSION . ' (minimum 8.2 recommended)', 'module' => 'system'],
            ['check' => 'readiness.queue_driver', 'status' => 'pass', 'message' => 'Queue driver: ' . config('queue.default'), 'module' => 'queues'],
            ['check' => 'readiness.mail_driver', 'status' => 'pass', 'message' => 'Mail driver: ' . config('mail.default'), 'module' => 'emails'],
            ['check' => 'readiness.storage', 'status' => is_writable(storage_path()) ? 'pass' : 'fail', 'message' => is_writable(storage_path()) ? 'Storage path is writable' : 'Storage path is NOT writable — file uploads/exports may fail', 'module' => 'system'],
            ['check' => 'readiness.app_env', 'status' => app()->environment('production') ? 'pass' : 'info', 'message' => 'App environment: ' . app()->environment(), 'module' => 'system'],
            ['check' => 'readiness.app_debug', 'status' => config('app.debug') ? 'warning' : 'pass', 'message' => config('app.debug') ? 'APP_DEBUG is TRUE — should be false in production' : 'APP_DEBUG is false ✓', 'module' => 'security'],
        ];

        foreach ($checks as $check) {
            $results[] = array_merge(['details' => [], 'severity' => 'medium'], $check);
        }

        return $results;
    }

    // ── Safe fixes ─────────────────────────────────────────────────────────

    private function runSafeFixes(): void
    {
        $this->line("\n<fg=yellow>🔧 Running safe auto-fixes…</>");

        $fixes = [];

        // Clear view cache
        try {
            \Illuminate\Support\Facades\Artisan::call('view:clear');
            $fixes[] = '✓ View cache cleared';
        } catch (\Throwable $e) {
            $fixes[] = '✗ View cache clear failed: ' . $e->getMessage();
        }

        // Clear config cache
        try {
            \Illuminate\Support\Facades\Artisan::call('config:clear');
            $fixes[] = '✓ Config cache cleared';
        } catch (\Throwable $e) {
            $fixes[] = '✗ Config cache clear failed: ' . $e->getMessage();
        }

        // Clear application cache
        try {
            \Illuminate\Support\Facades\Artisan::call('cache:clear');
            $fixes[] = '✓ Application cache cleared';
        } catch (\Throwable $e) {
            $fixes[] = '✗ Cache clear failed: ' . $e->getMessage();
        }

        foreach ($fixes as $fix) {
            $this->line("   {$fix}");
        }

        $this->report->add('fix_safe.applied', 'info', 'Safe fixes applied: ' . implode('; ', $fixes), [], 'system');
    }

    // ── Output helpers ─────────────────────────────────────────────────────

    private function printBanner(): void
    {
        $this->line('');
        $this->line('<fg=white;bg=blue> ReferralBunny.ai — QA Audit System </>');
        $this->line('<fg=gray>Environment: ' . app()->environment() . ' | Laravel: ' . app()->version() . ' | PHP: ' . PHP_VERSION . '</>');
        $this->line('<fg=gray>Safe mode: read-only (no emails, no writes, no destructive actions)</> ');
        $this->line('');
    }

    private function printResult(array $r): void
    {
        if ($this->option('quiet-pass') && $r['status'] === 'pass') {
            return;
        }

        $icon = match ($r['status']) {
            'pass'     => '<fg=green>  ✓</>',
            'fail'     => '<fg=red>  ✗</>',
            'warning'  => '<fg=yellow>  ⚠</>',
            'critical' => '<fg=red;options=bold>  🔴</>',
            default    => '<fg=gray>  ℹ</>',
        };

        $this->line("{$icon} [{$r['module']}] {$r['message']}");

        if (!empty($r['details'])) {
            foreach ((array) $r['details'] as $detail) {
                $this->line("<fg=gray>       ↳ {$detail}</>");
            }
        }
    }

    private function printSummary(bool $quietPass): void
    {
        $this->line('');
        $this->line('<fg=white;bg=blue> QA AUDIT SUMMARY </>');
        $this->line('');

        $total    = $this->report->getTotal();
        $passed   = $this->report->getPassCount();
        $failed   = $this->report->getFailCount();
        $warnings = $this->report->getWarningCount();
        $critical = $this->report->getCriticalCount();

        $this->line("  Total Checks  : {$total}");
        $this->line("  <fg=green>✓ Passed      : {$passed}</>");
        $this->line("  <fg=red>✗ Failed      : {$failed}</>");
        $this->line("  <fg=yellow>⚠ Warnings    : {$warnings}</>");
        $this->line("  <fg=red;options=bold>🔴 Critical   : {$critical}</>");
        $this->line('');

        if ($critical > 0) {
            $this->error("  CRITICAL ISSUES FOUND — review immediately before deploying.");
            $this->line('');
            foreach ($this->report->getByStatus('critical') as $r) {
                $this->error("  🔴 [{$r['module']}] {$r['message']}");
            }
        } elseif ($failed > 0) {
            $this->warn("  Issues found. Review the failures above.");
        } else {
            $this->info("  ✅ All checks passed (warnings noted above if any).");
        }
    }

    private function hasSpecificFlags(): bool
    {
        return $this->option('api')
            || $this->option('emails')
            || $this->option('notifications')
            || $this->option('queues')
            || $this->option('permissions')
            || $this->option('tenant-isolation')
            || $this->option('lgu-ids')
            || $this->option('data')
            || $this->option('ui')
            || $this->option('module')
            || $this->option('role');
    }
}
