<?php

namespace App\Console\Commands;

use App\Services\Stitch\BladeViewScanner;
use App\Services\Stitch\InternalDispatcher;
use App\Services\Stitch\ReportWriter;
use Illuminate\Console\Command;

/**
 * STITCH flow-graph crawler — BFS over the guest-accessible site using the
 * HTTP kernel for internal dispatch (no network round trip). Phase 1 covers
 * the guest role only (no seeded auth); auth-gated links simply terminate at
 * whatever redirect/login page the app itself returns, which is a valid
 * terminal state per spec Section H, not a failure.
 *
 * Output: flow-graph.json (nodes/edges), flow-graph.mmd (Mermaid diagram),
 * dead-ends.csv, and map.json for stitch:report aggregation.
 */
class StitchMapCommand extends Command
{
    protected $signature = 'stitch:map {start=/} {--run-id=} {--max-depth=} {--deep} {--json} {--markdown}';

    protected $description = 'BFS-crawl the guest-accessible site via the HTTP kernel and build a flow graph';

    /** Circuit breaker — Phase 1 guest surface is small; this guards against runaway crawls. */
    private const MAX_REQUESTS = 200;

    /** Redirect hops to follow before declaring a loop. */
    private const MAX_REDIRECTS = 10;

    public function handle(InternalDispatcher $dispatcher, BladeViewScanner $scanner): int
    {
        $writer = new ReportWriter($this->option('run-id') ?: null);

        $maxDepth = $this->option('max-depth') !== null
            ? (int) $this->option('max-depth')
            : ($this->option('deep') ? 20 : 10);

        $start = $this->normalize((string) $this->argument('start'));
        $appHost = $this->appHost();

        $nodes = [];
        $edges = [];
        $visited = [];
        $queue = [[$start, 0]];
        $truncated = false;

        while (! empty($queue)) {
            if (count($nodes) >= self::MAX_REQUESTS) {
                $truncated = true;
                break;
            }

            [$uri, $depth] = array_shift($queue);

            if (isset($visited[$uri])) {
                continue;
            }

            if ($depth > $maxDepth) {
                $visited[$uri] = true;
                $nodes[$uri] = $this->node($uri, $depth, null, null, ['skipped' => 'max_depth']);

                continue;
            }

            ['result' => $result, 'chain' => $chain, 'loop' => $loop, 'external' => $external] = $this->followRedirects($uri, $dispatcher, $appHost);

            for ($i = 0; $i < count($chain) - 1; $i++) {
                $edges[] = ['from' => $chain[$i], 'to' => $chain[$i + 1], 'type' => 'redirect'];
            }
            foreach ($chain as $hop) {
                $visited[$hop] = true;
            }

            $final = end($chain);

            if ($loop) {
                $nodes[$final] = $this->node($final, $depth, $result['status'], $result['route'], ['redirect_loop' => true]);

                continue;
            }

            if ($external) {
                $nodes[$final] = $this->node($final, $depth, $result['status'], $result['route'], ['external_redirect' => true]);

                continue;
            }

            $links = $this->extractLinks($result['body'], $appHost, $scanner);
            $bodyTag = $scanner->findTags($result['body'], 'body')[0]['tag'] ?? '';
            $hasFallback = str_contains($bodyTag, 'data-stitch-fallback=');

            $nodes[$final] = $this->node($final, $depth, $result['status'], $result['route'], [
                'outgoing_links' => count($links['internal']),
                'external_links' => count($links['external']),
                'has_stitch_fallback' => $hasFallback,
            ]);

            foreach ($links['internal'] as $target) {
                $edges[] = ['from' => $final, 'to' => $target, 'type' => 'link'];

                if (! isset($visited[$target])) {
                    $queue[] = [$target, $depth + 1];
                }
            }
        }

        $findings = array_merge(
            $this->classifyDeadEnds($nodes, $truncated),
            $this->classifyBrokenLinks($nodes, $edges)
        );

        $counts = ReportWriter::emptySeverityCounts();
        foreach ($findings as $f) {
            $counts[$f['severity']]++;
        }

        $writer->writeCsv('dead-ends.csv', ['uri', 'status', 'depth', 'severity', 'issue', 'recommendation'], array_map(fn ($f) => [
            $f['uri'], $f['status'] ?? '', $f['depth'] ?? '', $f['severity'], $f['issue'], $f['recommendation'],
        ], $findings));

        $writer->writeJson('flow-graph.json', [
            'run_id' => $writer->runId,
            'start' => $start,
            'max_depth' => $maxDepth,
            'truncated' => $truncated,
            'nodes' => array_values($nodes),
            'edges' => $edges,
        ]);

        $this->writeMermaid($nodes, $edges, $writer);

        $summary = [
            'run_id' => $writer->runId,
            'counts' => $counts,
            'total_nodes' => count($nodes),
            'total_edges' => count($edges),
            'truncated' => $truncated,
            'findings' => $findings,
        ];
        $writer->writeJson('map.json', $summary);

        if ($this->option('json')) {
            $this->line(json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } elseif ($this->option('markdown')) {
            $this->line($this->toMarkdown($findings, $counts, $summary));
        } else {
            $this->renderTable($findings, $counts, $summary, $writer->runId);
        }

        return self::SUCCESS;
    }

    /**
     * Follows redirects from $uri (up to MAX_REDIRECTS hops), returning the
     * final dispatch result, the chain of URIs visited (in order), whether a
     * same-origin redirect loop was detected, and whether the chain ended at
     * an external (different-host) redirect target.
     *
     * @return array{result: array{status:int,body:string,location:?string,route:?string}, chain: array<int,string>, loop: bool, external: bool}
     */
    private function followRedirects(string $uri, InternalDispatcher $dispatcher, string $appHost): array
    {
        $chain = [$uri];
        $current = $uri;
        $result = null;

        for ($i = 0; $i < self::MAX_REDIRECTS; $i++) {
            $result = $dispatcher->get($current);

            if ($result['status'] < 300 || $result['status'] >= 400 || ! $result['location']) {
                return ['result' => $result, 'chain' => $chain, 'loop' => false, 'external' => false];
            }

            $locationHost = parse_url($result['location'], PHP_URL_HOST);

            if ($locationHost !== null && strcasecmp($locationHost, $appHost) !== 0) {
                return ['result' => $result, 'chain' => $chain, 'loop' => false, 'external' => true];
            }

            $target = $this->normalize($result['location']);

            if (in_array($target, $chain, true)) {
                return ['result' => $result, 'chain' => $chain, 'loop' => true, 'external' => false];
            }

            $chain[] = $target;
            $current = $target;
        }

        return ['result' => $result, 'chain' => $chain, 'loop' => true, 'external' => false];
    }

    /** @return array{internal: array<int,string>, external: array<int,string>} */
    private function extractLinks(string $html, string $appHost, BladeViewScanner $scanner): array
    {
        $internal = [];
        $external = [];

        foreach ($scanner->findTags($html, 'a') as $tag) {
            // (?<!:) excludes Alpine bindings (:href="..." / x-bind:href="...")
            // whose target is a JS expression, not a literal URL.
            if (! preg_match('/(?<!:)href\s*=\s*["\']([^"\']*)["\']/i', $tag['tag'], $m)) {
                continue;
            }

            $href = trim($m[1]);

            if ($href === '' || str_starts_with($href, '#')) {
                continue;
            }

            if (preg_match('/^(mailto:|tel:|javascript:)/i', $href)) {
                $external[] = $href;

                continue;
            }

            if (preg_match('#^https?://#i', $href)) {
                $host = parse_url($href, PHP_URL_HOST);

                if (strcasecmp((string) $host, $appHost) !== 0) {
                    $external[] = $href;

                    continue;
                }

                $internal[] = $this->normalize($href);

                continue;
            }

            $internal[] = $this->normalize($href);
        }

        return [
            'internal' => array_values(array_unique($internal)),
            'external' => array_values(array_unique($external)),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function classifyDeadEnds(array $nodes, bool $truncated): array
    {
        $findings = [];

        foreach ($nodes as $uri => $node) {
            if (! empty($node['skipped']) || ! empty($node['external_redirect'])) {
                continue;
            }

            if (! empty($node['redirect_loop'])) {
                $findings[] = [
                    'uri' => $uri,
                    'status' => $node['status'],
                    'depth' => $node['depth'],
                    'severity' => 'P1',
                    'issue' => 'redirect loop detected',
                    'recommendation' => 'Inspect the redirect chain for this URI — it points back to a URI already visited.',
                ];

                continue;
            }

            $status = $node['status'];

            if ($status >= 500) {
                $findings[] = [
                    'uri' => $uri,
                    'status' => $status,
                    'depth' => $node['depth'],
                    'severity' => 'P0',
                    'issue' => "raw {$status} response",
                    'recommendation' => 'Fix the underlying error so this page renders the branded error view instead of a raw server error.',
                ];

                continue;
            }

            $outgoing = $node['outgoing_links'] ?? 0;
            $hasFallback = $node['has_stitch_fallback'] ?? false;

            if ($outgoing === 0 && ! $hasFallback) {
                $findings[] = [
                    'uri' => $uri,
                    'status' => $status,
                    'depth' => $node['depth'],
                    'severity' => 'P1',
                    'issue' => "status {$status} page has no outgoing links and no data-stitch-fallback",
                    'recommendation' => 'Add a link back to the homepage, login, or another safe page (e.g. extend layouts.public).',
                ];
            }
        }

        if ($truncated) {
            $findings[] = [
                'uri' => null,
                'status' => null,
                'depth' => null,
                'severity' => 'P2',
                'issue' => 'crawl truncated after '.self::MAX_REQUESTS.' requests',
                'recommendation' => 'Re-run with a narrower --start or investigate whether the site has an unexpectedly large/looping guest surface.',
            ];
        }

        return $findings;
    }

    /**
     * Flags <a href> links whose target resolves to a 4xx/5xx response —
     * the page itself may not be a dead end, but the link is broken.
     *
     * @return array<int, array<string, mixed>>
     */
    private function classifyBrokenLinks(array $nodes, array $edges): array
    {
        $findings = [];

        foreach ($edges as $edge) {
            if ($edge['type'] !== 'link') {
                continue;
            }

            $target = $nodes[$edge['to']] ?? null;

            if ($target === null || ($target['status'] ?? 0) < 400) {
                continue;
            }

            $findings[] = [
                'uri' => $edge['from'],
                'status' => $target['status'],
                'depth' => $nodes[$edge['from']]['depth'] ?? null,
                'severity' => 'P2',
                'issue' => "links to '{$edge['to']}' which returns {$target['status']}",
                'recommendation' => 'Fix or remove this <a href> — it points to a route/URI that returns an error.',
            ];
        }

        return $findings;
    }

    private function writeMermaid(array $nodes, array $edges, ReportWriter $writer): void
    {
        $ids = [];
        $lines = ['flowchart LR'];

        foreach (array_keys($nodes) as $i => $uri) {
            $ids[$uri] = "n{$i}";
            $node = $nodes[$uri];

            $label = $uri;
            if ($node['status'] !== null) {
                $label .= " ({$node['status']})";
            }
            if (! empty($node['redirect_loop'])) {
                $label .= ' [LOOP]';
            }
            if (! empty($node['skipped'])) {
                $label .= ' [max depth]';
            }

            $lines[] = "    {$ids[$uri]}[\"{$this->escapeMermaid($label)}\"]";
        }

        foreach ($edges as $edge) {
            $from = $ids[$edge['from']] ?? null;
            $to = $ids[$edge['to']] ?? null;

            if ($from === null || $to === null) {
                continue;
            }

            $arrow = $edge['type'] === 'redirect' ? '-.->|redirect|' : '-->';
            $lines[] = "    {$from} {$arrow} {$to}";
        }

        $writer->writeText('flow-graph.mmd', implode("\n", $lines)."\n");
    }

    private function escapeMermaid(string $text): string
    {
        return str_replace(['"', "\n"], ["'", ' '], $text);
    }

    /** @return array<string, mixed> */
    private function node(string $uri, int $depth, ?int $status, ?string $route, array $extra = []): array
    {
        return array_merge([
            'uri' => $uri,
            'depth' => $depth,
            'status' => $status,
            'route' => $route,
        ], $extra);
    }

    private function appHost(): string
    {
        return (string) (parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost');
    }

    /**
     * Normalizes a path or absolute URL to a canonical site-relative URI for
     * dedup/comparison: strips scheme+host (any host — callers check origin
     * separately before calling this), drops the fragment, ensures a leading
     * "/", and trims a trailing "/" (except for "/" itself).
     */
    private function normalize(string $uri): string
    {
        if (preg_match('#^https?://#i', $uri)) {
            $path = parse_url($uri, PHP_URL_PATH) ?: '/';
            $query = parse_url($uri, PHP_URL_QUERY);
            $uri = $path.($query ? '?'.$query : '');
        }

        $uri = explode('#', $uri, 2)[0];

        if ($uri === '') {
            return '/';
        }

        if (! str_starts_with($uri, '/')) {
            $uri = '/'.$uri;
        }

        if ($uri !== '/' && str_ends_with($uri, '/')) {
            $uri = rtrim($uri, '/');
        }

        return $uri;
    }

    private function renderTable(array $findings, array $counts, array $summary, string $runId): void
    {
        $this->info("STITCH map — run {$runId}");
        $this->line("Start node: total nodes={$summary['total_nodes']}  edges={$summary['total_edges']}".($summary['truncated'] ? '  (TRUNCATED)' : ''));
        $this->line("P0: {$counts['P0']}  P1: {$counts['P1']}  P2: {$counts['P2']}  P3: {$counts['P3']}");

        if (empty($findings)) {
            $this->info('No dead ends found.');

            return;
        }

        $this->table(
            ['Severity', 'URI', 'Status', 'Issue'],
            array_map(fn ($f) => [$f['severity'], $f['uri'] ?? '-', $f['status'] ?? '-', $f['issue']], $findings)
        );

        $this->line('Full details: '.storage_path("app/stitch/reports/{$runId}/dead-ends.csv"));
        $this->line('Flow graph: '.storage_path("app/stitch/reports/{$runId}/flow-graph.json").' / flow-graph.mmd');
    }

    private function toMarkdown(array $findings, array $counts, array $summary): string
    {
        $lines = [
            "**Nodes:** {$summary['total_nodes']}  **Edges:** {$summary['total_edges']}  **Truncated:** ".($summary['truncated'] ? 'yes' : 'no'),
            "**Counts:** P0={$counts['P0']} P1={$counts['P1']} P2={$counts['P2']} P3={$counts['P3']}",
            '',
            '| Severity | URI | Status | Issue |',
            '| --- | --- | --- | --- |',
        ];

        foreach ($findings as $f) {
            $lines[] = "| {$f['severity']} | ".($f['uri'] ?? '-')." | ".($f['status'] ?? '-')." | {$f['issue']} |";
        }

        return implode("\n", $lines);
    }
}
