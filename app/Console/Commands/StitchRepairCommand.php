<?php

namespace App\Console\Commands;

use App\Services\Stitch\BladeViewScanner;
use App\Services\Stitch\ReportWriter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * STITCH safe-repair — applies ONLY the two mechanical, behavior-preserving
 * fixes from spec Section W's safe-repair allowlist, driven by a previous
 * stitch:lint run's findings:
 *
 *  - missing_button_type: add type="button" to a <button> that already has
 *    a click handler — @click / x-on:click / wire:click / onclick, or an
 *    id="..." wired up via getElementById()/querySelector() elsewhere in the
 *    file (BladeViewScanner::hasHandler(), same heuristic stitch:lint uses
 *    for href="#" buttons).
 *    Buttons WITHOUT a click handler are skipped — they may rely on the
 *    HTML default type="submit" inside a <form>, and changing that would be
 *    a behavior change, not a safe repair.
 *  - missing_rel_noopener: add rel="noopener noreferrer" to <a target="_blank">
 *    (appending to an existing rel="..." if present).
 *
 * Everything else (undefined routes, href="#", missing contracts, ...) is
 * never auto-edited — it stays in lint.json / fix-plan.md as a manual
 * recommendation.
 *
 * Without --safe this is a dry run: it reports what WOULD change without
 * writing any files.
 */
class StitchRepairCommand extends Command
{
    protected $signature = 'stitch:repair {runId?} {--safe} {--json} {--markdown}';

    protected $description = 'Apply safe-repair allowlist fixes (button type, rel=noopener noreferrer) from a stitch:lint run';

    private const SAFE_TYPES = ['missing_button_type', 'missing_rel_noopener'];

    public function handle(BladeViewScanner $scanner): int
    {
        $runId = $this->argument('runId') ?: ReportWriter::latestRunId();

        if ($runId === null) {
            $this->error('No STITCH runs found. Run `php artisan stitch:lint` first.');

            return self::FAILURE;
        }

        $writer = new ReportWriter($runId);

        if (! $writer->exists('lint.json')) {
            $this->error("lint.json not found for run {$runId}. Run `php artisan stitch:lint --run-id={$runId}` first.");

            return self::FAILURE;
        }

        $safe = (bool) $this->option('safe');
        $lint = $writer->readJson('lint.json');

        $candidates = array_values(array_filter(
            $lint['findings'] ?? [],
            fn ($f) => in_array($f['type'], self::SAFE_TYPES, true)
        ));

        $byPath = [];
        foreach ($candidates as $finding) {
            $byPath[$finding['path']][] = $finding;
        }

        $repairs = [];

        foreach ($byPath as $path => $findings) {
            $repairs = array_merge($repairs, $this->repairFile($path, $findings, $scanner, $safe));
        }

        $counts = ['repaired' => 0, 'skipped' => 0];
        foreach ($repairs as $r) {
            $counts[str_starts_with($r['action'], 'repair') ? 'repaired' : 'skipped']++;
        }

        $writer->writeCsv('repair-log.csv', ['path', 'line', 'type', 'action', 'status', 'before', 'after'], array_map(fn ($r) => [
            $r['path'], $r['line'], $r['type'], $r['action'], $r['status'], $r['before'], $r['after'],
        ], $repairs));

        $summary = [
            'run_id' => $writer->runId,
            'source_run_id' => $runId,
            'safe' => $safe,
            'candidates' => count($candidates),
            'repaired' => $counts['repaired'],
            'skipped' => $counts['skipped'],
            'repairs' => $repairs,
        ];
        $writer->writeJson('repair.json', $summary);

        if ($this->option('json')) {
            $this->line(json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } elseif ($this->option('markdown')) {
            $this->line($this->toMarkdown($repairs, $summary));
        } else {
            $this->renderTable($repairs, $summary);
        }

        return self::SUCCESS;
    }

    /** @return array<int, array<string, mixed>> */
    private function repairFile(string $path, array $findings, BladeViewScanner $scanner, bool $safe): array
    {
        $fullPath = resource_path('views/'.$path);
        $content = File::get($fullPath);
        $original = $content;

        $edits = [];

        $buttonCandidates = array_values(array_filter($findings, fn ($f) => $f['type'] === 'missing_button_type'));
        $relCandidates = array_values(array_filter($findings, fn ($f) => $f['type'] === 'missing_rel_noopener'));

        if (! empty($buttonCandidates)) {
            $currentButtons = array_values(array_filter(
                $scanner->findTags($content, 'button'),
                fn ($t) => ! preg_match('/\btype\s*=/i', $t['tag'])
            ));

            if (count($currentButtons) === count($buttonCandidates)) {
                foreach ($buttonCandidates as $i => $finding) {
                    $edits[] = $this->buttonEdit($currentButtons[$i], $finding, $content, $scanner);
                }
            } else {
                foreach ($buttonCandidates as $finding) {
                    $edits[] = $this->mismatchEdit($finding);
                }
            }
        }

        if (! empty($relCandidates)) {
            $currentAnchors = array_values(array_filter(
                $scanner->findTags($content, 'a'),
                fn ($t) => preg_match('/target\s*=\s*["\']_blank["\']/i', $t['tag'])
                    && ! preg_match('/rel\s*=\s*["\'][^"\']*noopener[^"\']*noreferrer|rel\s*=\s*["\'][^"\']*noreferrer[^"\']*noopener/i', $t['tag'])
            ));

            if (count($currentAnchors) === count($relCandidates)) {
                foreach ($relCandidates as $i => $finding) {
                    $edits[] = $this->relEdit($currentAnchors[$i], $finding, $content);
                }
            } else {
                foreach ($relCandidates as $finding) {
                    $edits[] = $this->mismatchEdit($finding);
                }
            }
        }

        // Apply highest-offset edits first so earlier offsets stay valid.
        $applicable = array_filter($edits, fn ($e) => isset($e['offset']));
        usort($applicable, fn ($a, $b) => $b['offset'] <=> $a['offset']);

        foreach ($applicable as $edit) {
            if ($edit['action'] !== 'repair') {
                continue;
            }

            $content = substr_replace($content, $edit['new'], $edit['offset'], strlen($edit['old']));
        }

        if ($safe && $content !== $original) {
            File::put($fullPath, $content);
        }

        foreach ($edits as &$edit) {
            $edit['status'] = $edit['action'] === 'repair'
                ? ($safe ? 'repaired' : 'would_repair')
                : $edit['action'];

            unset($edit['offset'], $edit['old'], $edit['new']);
        }

        return $edits;
    }

    /** @return array<string, mixed> */
    private function buttonEdit(array $tag, array $finding, string $content, BladeViewScanner $scanner): array
    {
        $hasHandler = $scanner->hasHandler($tag['tag'], $content);

        if (! $hasHandler) {
            return $this->edit($finding, 'skip_no_handler', $tag['tag'], $tag['tag']);
        }

        $new = preg_replace('/^<button\b/i', '<button type="button"', $tag['tag'], 1);

        return $this->edit($finding, 'repair', $tag['tag'], $new, $tag['offset']);
    }

    /** @return array<string, mixed> */
    private function relEdit(array $tag, array $finding, string $content): array
    {
        if (preg_match('/\brel\s*=\s*["\']([^"\']*)["\']/i', $tag['tag'], $relMatch)) {
            $existing = preg_split('/\s+/', trim($relMatch[1]), -1, PREG_SPLIT_NO_EMPTY);
            $missing = array_diff(['noopener', 'noreferrer'], array_map('strtolower', $existing));
            $merged = trim(implode(' ', array_merge($existing, array_values($missing))));
            $new = preg_replace('/\brel\s*=\s*["\'][^"\']*["\']/i', 'rel="'.$merged.'"', $tag['tag'], 1);
        } else {
            $new = preg_replace('/^<a\b/i', '<a rel="noopener noreferrer"', $tag['tag'], 1);
        }

        return $this->edit($finding, 'repair', $tag['tag'], $new, $tag['offset']);
    }

    /** @return array<string, mixed> */
    private function mismatchEdit(array $finding): array
    {
        return $this->edit($finding, 'skip_mismatch', $finding['snippet'] ?? '', $finding['snippet'] ?? '');
    }

    /** @return array<string, mixed> */
    private function edit(array $finding, string $action, string $rawOld, string $rawNew, ?int $offset = null): array
    {
        return [
            'path' => $finding['path'],
            'line' => $finding['line'],
            'type' => $finding['type'],
            'action' => $action,
            'before' => $this->collapse($rawOld),
            'after' => $this->collapse($rawNew),
            'old' => $rawOld,
            'new' => $rawNew,
            'offset' => $offset,
        ];
    }

    private function collapse(string $text): string
    {
        return trim(preg_replace('/\s+/', ' ', $text));
    }

    private function renderTable(array $repairs, array $summary): void
    {
        $mode = $summary['safe'] ? 'SAFE (files written)' : 'DRY RUN (no files written — pass --safe to apply)';

        $this->info("STITCH repair — source run {$summary['source_run_id']}, mode: {$mode}");
        $this->line("Candidates: {$summary['candidates']}  Repaired: {$summary['repaired']}  Skipped: {$summary['skipped']}");

        if (empty($repairs)) {
            $this->info('No safe-repairable findings.');

            return;
        }

        $this->table(
            ['Status', 'Path', 'Line', 'Type', 'Before', 'After'],
            array_map(fn ($r) => [
                $r['status'], $r['path'], $r['line'], $r['type'],
                mb_strimwidth($r['before'], 0, 60, '…'),
                mb_strimwidth($r['after'], 0, 60, '…'),
            ], $repairs)
        );

        $this->line('Full details: '.storage_path("app/stitch/reports/{$summary['run_id']}/repair-log.csv"));
    }

    private function toMarkdown(array $repairs, array $summary): string
    {
        $mode = $summary['safe'] ? 'SAFE (files written)' : 'DRY RUN';

        $lines = [
            "**Source run:** {$summary['source_run_id']}  **Mode:** {$mode}",
            "**Candidates:** {$summary['candidates']}  **Repaired:** {$summary['repaired']}  **Skipped:** {$summary['skipped']}",
            '',
            '| Status | Path | Line | Type |',
            '| --- | --- | --- | --- |',
        ];

        foreach ($repairs as $r) {
            $lines[] = "| {$r['status']} | {$r['path']} | {$r['line']} | {$r['type']} |";
        }

        return implode("\n", $lines);
    }
}
