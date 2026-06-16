<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class StitchCommandsTest extends TestCase
{
    private const RUN_ID = 'stitch_test_full';

    private const GATE_RUN_ID = 'stitch_test_gate';

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('app/stitch/reports/'.self::RUN_ID));
        File::deleteDirectory(storage_path('app/stitch/reports/'.self::GATE_RUN_ID));

        parent::tearDown();
    }

    public function test_lint_produces_artifacts(): void
    {
        $this->artisan('stitch:lint', ['--run-id' => self::RUN_ID])->assertSuccessful();

        $lint = $this->readReportJson(self::RUN_ID, 'lint.json');
        $this->assertSame(self::RUN_ID, $lint['run_id']);
        $this->assertValidSeverityCounts($lint['counts']);
        $this->assertIsArray($lint['findings']);

        $this->assertFileExists($this->reportPath(self::RUN_ID, 'broken-actions.csv'));
    }

    public function test_fallbacks_produces_artifacts(): void
    {
        $this->artisan('stitch:fallbacks', ['--run-id' => self::RUN_ID])->assertSuccessful();

        $fallbacks = $this->readReportJson(self::RUN_ID, 'fallbacks.json');
        $this->assertSame(self::RUN_ID, $fallbacks['run_id']);
        $this->assertValidSeverityCounts($fallbacks['counts']);
        $this->assertIsInt($fallbacks['checked']);
        $this->assertIsArray($fallbacks['findings']);

        $this->assertFileExists($this->reportPath(self::RUN_ID, 'missing-fallbacks.csv'));
    }

    public function test_contracts_produces_artifacts(): void
    {
        $this->artisan('stitch:contracts', ['--run-id' => self::RUN_ID])->assertSuccessful();

        $contracts = $this->readReportJson(self::RUN_ID, 'contracts.json');
        $this->assertSame(self::RUN_ID, $contracts['run_id']);
        $this->assertValidSeverityCounts($contracts['counts']);
        $this->assertIsInt($contracts['checked']);
        $this->assertIsInt($contracts['skipped']);
        $this->assertIsArray($contracts['findings']);

        $this->assertFileExists($this->reportPath(self::RUN_ID, 'contract-gaps.csv'));
    }

    public function test_map_produces_artifacts(): void
    {
        $this->artisan('stitch:map', ['--run-id' => self::RUN_ID])->assertSuccessful();

        $map = $this->readReportJson(self::RUN_ID, 'map.json');
        $this->assertSame(self::RUN_ID, $map['run_id']);
        $this->assertValidSeverityCounts($map['counts']);
        $this->assertIsInt($map['total_nodes']);
        $this->assertGreaterThan(0, $map['total_nodes']);
        $this->assertIsInt($map['total_edges']);
        $this->assertIsBool($map['truncated']);
        $this->assertIsArray($map['findings']);

        $this->assertFileExists($this->reportPath(self::RUN_ID, 'flow-graph.json'));
        $this->assertFileExists($this->reportPath(self::RUN_ID, 'flow-graph.mmd'));
        $this->assertFileExists($this->reportPath(self::RUN_ID, 'dead-ends.csv'));
    }

    public function test_repair_dry_run_reports_candidates_without_editing_views(): void
    {
        $this->artisan('stitch:lint', ['--run-id' => self::RUN_ID])->assertSuccessful();

        $this->artisan('stitch:repair', ['runId' => self::RUN_ID])->assertSuccessful();

        $repair = $this->readReportJson(self::RUN_ID, 'repair.json');
        $this->assertSame(self::RUN_ID, $repair['run_id']);
        $this->assertSame(self::RUN_ID, $repair['source_run_id']);
        $this->assertFalse($repair['safe'], 'Without --safe, stitch:repair must not write to view files.');
        $this->assertIsInt($repair['candidates']);
        $this->assertIsInt($repair['repaired']);
        $this->assertIsInt($repair['skipped']);
        $this->assertSame($repair['candidates'], $repair['repaired'] + $repair['skipped']);
        $this->assertIsArray($repair['repairs']);

        $this->assertFileExists($this->reportPath(self::RUN_ID, 'repair-log.csv'));
    }

    public function test_report_aggregates_a_full_run(): void
    {
        foreach (['stitch:lint', 'stitch:fallbacks', 'stitch:contracts', 'stitch:map'] as $command) {
            $this->artisan($command, ['--run-id' => self::RUN_ID])->assertSuccessful();
        }
        $this->artisan('stitch:repair', ['runId' => self::RUN_ID])->assertSuccessful();

        // Exit code reflects the verdict (0 = PASS/PASS WITH WARNINGS, 1 = BLOCK RELEASE) — both mean the report ran to completion.
        $exitCode = Artisan::call('stitch:report', ['runId' => self::RUN_ID]);
        $this->assertContains($exitCode, [0, 1]);

        $report = $this->readReportJson(self::RUN_ID, 'report.json');
        $this->assertSame(self::RUN_ID, $report['run_id']);
        $this->assertContains($report['verdict'], ['PASS', 'PASS WITH WARNINGS', 'BLOCK RELEASE']);
        $this->assertValidSeverityCounts($report['counts']);
        $this->assertSame([], $report['missing_sections']);

        foreach (['lint', 'fallbacks', 'contracts', 'map'] as $section) {
            $this->assertArrayHasKey($section, $report['sections']);
            $this->assertNotNull($report['sections'][$section]);
        }

        $this->assertArrayHasKey('exit', $report['landing']);
        $this->assertArrayHasKey('failures', $report['landing']);
        $this->assertNotNull($report['repair']);
        $this->assertIsArray($report['findings']);

        $this->assertFileExists($this->reportPath(self::RUN_ID, 'summary.md'));
        $this->assertFileExists($this->reportPath(self::RUN_ID, 'fix-plan.md'));
    }

    public function test_release_gate_orchestrates_full_suite_and_emits_json(): void
    {
        $exitCode = $this->artisan('stitch:release-gate', [
            '--run-id' => self::GATE_RUN_ID,
            '--json' => true,
        ])
            ->expectsOutputToContain('"run_id": "'.self::GATE_RUN_ID.'"')
            ->run();

        $this->assertContains($exitCode, [0, 1]);

        $report = $this->readReportJson(self::GATE_RUN_ID, 'report.json');
        $this->assertSame(self::GATE_RUN_ID, $report['run_id']);
        $this->assertContains($report['verdict'], ['PASS', 'PASS WITH WARNINGS', 'BLOCK RELEASE']);
        $this->assertValidSeverityCounts($report['counts']);

        foreach (['lint', 'fallbacks', 'contracts', 'map'] as $section) {
            $this->assertArrayHasKey($section, $report['sections']);
            $this->assertNotNull($report['sections'][$section]);
        }

        $this->assertFileExists($this->reportPath(self::GATE_RUN_ID, 'summary.md'));
        $this->assertFileExists($this->reportPath(self::GATE_RUN_ID, 'fix-plan.md'));
    }

    private function reportPath(string $runId, string $filename): string
    {
        return storage_path("app/stitch/reports/{$runId}/{$filename}");
    }

    private function readReportJson(string $runId, string $filename): array
    {
        return json_decode(File::get($this->reportPath($runId, $filename)), true);
    }

    private function assertValidSeverityCounts(array $counts): void
    {
        foreach (['P0', 'P1', 'P2', 'P3'] as $level) {
            $this->assertArrayHasKey($level, $counts);
            $this->assertIsInt($counts[$level]);
            $this->assertGreaterThanOrEqual(0, $counts[$level]);
        }
    }
}
