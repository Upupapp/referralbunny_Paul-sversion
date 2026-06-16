<?php

namespace App\Console\Commands;

use App\Services\Stitch\BladeViewScanner;
use App\Services\Stitch\InternalDispatcher;
use App\Services\Stitch\ReportWriter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\View;
use Throwable;

/**
 * STITCH Flow Contract auditor — for each critical_flows entry that's
 * reachable in Phase 1 (guest routes + system-state views), renders the
 * page and checks for data-stitch-page/data-stitch-fallback on <body> and
 * data-stitch-action/target/fallback/loading on CTAs (spec Section F).
 *
 * Entries requiring auth (guest_reachable=false) or with no route/view are
 * skipped as Phase 2 roadmap, not reported as findings.
 */
class StitchContractsCommand extends Command
{
    protected $signature = 'stitch:contracts {--run-id=} {--json} {--markdown}';

    protected $description = 'Audit data-stitch-* Flow Contract coverage on critical-flow pages and CTAs';

    private const PAGE_ATTRS = ['data-stitch-page', 'data-stitch-fallback'];

    private const CTA_ATTRS = ['data-stitch-action', 'data-stitch-target', 'data-stitch-fallback', 'data-stitch-loading'];

    public function handle(InternalDispatcher $dispatcher, BladeViewScanner $scanner): int
    {
        $writer = new ReportWriter($this->option('run-id') ?: null);

        $findings = [];
        $checked = 0;
        $skipped = 0;

        foreach (config('stitch.critical_flows', []) as $flow) {
            $result = $this->auditFlow($flow, $dispatcher, $scanner);

            if ($result === null) {
                $skipped++;

                continue;
            }

            $checked++;
            $findings = array_merge($findings, $result);
        }

        $counts = ReportWriter::emptySeverityCounts();
        foreach ($findings as $f) {
            $counts[$f['severity']]++;
        }

        $writer->writeCsv('contract-gaps.csv', ['flow_id', 'label', 'severity', 'issue', 'recommendation'], array_map(fn ($f) => [
            $f['flow_id'], $f['label'], $f['severity'], $f['issue'], $f['recommendation'],
        ], $findings));

        $summary = [
            'run_id' => $writer->runId,
            'counts' => $counts,
            'checked' => $checked,
            'skipped' => $skipped,
            'findings' => $findings,
        ];
        $writer->writeJson('contracts.json', $summary);

        if ($this->option('json')) {
            $this->line(json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } elseif ($this->option('markdown')) {
            $this->line($this->toMarkdown($findings, $counts, $checked, $skipped));
        } else {
            $this->renderTable($findings, $counts, $checked, $skipped, $writer->runId);
        }

        return self::SUCCESS;
    }

    /** @return array<int, array<string, mixed>>|null */
    private function auditFlow(array $flow, InternalDispatcher $dispatcher, BladeViewScanner $scanner): ?array
    {
        // Real route, guest-reachable → dispatch HTTP GET and scan the response.
        if ($flow['route'] !== null && $flow['guest_reachable']) {
            $html = $dispatcher->get(route($flow['route']))['body'];

            return $this->auditHtml($flow, $html, $scanner);
        }

        // Real route, auth-required → Phase 2 (seeded role/tenant + crawler).
        if ($flow['route'] !== null) {
            return null;
        }

        // No route and no view — not yet mappable.
        if ($flow['view'] === null) {
            return null;
        }

        if (! View::exists($flow['view'])) {
            return [$this->finding($flow, 'P2', "view '{$flow['view']}' does not exist yet", 'Create this Blade view (see resources/views/errors/500.blade.php for the branded-error pattern).')];
        }

        // 404 has no route of its own but is guest-reachable via any unmatched URI.
        if ($flow['guest_reachable']) {
            $html = $dispatcher->get('/__stitch-probe-'.$flow['id'])['body'];

            return $this->auditHtml($flow, $html, $scanner);
        }

        // 403/419/500 — render standalone, no HTTP context needed.
        try {
            $html = View::make($flow['view'])->render();

            return $this->auditHtml($flow, $html, $scanner);
        } catch (Throwable $e) {
            return [$this->finding($flow, 'P1', "view '{$flow['view']}' threw while rendering standalone: {$e->getMessage()}", 'Ensure this view can render without additional request/session context.')];
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function auditHtml(array $flow, string $html, BladeViewScanner $scanner): array
    {
        $findings = [];

        $bodyTags = $scanner->findTags($html, 'body');
        $body = $bodyTags[0]['tag'] ?? '';

        foreach (self::PAGE_ATTRS as $attr) {
            if (! str_contains($body, $attr.'=')) {
                $findings[] = $this->finding($flow, 'P2', "<body> is missing {$attr}", "Extend layouts.public (or add {$attr} directly) so this page carries its Flow Contract.");
            }
        }

        $ctaTags = array_filter($scanner->findTags($html, 'a'), fn ($t) => str_contains($t['tag'], 'data-stitch-action'));

        foreach ($ctaTags as $tag) {
            foreach (self::CTA_ATTRS as $attr) {
                if (! str_contains($tag['tag'], $attr.'=')) {
                    $findings[] = $this->finding($flow, 'P3', "a CTA with data-stitch-action is missing {$attr}", 'Use <x-public.cta-button> or add the missing data-stitch-* attribute directly.');

                    break;
                }
            }
        }

        if (empty($ctaTags)) {
            $findings[] = $this->finding($flow, 'P3', 'no CTAs with data-stitch-action found on this page', 'Use <x-public.cta-button> for primary CTAs so Flow Contract attributes are applied automatically.');
        }

        return $findings;
    }

    /** @return array<string, mixed> */
    private function finding(array $flow, string $severity, string $issue, string $recommendation): array
    {
        return [
            'flow_id' => $flow['id'],
            'label' => $flow['label'],
            'severity' => $severity,
            'issue' => $issue,
            'recommendation' => $recommendation,
        ];
    }

    private function renderTable(array $findings, array $counts, int $checked, int $skipped, string $runId): void
    {
        $this->info("STITCH contracts — run {$runId}");
        $this->line("Checked: {$checked}  Skipped (Phase 2): {$skipped}");
        $this->line("P0: {$counts['P0']}  P1: {$counts['P1']}  P2: {$counts['P2']}  P3: {$counts['P3']}");

        if (empty($findings)) {
            $this->info('No contract gaps found.');

            return;
        }

        $this->table(
            ['Severity', 'Flow', 'Issue'],
            array_map(fn ($f) => [$f['severity'], "#{$f['flow_id']} {$f['label']}", $f['issue']], $findings)
        );

        $this->line('Full details: '.storage_path("app/stitch/reports/{$runId}/contract-gaps.csv"));
    }

    private function toMarkdown(array $findings, array $counts, int $checked, int $skipped): string
    {
        $lines = [
            "**Checked:** {$checked}  **Skipped (Phase 2):** {$skipped}  **Counts:** P0={$counts['P0']} P1={$counts['P1']} P2={$counts['P2']} P3={$counts['P3']}",
            '',
            '| Severity | Flow | Issue |',
            '| --- | --- | --- |',
        ];

        foreach ($findings as $f) {
            $lines[] = "| {$f['severity']} | #{$f['flow_id']} {$f['label']} | {$f['issue']} |";
        }

        return implode("\n", $lines);
    }
}
