<?php

namespace App\Console\Commands;

use App\Services\Stitch\ReportWriter;
use App\Services\Stitch\RouteRegistry;
use Illuminate\Console\Command;

/**
 * STITCH fallback registry validator — confirms every route name in
 * config/stitch.php (default_fallbacks, role_dashboards, route_aliases,
 * critical_flows) resolves via the router (spec Section E). A missing
 * entry means STITCH's own safety net points nowhere.
 */
class StitchFallbacksCommand extends Command
{
    protected $signature = 'stitch:fallbacks {--run-id=} {--json} {--markdown}';

    protected $description = 'Validate config/stitch.php route names against the router';

    public function handle(RouteRegistry $routes): int
    {
        $writer = new ReportWriter($this->option('run-id') ?: null);

        $rows = [];

        foreach (config('stitch.default_fallbacks', []) as $key => $routeName) {
            $rows[] = $this->check('default_fallbacks', $key, $routeName, $routes);
        }

        foreach (config('stitch.role_dashboards', []) as $key => $routeName) {
            $rows[] = $this->check('role_dashboards', $key, $routeName, $routes);
        }

        foreach (config('stitch.route_aliases', []) as $alias => $target) {
            $rows[] = $this->check('route_aliases', "{$alias} (alias)", $alias, $routes);
            $rows[] = $this->check('route_aliases', "{$alias} (target)", $target, $routes);
        }

        foreach (config('stitch.critical_flows', []) as $flow) {
            if ($flow['route'] !== null) {
                $rows[] = $this->check("critical_flows#{$flow['id']}", $flow['label'], $flow['route'], $routes);
            }
        }

        $findings = array_values(array_filter($rows, fn ($r) => $r['status'] === 'missing'));

        $counts = ReportWriter::emptySeverityCounts();
        foreach ($findings as $f) {
            $counts[$f['severity']]++;
        }

        $writer->writeCsv('missing-fallbacks.csv', ['source', 'key', 'route', 'status', 'severity'], array_map(fn ($r) => [
            $r['source'], $r['key'], $r['route'], $r['status'], $r['severity'],
        ], $findings));

        $summary = [
            'run_id' => $writer->runId,
            'counts' => $counts,
            'checked' => count($rows),
            'findings' => $findings,
        ];
        $writer->writeJson('fallbacks.json', $summary);

        if ($this->option('json')) {
            $this->line(json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } elseif ($this->option('markdown')) {
            $this->line($this->toMarkdown($rows, $counts));
        } else {
            $this->renderTable($rows, $counts, $writer->runId);
        }

        return self::SUCCESS;
    }

    /** @return array{source: string, key: string, route: string, status: string, severity: string} */
    private function check(string $source, string $key, string $routeName, RouteRegistry $routes): array
    {
        $exists = $routes->has($routeName);

        return [
            'source' => $source,
            'key' => $key,
            'route' => $routeName,
            'status' => $exists ? 'ok' : 'missing',
            'severity' => 'P1',
        ];
    }

    private function renderTable(array $rows, array $counts, string $runId): void
    {
        $this->info("STITCH fallbacks — run {$runId}");
        $this->line("Checked: ".count($rows)."  Missing: {$counts['P1']}");

        $missing = array_filter($rows, fn ($r) => $r['status'] === 'missing');

        if (empty($missing)) {
            $this->info('All fallback / dashboard / alias / critical-flow route names resolve.');

            return;
        }

        $this->table(
            ['Source', 'Key', 'Route', 'Status'],
            array_map(fn ($r) => [$r['source'], $r['key'], $r['route'], $r['status']], $missing)
        );

        $this->line('Full details: '.storage_path("app/stitch/reports/{$runId}/missing-fallbacks.csv"));
    }

    private function toMarkdown(array $rows, array $counts): string
    {
        $missing = array_filter($rows, fn ($r) => $r['status'] === 'missing');

        $lines = [
            "**Checked:** ".count($rows)."  **Missing:** {$counts['P1']}",
            '',
            '| Source | Key | Route | Status |',
            '| --- | --- | --- | --- |',
        ];

        foreach ($missing as $r) {
            $lines[] = "| {$r['source']} | {$r['key']} | {$r['route']} | {$r['status']} |";
        }

        return implode("\n", $lines);
    }
}
