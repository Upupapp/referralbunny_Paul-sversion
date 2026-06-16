<?php

namespace App\Console\Commands;

use App\Services\Stitch\ReportWriter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

/**
 * STITCH report aggregator — merges a run's lint/fallbacks/contracts/map
 * findings plus a live referralbunny:diagnose-public-landing check into
 * summary.md, fix-plan.md, and report.json (consumed by stitch:release-gate).
 * See spec Section Y.
 *
 * With no runId, runs stitch:lint/fallbacks/contracts/map fresh into a new
 * run directory before aggregating. With a runId, aggregates whatever
 * *.json artifacts already exist for that run — nothing is re-run.
 */
class StitchReportCommand extends Command
{
    protected $signature = 'stitch:report {runId?} {--json} {--markdown}';

    protected $description = 'Aggregate lint/fallbacks/contracts/map findings into summary.md, fix-plan.md, and report.json';

    /** Command suffix => artifact filename. */
    private const SECTIONS = [
        'lint' => 'lint.json',
        'fallbacks' => 'fallbacks.json',
        'contracts' => 'contracts.json',
        'map' => 'map.json',
    ];

    public function handle(): int
    {
        $runIdArg = $this->argument('runId');

        if ($runIdArg !== null) {
            $writer = new ReportWriter($runIdArg);

            if (! $this->anyArtifactExists($writer)) {
                $this->error("No STITCH artifacts found for run {$runIdArg}. Run stitch:lint/fallbacks/contracts/map with --run-id={$runIdArg} first, or call stitch:report with no argument for a fresh run.");

                return self::FAILURE;
            }
        } else {
            $writer = new ReportWriter();

            foreach (self::SECTIONS as $command => $file) {
                $this->callSilently('stitch:'.$command, ['--run-id' => $writer->runId]);
            }
        }

        $sections = [];
        $countsList = [];
        $missing = [];

        foreach (self::SECTIONS as $key => $file) {
            if ($writer->exists($file)) {
                $sections[$key] = $writer->readJson($file);
                $countsList[] = $sections[$key]['counts'] ?? ReportWriter::emptySeverityCounts();
            } else {
                $sections[$key] = null;
                $missing[] = $key;
            }
        }

        $repair = $writer->exists('repair.json') ? $writer->readJson('repair.json') : null;

        $landingExit = Artisan::call('referralbunny:diagnose-public-landing');
        $landingFailures = $landingExit === 0 ? 0 : $this->countLandingFailures(Artisan::output());

        $findings = $this->collectFindings($sections);

        if ($landingExit !== 0) {
            $findings[] = [
                'severity' => 'P1',
                'source' => 'landing',
                'location' => 'public.home',
                'issue' => "referralbunny:diagnose-public-landing reported {$landingFailures} failing check(s)",
                'recommendation' => 'Run `php artisan referralbunny:diagnose-public-landing` for details.',
            ];
            usort($findings, fn ($a, $b) => $this->severityRank($a['severity']) <=> $this->severityRank($b['severity']));
        }

        $counts = ReportWriter::mergeSeverityCounts(...$countsList);
        if ($landingExit !== 0) {
            $counts['P1']++;
        }

        $verdict = $writer->verdict($counts);

        $sectionRows = $this->buildSectionRows($sections);

        $writer->writeMarkdown('summary.md', $this->buildSummaryMarkdown(
            $writer->runId, $counts, $verdict, $sectionRows, $repair, $landingExit, $landingFailures, $missing
        ));
        $writer->writeMarkdown('fix-plan.md', $this->buildFixPlanMarkdown($findings));

        $report = [
            'run_id' => $writer->runId,
            'counts' => $counts,
            'verdict' => $verdict,
            'sections' => [
                'lint' => $this->sectionSummary($sections['lint']),
                'fallbacks' => $this->sectionSummary($sections['fallbacks'], ['checked']),
                'contracts' => $this->sectionSummary($sections['contracts'], ['checked', 'skipped']),
                'map' => $this->sectionSummary($sections['map'], ['total_nodes', 'total_edges', 'truncated']),
            ],
            'missing_sections' => $missing,
            'landing' => ['exit' => $landingExit, 'failures' => $landingFailures],
            'repair' => $repair === null ? null : [
                'source_run_id' => $repair['source_run_id'],
                'safe' => $repair['safe'],
                'candidates' => $repair['candidates'],
                'repaired' => $repair['repaired'],
                'skipped' => $repair['skipped'],
            ],
            'findings' => $findings,
        ];
        $writer->writeJson('report.json', $report);

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } elseif ($this->option('markdown')) {
            $this->line($this->buildSummaryMarkdown(
                $writer->runId, $counts, $verdict, $sectionRows, $repair, $landingExit, $landingFailures, $missing
            ));
        } else {
            $this->renderTable($report, $sectionRows);
        }

        return $verdict === 'BLOCK RELEASE' ? self::FAILURE : self::SUCCESS;
    }

    private function anyArtifactExists(ReportWriter $writer): bool
    {
        foreach (self::SECTIONS as $file) {
            if ($writer->exists($file)) {
                return true;
            }
        }

        return false;
    }

    /** @return array<int, array<string, mixed>> */
    private function collectFindings(array $sections): array
    {
        $findings = [];

        foreach ($sections['lint']['findings'] ?? [] as $f) {
            $findings[] = [
                'severity' => $f['severity'],
                'source' => 'lint',
                'location' => "{$f['view']}:{$f['line']}",
                'issue' => $f['issue'],
                'recommendation' => $f['recommendation'],
            ];
        }

        foreach ($sections['fallbacks']['findings'] ?? [] as $f) {
            $findings[] = [
                'severity' => $f['severity'],
                'source' => 'fallbacks',
                'location' => "{$f['source']}: {$f['key']}",
                'issue' => "route '{$f['route']}' is not registered",
                'recommendation' => "Define route '{$f['route']}', or correct config/stitch.php to reference an existing route.",
            ];
        }

        foreach ($sections['contracts']['findings'] ?? [] as $f) {
            $findings[] = [
                'severity' => $f['severity'],
                'source' => 'contracts',
                'location' => "#{$f['flow_id']} {$f['label']}",
                'issue' => $f['issue'],
                'recommendation' => $f['recommendation'],
            ];
        }

        foreach ($sections['map']['findings'] ?? [] as $f) {
            $findings[] = [
                'severity' => $f['severity'],
                'source' => 'map',
                'location' => $f['uri'] ?? '-',
                'issue' => $f['issue'],
                'recommendation' => $f['recommendation'],
            ];
        }

        usort($findings, fn ($a, $b) => $this->severityRank($a['severity']) <=> $this->severityRank($b['severity']));

        return $findings;
    }

    private function severityRank(string $severity): int
    {
        $rank = array_search($severity, ['P0', 'P1', 'P2', 'P3'], true);

        return $rank === false ? 99 : $rank;
    }

    /** @return array<string, mixed>|null */
    private function sectionSummary(?array $data, array $extra = []): ?array
    {
        if ($data === null) {
            return null;
        }

        $summary = [
            'counts' => $data['counts'] ?? ReportWriter::emptySeverityCounts(),
            'total_findings' => count($data['findings'] ?? []),
        ];

        foreach ($extra as $key) {
            $summary[$key] = $data[$key] ?? null;
        }

        return $summary;
    }

    private function countLandingFailures(string $output): int
    {
        if (preg_match('/(\d+) check\(s\) failed/', $output, $m)) {
            return (int) $m[1];
        }

        return substr_count($output, '  MISSING ') + substr_count($output, '  FAIL    ');
    }

    /**
     * Builds [name, p0, p1, p2, p3, details] rows shared by the console
     * table and the summary.md table.
     *
     * @return array<int, array{0: string, 1: string, 2: string, 3: string, 4: string, 5: string}>
     */
    private function buildSectionRows(array $sections): array
    {
        $rows = [];

        $lint = $sections['lint'];
        $rows[] = $this->sectionRow('lint', $lint, $lint ? count($lint['findings']).' finding(s)' : 'not run');

        $fb = $sections['fallbacks'];
        $rows[] = $this->sectionRow('fallbacks', $fb, $fb ? "{$fb['checked']} checked, ".count($fb['findings']).' missing' : 'not run');

        $contracts = $sections['contracts'];
        $rows[] = $this->sectionRow('contracts', $contracts, $contracts ? "{$contracts['checked']} checked, {$contracts['skipped']} skipped (Phase 2), ".count($contracts['findings']).' gap(s)' : 'not run');

        $map = $sections['map'];
        $rows[] = $this->sectionRow('map', $map, $map ? "{$map['total_nodes']} nodes, {$map['total_edges']} edges".($map['truncated'] ? ', TRUNCATED' : '') : 'not run');

        return $rows;
    }

    /** @return array{0: string, 1: string, 2: string, 3: string, 4: string, 5: string} */
    private function sectionRow(string $name, ?array $section, string $details): array
    {
        if ($section === null) {
            return [$name, '-', '-', '-', '-', $details];
        }

        $c = $section['counts'] ?? ReportWriter::emptySeverityCounts();

        return [$name, (string) $c['P0'], (string) $c['P1'], (string) $c['P2'], (string) $c['P3'], $details];
    }

    private function buildSummaryMarkdown(
        string $runId,
        array $counts,
        string $verdict,
        array $sectionRows,
        ?array $repair,
        int $landingExit,
        int $landingFailures,
        array $missing,
    ): string {
        $lines = [
            "# STITCH Report — run {$runId}",
            '',
            "**Verdict:** {$verdict}",
            "**Counts:** P0={$counts['P0']} P1={$counts['P1']} P2={$counts['P2']} P3={$counts['P3']}",
            '',
            '## Sections',
            '',
            '| Section | P0 | P1 | P2 | P3 | Details |',
            '| --- | --- | --- | --- | --- | --- |',
        ];

        foreach ($sectionRows as [$name, $p0, $p1, $p2, $p3, $details]) {
            $lines[] = "| {$name} | {$p0} | {$p1} | {$p2} | {$p3} | {$this->escapeMd($details)} |";
        }

        $lines[] = '';
        $lines[] = '## Public Landing Page';
        $lines[] = '';
        $lines[] = $landingExit === 0
            ? '- ok — `referralbunny:diagnose-public-landing` reported all checks passed.'
            : "- FAIL — `referralbunny:diagnose-public-landing` reported {$landingFailures} failing check(s). Run it directly for details.";

        if ($repair !== null) {
            $lines[] = '';
            $lines[] = '## Last Repair';
            $lines[] = '';
            $mode = $repair['safe'] ? 'applied' : 'dry run';
            $lines[] = "- Source run `{$repair['source_run_id']}`, mode: {$mode}. Candidates: {$repair['candidates']}, repaired: {$repair['repaired']}, skipped: {$repair['skipped']}.";
        }

        if (! empty($missing)) {
            $lines[] = '';
            $lines[] = '## Not Run';
            $lines[] = '';
            foreach ($missing as $m) {
                $lines[] = "- {$m} — no {$m}.json found for this run.";
            }
        }

        $lines[] = '';
        $lines[] = '## Artifacts';
        $lines[] = '';
        $lines[] = '- Full fix plan: `'.storage_path("app/stitch/reports/{$runId}/fix-plan.md").'`';
        $lines[] = '- Machine-readable: `'.storage_path("app/stitch/reports/{$runId}/report.json").'`';

        return implode("\n", $lines)."\n";
    }

    private function buildFixPlanMarkdown(array $findings): string
    {
        if (empty($findings)) {
            return "# STITCH Fix Plan\n\nNo findings — nothing to fix.\n";
        }

        $lines = [
            '# STITCH Fix Plan',
            '',
            '| Severity | Source | Location | Issue | Recommendation |',
            '| --- | --- | --- | --- | --- |',
        ];

        foreach ($findings as $f) {
            $lines[] = "| {$f['severity']} | {$f['source']} | {$this->escapeMd($f['location'])} | {$this->escapeMd($f['issue'])} | {$this->escapeMd($f['recommendation'])} |";
        }

        return implode("\n", $lines)."\n";
    }

    private function escapeMd(string $text): string
    {
        return str_replace(['|', "\n"], ['\\|', ' '], $text);
    }

    private function renderTable(array $report, array $sectionRows): void
    {
        $this->info("STITCH report — run {$report['run_id']}");
        $this->line("Verdict: {$report['verdict']}");
        $this->line("P0: {$report['counts']['P0']}  P1: {$report['counts']['P1']}  P2: {$report['counts']['P2']}  P3: {$report['counts']['P3']}");
        $this->newLine();

        $this->table(['Section', 'P0', 'P1', 'P2', 'P3', 'Details'], $sectionRows);

        if (! empty($report['missing_sections'])) {
            $this->warn('Not run for this report: '.implode(', ', $report['missing_sections']));
        }

        $landing = $report['landing'];
        $this->line($landing['exit'] === 0
            ? 'Public landing page: all checks passed.'
            : "Public landing page: {$landing['failures']} check(s) failed (php artisan referralbunny:diagnose-public-landing).");

        if ($report['repair'] !== null) {
            $r = $report['repair'];
            $mode = $r['safe'] ? 'applied' : 'dry run';
            $this->line("Last repair: source run {$r['source_run_id']}, mode: {$mode}. Candidates: {$r['candidates']}, repaired: {$r['repaired']}, skipped: {$r['skipped']}.");
        }

        $this->newLine();
        $this->line('Summary: '.storage_path("app/stitch/reports/{$report['run_id']}/summary.md"));
        $this->line('Fix plan: '.storage_path("app/stitch/reports/{$report['run_id']}/fix-plan.md"));
    }
}
