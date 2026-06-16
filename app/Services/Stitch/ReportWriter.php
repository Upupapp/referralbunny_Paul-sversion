<?php

namespace App\Services\Stitch;

use Illuminate\Support\Facades\File;

/**
 * Writes STITCH report artifacts to storage/app/stitch/reports/{runId}/ and
 * computes the PASS / PASS WITH WARNINGS / BLOCK RELEASE verdict (spec
 * Sections Y and AA) from a severity-count map.
 */
class ReportWriter
{
    public readonly string $runId;

    protected string $basePath;

    public function __construct(?string $runId = null)
    {
        $this->runId = $runId ?: now()->format('Ymd_His');
        $this->basePath = storage_path('app/stitch/reports/'.$this->runId);

        File::ensureDirectoryExists($this->basePath);
    }

    public function path(string $filename = ''): string
    {
        return $filename === '' ? $this->basePath : $this->basePath.DIRECTORY_SEPARATOR.$filename;
    }

    public function exists(string $filename): bool
    {
        return File::exists($this->path($filename));
    }

    public function writeJson(string $filename, mixed $data): void
    {
        File::put($this->path($filename), json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
    }

    public function readJson(string $filename): mixed
    {
        return json_decode(File::get($this->path($filename)), true);
    }

    public function writeMarkdown(string $filename, string $content): void
    {
        File::put($this->path($filename), $content);
    }

    public function writeText(string $filename, string $content): void
    {
        File::put($this->path($filename), $content);
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, array<int|string, mixed>>  $rows
     */
    public function writeCsv(string $filename, array $headers, array $rows): void
    {
        $handle = fopen($this->path($filename), 'w');

        fputcsv($handle, $headers);
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        fclose($handle);
    }

    /** @return array<string, int> */
    public static function emptySeverityCounts(): array
    {
        return ['P0' => 0, 'P1' => 0, 'P2' => 0, 'P3' => 0];
    }

    /**
     * @param  array<string, int>  ...$counts
     * @return array<string, int>
     */
    public static function mergeSeverityCounts(array ...$counts): array
    {
        $merged = self::emptySeverityCounts();

        foreach ($counts as $count) {
            foreach ($merged as $level => $value) {
                $merged[$level] += $count[$level] ?? 0;
            }
        }

        return $merged;
    }

    /**
     * Determine the release verdict from severity counts (keys "P0".."P3")
     * and a --fail-on threshold. Any non-zero count at or above the
     * threshold blocks the release; non-zero counts below it become
     * warnings.
     *
     * @param  array<string, int>  $counts
     */
    public function verdict(array $counts, string $failOn = 'P0'): string
    {
        $order = ['P0', 'P1', 'P2', 'P3'];
        $threshold = array_search($failOn, $order, true);
        $threshold = $threshold === false ? 0 : $threshold;

        foreach ($order as $index => $level) {
            if ($index <= $threshold && ($counts[$level] ?? 0) > 0) {
                return 'BLOCK RELEASE';
            }
        }

        foreach (array_slice($order, $threshold + 1) as $level) {
            if (($counts[$level] ?? 0) > 0) {
                return 'PASS WITH WARNINGS';
            }
        }

        return 'PASS';
    }

    /**
     * Most recent run directory under storage/app/stitch/reports, or null
     * if no runs exist yet.
     */
    public static function latestRunId(): ?string
    {
        $base = storage_path('app/stitch/reports');

        if (! File::isDirectory($base)) {
            return null;
        }

        $dirs = array_filter(File::directories($base), fn ($dir) => is_dir($dir));
        if (empty($dirs)) {
            return null;
        }

        $names = array_map('basename', $dirs);
        sort($names);

        return end($names);
    }
}
