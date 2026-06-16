<?php

namespace App\Console\Commands;

use App\Services\Stitch\BladeViewScanner;
use App\Services\Stitch\ReportWriter;
use App\Services\Stitch\RouteRegistry;
use Illuminate\Console\Command;

/**
 * STITCH static lint — scans every Blade view for undefined route() calls,
 * dead links (href="#" / javascript:void(0) with no handler), and
 * safe-repairable markup (missing button type, target="_blank" without
 * rel="noopener noreferrer"). See spec Section J.
 */
class StitchLintCommand extends Command
{
    protected $signature = 'stitch:lint {--run-id=} {--json} {--markdown}';

    protected $description = 'Static scan of all Blade views for undefined routes, dead links, and unsafe markup';

    public function handle(BladeViewScanner $scanner, RouteRegistry $routes): int
    {
        $writer = new ReportWriter($this->option('run-id') ?: null);

        $criticalViews = collect(config('stitch.critical_flows', []))
            ->pluck('view')
            ->filter()
            ->flip()
            ->all();

        $findings = [];

        foreach ($scanner->viewFiles() as $file) {
            foreach ($scanner->scanFile($file['path']) as $finding) {
                $issue = $this->classify($finding, $file['view'], $routes, $criticalViews);

                if ($issue !== null) {
                    $findings[] = array_merge($issue, [
                        'view' => $file['view'],
                        'path' => $file['relative'],
                        'line' => $finding['line'],
                        'snippet' => $finding['snippet'] ?? '',
                    ]);
                }
            }
        }

        $counts = ReportWriter::emptySeverityCounts();
        foreach ($findings as $finding) {
            $counts[$finding['severity']]++;
        }

        $writer->writeCsv('broken-actions.csv', [
            'severity', 'view', 'path', 'line', 'type', 'issue', 'recommendation', 'snippet',
        ], array_map(fn ($f) => [
            $f['severity'], $f['view'], $f['path'], $f['line'], $f['type'], $f['issue'], $f['recommendation'], $f['snippet'],
        ], $findings));

        $summary = ['run_id' => $writer->runId, 'counts' => $counts, 'findings' => $findings];
        $writer->writeJson('lint.json', $summary);

        if ($this->option('json')) {
            $this->line(json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } elseif ($this->option('markdown')) {
            $this->line($this->toMarkdown($findings, $counts));
        } else {
            $this->renderTable($findings, $counts, $writer->runId);
        }

        return self::SUCCESS;
    }

    /** @return array{severity: string, type: string, issue: string, recommendation: string}|null */
    private function classify(array $finding, string $view, RouteRegistry $routes, array $criticalViews): ?array
    {
        switch ($finding['type']) {
            case 'route_call':
                if ($routes->has($finding['name']) || $finding['guarded']) {
                    return null;
                }

                return [
                    'severity' => 'P1',
                    'type' => 'undefined_route',
                    'issue' => "route('{$finding['name']}') does not match any registered route",
                    'recommendation' => 'Define the route or correct the route name — would throw RouteNotFoundException (raw 500) if rendered.',
                ];

            case 'href_hash':
                if ($finding['has_handler']) {
                    return null;
                }

                return [
                    'severity' => isset($criticalViews[$view]) ? 'P1' : 'P2',
                    'type' => 'dead_link',
                    'issue' => 'href="#" with no click handler',
                    'recommendation' => 'Point to a real route, or add an Alpine click handler (@click) / data-* attribute.',
                ];

            case 'javascript_void':
                if ($finding['has_handler']) {
                    return null;
                }

                return [
                    'severity' => 'P2',
                    'type' => 'dead_link',
                    'issue' => 'href="javascript:void(0)" with no click handler',
                    'recommendation' => 'Point to a real route, or add an Alpine click handler (@click) / data-* attribute.',
                ];

            case 'button':
                if ($finding['has_type']) {
                    return null;
                }

                return [
                    'severity' => 'P3',
                    'type' => 'missing_button_type',
                    'issue' => '<button> has no type= attribute',
                    'recommendation' => 'Add type="button" (safe-repairable via stitch:repair --safe).',
                ];

            case 'target_blank':
                if ($finding['has_rel']) {
                    return null;
                }

                return [
                    'severity' => 'P3',
                    'type' => 'missing_rel_noopener',
                    'issue' => 'target="_blank" without rel="noopener noreferrer"',
                    'recommendation' => 'Add rel="noopener noreferrer" (safe-repairable via stitch:repair --safe).',
                ];

            default:
                return null;
        }
    }

    private function renderTable(array $findings, array $counts, string $runId): void
    {
        $this->info("STITCH lint — run {$runId}");
        $this->line("P0: {$counts['P0']}  P1: {$counts['P1']}  P2: {$counts['P2']}  P3: {$counts['P3']}");

        if (empty($findings)) {
            $this->info('No findings.');

            return;
        }

        $this->table(
            ['Severity', 'View', 'Line', 'Issue'],
            array_map(fn ($f) => [$f['severity'], $f['view'], $f['line'], $f['issue']], $findings)
        );

        $this->line('Full details: '.storage_path("app/stitch/reports/{$runId}/broken-actions.csv"));
    }

    private function toMarkdown(array $findings, array $counts): string
    {
        $lines = [
            "**Counts** — P0: {$counts['P0']}, P1: {$counts['P1']}, P2: {$counts['P2']}, P3: {$counts['P3']}",
            '',
            '| Severity | View | Line | Issue |',
            '| --- | --- | --- | --- |',
        ];

        foreach ($findings as $f) {
            $lines[] = "| {$f['severity']} | {$f['view']} | {$f['line']} | {$f['issue']} |";
        }

        return implode("\n", $lines);
    }
}
