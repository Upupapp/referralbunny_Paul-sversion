<?php

namespace App\Console\Commands;

use App\Services\Stitch\ReportWriter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * STITCH release gate — orchestrates lint -> fallbacks -> contracts ->
 * map(/) -> report into a single run, then exits non-zero if the aggregated
 * verdict is BLOCK RELEASE at the --fail-on threshold.
 *
 * Covers the static/guest-surface portion of spec Section AA's block
 * conditions (raw 500s, dead ends, missing fallbacks, redirect loops,
 * broken links/routes on the guest surface). Role/tenant/runtime block
 * conditions are Phase 2.
 */
class StitchReleaseGateCommand extends Command
{
    protected $signature = 'stitch:release-gate {--fail-on=P0} {--run-id=} {--json} {--markdown} {--ci}';

    protected $description = 'Run the full STITCH static suite (lint, fallbacks, contracts, map, report) and gate release on the severity verdict';

    private const LEVELS = ['P0', 'P1', 'P2', 'P3'];

    public function handle(): int
    {
        $failOn = strtoupper((string) $this->option('fail-on'));

        if (! in_array($failOn, self::LEVELS, true)) {
            $this->error('Invalid --fail-on='.$this->option('fail-on').'. Expected one of: '.implode(', ', self::LEVELS).'.');

            return self::INVALID;
        }

        $writer = new ReportWriter($this->option('run-id') ?: null);

        foreach (['stitch:lint', 'stitch:fallbacks', 'stitch:contracts', 'stitch:map'] as $command) {
            $this->callSilently($command, ['--run-id' => $writer->runId]);
        }

        $this->callSilently('stitch:report', ['runId' => $writer->runId]);

        $report = $writer->readJson('report.json');
        $counts = $report['counts'];
        $verdict = $writer->verdict($counts, $failOn);

        if ($this->option('json')) {
            $this->line(json_encode([
                'run_id' => $writer->runId,
                'fail_on' => $failOn,
                'verdict' => $verdict,
                'counts' => $counts,
                'sections' => $report['sections'],
                'landing' => $report['landing'],
                'summary' => $writer->path('summary.md'),
                'fix_plan' => $writer->path('fix-plan.md'),
                'report' => $writer->path('report.json'),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } elseif ($this->option('markdown')) {
            $this->line("**Release gate verdict (--fail-on={$failOn}):** {$verdict}");
            $this->line('');
            $this->line(File::get($writer->path('summary.md')));
        } elseif ($this->option('ci')) {
            $this->renderCi($writer, $counts, $verdict, $failOn);
        } else {
            $this->renderTable($writer, $report, $counts, $verdict, $failOn);
        }

        return $verdict === 'BLOCK RELEASE' ? self::FAILURE : self::SUCCESS;
    }

    private function renderCi(ReportWriter $writer, array $counts, string $verdict, string $failOn): void
    {
        $this->line(sprintf(
            'STITCH release-gate: %s (fail-on=%s) P0=%d P1=%d P2=%d P3=%d run=%s',
            $verdict, $failOn, $counts['P0'], $counts['P1'], $counts['P2'], $counts['P3'], $writer->runId
        ));
        $this->line('fix-plan: '.$writer->path('fix-plan.md'));
    }

    private function renderTable(ReportWriter $writer, array $report, array $counts, string $verdict, string $failOn): void
    {
        $this->info("STITCH release gate — run {$writer->runId}");
        $this->line("Fail on: {$failOn}");
        $this->line("Verdict: {$verdict}");
        $this->line("P0: {$counts['P0']}  P1: {$counts['P1']}  P2: {$counts['P2']}  P3: {$counts['P3']}");
        $this->newLine();

        foreach ($report['sections'] as $name => $section) {
            if ($section === null) {
                $this->line("  {$name}: not run");

                continue;
            }

            $c = $section['counts'];
            $this->line("  {$name}: P0={$c['P0']} P1={$c['P1']} P2={$c['P2']} P3={$c['P3']}");
        }

        $landing = $report['landing'];
        $this->line($landing['exit'] === 0
            ? '  landing: all checks passed'
            : "  landing: {$landing['failures']} check(s) failed");

        $this->newLine();
        $this->line('Summary: '.$writer->path('summary.md'));
        $this->line('Fix plan: '.$writer->path('fix-plan.md'));

        if ($verdict === 'BLOCK RELEASE') {
            $this->error('BLOCK RELEASE — see fix-plan.md for required fixes.');
        } elseif ($verdict === 'PASS WITH WARNINGS') {
            $this->warn('PASS WITH WARNINGS — see fix-plan.md for recommended fixes.');
        } else {
            $this->info('PASS');
        }
    }
}
