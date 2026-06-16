<?php

namespace App\Services\Stitch;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Walks resources/views/**\/*.blade.php and extracts the structural signals
 * STITCH commands need: route() calls, anchor/button tags (for href="#",
 * javascript:void(0), target="_blank", missing button type), and
 * data-stitch-* contract attributes.
 *
 * Tag matching is a heuristic (non-greedy up to the first ">"), not a full
 * HTML/Blade parser — sufficient for lint-style findings reviewed in
 * fix-plan.md, without adding a parser dependency.
 */
class BladeViewScanner
{
    /** @return array<int, array{path: string, view: string, relative: string}> */
    public function viewFiles(): array
    {
        $base = resource_path('views');
        $files = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($base) + 1));
            $view = str_replace('/', '.', substr($relative, 0, -strlen('.blade.php')));

            $files[] = [
                'path' => $file->getPathname(),
                'view' => $view,
                'relative' => $relative,
            ];
        }

        return $files;
    }

    /** @return array<int, array<string, mixed>> */
    public function scanFile(string $path): array
    {
        $content = file_get_contents($path);
        $findings = [];

        // Route::has('name') guards a later route('name') call (e.g.
        // @if (Route::has('register'))) — collect guarded names so the
        // command can skip those route_call findings as intentional.
        $guardedRoutes = [];
        foreach ($this->matchesWithOffsets('/Route::has\(\s*[\'"]([^\'"]+)[\'"]/', $content) as $match) {
            $guardedRoutes[$match['groups'][1]] = true;
        }

        // (?<!->) excludes Request::route('param') / request()->route('param')
        // method calls, which are unrelated to the route() URL helper.
        foreach ($this->matchesWithOffsets('/(?<!->)route\(\s*[\'"]([^\'"]+)[\'"]/', $content) as $match) {
            $findings[] = [
                'type' => 'route_call',
                'line' => $this->lineAt($content, $match['offset']),
                'name' => $match['groups'][1],
                'guarded' => isset($guardedRoutes[$match['groups'][1]]),
                'snippet' => $this->collapse($match['groups'][0]),
            ];
        }

        foreach ($this->extractTags($content, 'a') as $tag) {
            $line = $this->lineAt($content, $tag['offset']);
            $snippet = $this->collapse($tag['tag']);

            if (preg_match('/href\s*=\s*["\']#["\']/i', $tag['tag'])) {
                $findings[] = [
                    'type' => 'href_hash',
                    'line' => $line,
                    'has_handler' => $this->hasHandler($tag['tag'], $content),
                    'snippet' => $snippet,
                ];
            }

            if (preg_match('/href\s*=\s*["\']javascript:void\(0\)["\']/i', $tag['tag'])) {
                $findings[] = [
                    'type' => 'javascript_void',
                    'line' => $line,
                    'has_handler' => $this->hasHandler($tag['tag'], $content),
                    'snippet' => $snippet,
                ];
            }

            if (preg_match('/target\s*=\s*["\']_blank["\']/i', $tag['tag'])) {
                $findings[] = [
                    'type' => 'target_blank',
                    'line' => $line,
                    'has_rel' => (bool) preg_match('/rel\s*=\s*["\'][^"\']*noopener[^"\']*noreferrer|rel\s*=\s*["\'][^"\']*noreferrer[^"\']*noopener/i', $tag['tag']),
                    'snippet' => $snippet,
                ];
            }
        }

        foreach ($this->extractTags($content, 'button') as $tag) {
            $findings[] = [
                'type' => 'button',
                'line' => $this->lineAt($content, $tag['offset']),
                'has_type' => (bool) preg_match('/\btype\s*=/i', $tag['tag']),
                'snippet' => $this->collapse($tag['tag']),
            ];
        }

        foreach ($this->matchesWithOffsets('/data-stitch-([\w-]+)/', $content) as $match) {
            $findings[] = [
                'type' => 'stitch_attr',
                'line' => $this->lineAt($content, $match['offset']),
                'name' => $match['groups'][1],
                'snippet' => $this->collapse($match['groups'][0]),
            ];
        }

        return $findings;
    }

    /**
     * True if the tag has an inline click handler, or its id="..." is wired
     * up via getElementById()/querySelector() elsewhere in the file (a
     * common "share link" pattern where href="#" is a JS-populated
     * placeholder).
     */
    public function hasHandler(string $tag, string $content): bool
    {
        if (preg_match('/(@click|x-on:click|wire:click|onclick\s*=|data-[\w-]+\s*=)/i', $tag)) {
            return true;
        }

        if (preg_match('/\bid\s*=\s*["\']([^"\']+)["\']/i', $tag, $idMatch)) {
            $id = preg_quote($idMatch[1], '/');

            return (bool) preg_match('/getElementById\(\s*[\'"]'.$id.'[\'"]\s*\)|querySelector(?:All)?\(\s*[\'"]#'.$id.'[\'"]\s*\)/i', $content);
        }

        return false;
    }

    /**
     * Extract `<tagName ...>` opening tags from arbitrary HTML/Blade content.
     * Stops at the first unquoted ">", so embedded ">" characters inside
     * quoted attribute values (e.g. Alpine `x-show="count > 0"`) don't
     * truncate the tag before later attributes like `@click`/`type=`.
     * Also used by stitch:contracts to scan rendered page output for
     * data-stitch-* attributes.
     *
     * @return array<int, array{tag: string, offset: int}>
     */
    public function findTags(string $content, string $tagName): array
    {
        preg_match_all('/<'.$tagName.'\b(?:[^>"\']|"[^"]*"|\'[^\']*\')*>/i', $content, $matches, PREG_OFFSET_CAPTURE);

        return array_map(
            fn ($m) => ['tag' => $m[0], 'offset' => $m[1]],
            $matches[0]
        );
    }

    /** @return array<int, array{tag: string, offset: int}> */
    private function extractTags(string $content, string $tagName): array
    {
        return $this->findTags($content, $tagName);
    }

    /** @return array<int, array{offset: int, groups: array<int, string>}> */
    private function matchesWithOffsets(string $pattern, string $content): array
    {
        preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);

        return array_map(
            fn ($m) => [
                'offset' => $m[0][1],
                'groups' => array_map(fn ($g) => $g[0], $m),
            ],
            $matches
        );
    }

    private function lineAt(string $content, int $offset): int
    {
        return substr_count(substr($content, 0, $offset), "\n") + 1;
    }

    private function collapse(string $text): string
    {
        return trim(preg_replace('/\s+/', ' ', $text));
    }
}
