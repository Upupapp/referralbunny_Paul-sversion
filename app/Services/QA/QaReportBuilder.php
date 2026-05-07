<?php

namespace App\Services\QA;

use Illuminate\Support\Facades\Storage;

/**
 * Collects QA check results and generates JSON + Markdown reports.
 * Stores reports in storage/app/qa-reports/.
 */
class QaReportBuilder
{
    private array $results   = [];
    private array $meta      = [];
    private int   $passed    = 0;
    private int   $failed    = 0;
    private int   $warnings  = 0;
    private int   $critical  = 0;

    public function __construct()
    {
        $this->meta = [
            'audit_started_at' => now()->toIso8601String(),
            'environment'      => app()->environment(),
            'laravel_version'  => app()->version(),
            'php_version'      => phpversion(),
            'git_commit'       => $this->getGitCommit(),
            'git_branch'       => $this->getGitBranch(),
        ];
    }

    /**
     * Add a check result.
     * Status: pass | fail | warning | critical | info
     */
    public function add(
        string  $check,
        string  $status,
        string  $message,
        array   $details  = [],
        string  $module   = 'general',
        string  $severity = 'medium'
    ): self {
        $this->results[] = compact('check', 'status', 'message', 'details', 'module', 'severity');

        match ($status) {
            'pass'     => $this->passed++,
            'fail'     => $this->failed++,
            'warning'  => $this->warnings++,
            'critical' => $this->critical++,
            default    => null,
        };

        return $this;
    }

    /** Add multiple results from a checker array. */
    public function addMany(array $results): self
    {
        foreach ($results as $r) {
            $this->add(
                $r['check']    ?? 'unknown',
                $r['status']   ?? 'info',
                $r['message']  ?? '',
                $r['details']  ?? [],
                $r['module']   ?? 'general',
                $r['severity'] ?? 'medium',
            );
        }
        return $this;
    }

    public function setMeta(string $key, mixed $value): self
    {
        $this->meta[$key] = $value;
        return $this;
    }

    public function getCriticalCount(): int  { return $this->critical; }
    public function getFailCount(): int      { return $this->failed;   }
    public function getWarningCount(): int   { return $this->warnings; }
    public function getPassCount(): int      { return $this->passed;   }
    public function getTotal(): int          { return count($this->results); }
    public function getResults(): array      { return $this->results; }

    public function hasCritical(): bool { return $this->critical > 0; }
    public function hasFails(): bool    { return $this->failed > 0;   }

    /** Get results filtered by status */
    public function getByStatus(string $status): array
    {
        return array_values(array_filter($this->results, fn($r) => $r['status'] === $status));
    }

    /** Write both JSON and Markdown reports. Returns the base path. */
    public function write(): string
    {
        $ts   = now()->format('Y-m-d_H-i-s');
        $env  = app()->environment();
        $base = "qa-reports/qa-audit-{$env}-{$ts}";

        Storage::put("{$base}.json", $this->toJson());
        Storage::put("{$base}.md",   $this->toMarkdown());

        return storage_path("app/{$base}");
    }

    public function toJson(): string
    {
        return json_encode([
            'meta'     => array_merge($this->meta, [
                'audit_completed_at' => now()->toIso8601String(),
                'total'    => $this->getTotal(),
                'passed'   => $this->passed,
                'failed'   => $this->failed,
                'warnings' => $this->warnings,
                'critical' => $this->critical,
            ]),
            'results'  => $this->results,
            'summary'  => $this->buildSummaryArray(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    public function toMarkdown(): string
    {
        $meta    = $this->meta;
        $md      = [];
        $md[]    = "# ReferralBunny.ai QA Audit Report";
        $md[]    = "";
        $md[]    = "| Field | Value |";
        $md[]    = "|-------|-------|";
        $md[]    = "| Date  | " . now()->format('Y-m-d H:i:s') . " |";
        $md[]    = "| Env   | " . app()->environment() . " |";
        $md[]    = "| Laravel | " . app()->version() . " |";
        $md[]    = "| Git Commit | " . ($meta['git_commit'] ?? 'n/a') . " |";
        $md[]    = "| Git Branch | " . ($meta['git_branch'] ?? 'n/a') . " |";
        $md[]    = "";
        $md[]    = "## Summary";
        $md[]    = "";
        $md[]    = "| Status | Count |";
        $md[]    = "|--------|-------|";
        $md[]    = "| ✅ Passed   | {$this->passed} |";
        $md[]    = "| ❌ Failed   | {$this->failed} |";
        $md[]    = "| ⚠️ Warning  | {$this->warnings} |";
        $md[]    = "| 🔴 Critical | {$this->critical} |";
        $md[]    = "| Total       | " . $this->getTotal() . " |";
        $md[]    = "";

        foreach (['critical', 'fail', 'warning', 'pass', 'info'] as $status) {
            $items = $this->getByStatus($status);
            if (empty($items)) continue;

            $icon  = match($status) {
                'critical' => '🔴',
                'fail'     => '❌',
                'warning'  => '⚠️',
                'pass'     => '✅',
                default    => 'ℹ️',
            };

            $label = ucfirst($status);
            $md[]  = "## {$icon} {$label} ({$count})" . str_replace('{$count}', count($items), '');
            $md[]  = "## {$icon} {$label} (" . count($items) . ")";
            $md[]  = "";
            $md[]  = "| Module | Check | Message |";
            $md[]  = "|--------|-------|---------|";

            foreach ($items as $r) {
                $det = !empty($r['details']) ? ' — ' . implode(', ', array_map(fn($v, $k) => "{$k}: {$v}", $r['details'], array_keys($r['details']))) : '';
                $md[] = "| {$r['module']} | `{$r['check']}` | {$r['message']}{$det} |";
            }
            $md[] = "";
        }

        $md[] = "---";
        $md[] = "_Generated by `php artisan referralbunny:qa-audit` on " . now()->format('Y-m-d H:i:s') . "_";

        return implode("\n", $md);
    }

    // ── Private helpers ────────────────────────────────────────────────────

    private function buildSummaryArray(): array
    {
        $byModule = [];
        foreach ($this->results as $r) {
            $m = $r['module'];
            $byModule[$m] = $byModule[$m] ?? ['pass' => 0, 'fail' => 0, 'warning' => 0, 'critical' => 0];
            $byModule[$m][$r['status']] = ($byModule[$m][$r['status']] ?? 0) + 1;
        }
        return $byModule;
    }

    private function getGitCommit(): string
    {
        try {
            return trim(shell_exec('git rev-parse --short HEAD 2>/dev/null') ?? 'n/a');
        } catch (\Throwable) {
            return 'n/a';
        }
    }

    private function getGitBranch(): string
    {
        try {
            return trim(shell_exec('git rev-parse --abbrev-ref HEAD 2>/dev/null') ?? 'n/a');
        } catch (\Throwable) {
            return 'n/a';
        }
    }
}
