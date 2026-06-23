<?php

namespace Tests\Feature;

use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * Meta-test: every app/ file that resolves a Program by tenant_id must
 * either go through ProgramPolicy (real $this->authorize( call) or carry
 * its own App\Support\ProtectedTenants::isProtected() guard.
 *
 * This exists because the lgu-ids guardrail chain (ProgramPolicy ->
 * RequestFormController -> Referrer/PartnerProgramController ->
 * PublicProgramController -> DefaultProgramMigrationService) found a new
 * unguarded side door in four separate manual sweeps, one at a time. This
 * test automates that sweep so a fifth side door fails CI immediately
 * instead of waiting for another QA pass to notice it.
 *
 * If this test fails on a new file, the fix is the file needs a guard --
 * not to add it to ALLOWLISTED_FILES. That list exists only because a
 * one-time audit (this commit) confirmed every currently-matching file is
 * already correctly guarded.
 */
class ProgramProtectedTenantGuardCoverageTest extends TestCase
{
    /**
     * Files already audited and confirmed to carry a real guard (either
     * $this->authorize() against ProgramPolicy, or their own
     * ProtectedTenants::isProtected() check). Verified as part of this test
     * being written -- see the commit that introduced this file.
     */
    private const ALLOWLISTED_FILES = [
        'app/Http/Controllers/Web/PartnerProgramController.php',
        'app/Http/Controllers/Web/ProgramActionItemController.php',
        'app/Http/Controllers/Web/ProgramContractController.php',
        'app/Http/Controllers/Web/ProgramController.php',
        'app/Http/Controllers/Web/ProgramMembershipController.php',
        'app/Http/Controllers/Web/ProgramOfferController.php',
        'app/Http/Controllers/Web/ProgramWorkspaceController.php',
        'app/Http/Controllers/Web/PublicProgramController.php',
        'app/Http/Controllers/Web/ReferrerProgramController.php',
        'app/Http/Controllers/Web/RequestFormController.php',
        'app/Services/Programs/DefaultProgramMigrationService.php',
    ];

    /**
     * Matches both direct static calls (Program::forTenant(/Program::where('tenant_id')
     * AND relationship-closure resolution (->whereHas('program', fn($q) =>
     * $q->where('tenant_id', ...))), which exists live in
     * Referrer/PartnerProgramController and contains no `Program::` token at
     * all -- a purely Program::-keyed regex would silently miss it.
     */
    private const RESOLVES_BY_TENANT = '/Program::forTenant\(|Program::where\(\s*[\'"]tenant_id|whereHas\(\s*[\'"]program/';

    private const HAS_REAL_GUARD = '/\$this->authorize\(|ProtectedTenants::isProtected\(/';

    public function test_every_program_resolving_file_is_guarded_or_allowlisted(): void
    {
        $basePath = base_path();
        $finder = (new Finder())
            ->files()
            ->name('*.php')
            ->in($basePath . '/app')
            ->notPath('Policies') // ProgramPolicy itself defines the guard, doesn't need to call it
            ->notPath('Models');  // scopeForTenant() definitions, not resolution call sites

        $resolvesByTenant = self::RESOLVES_BY_TENANT;
        $hasRealGuard     = self::HAS_REAL_GUARD;

        $unguarded = [];

        foreach ($finder as $file) {
            $relativePath = str_replace($basePath . '\\', '', str_replace($basePath . '/', '', $file->getRealPath()));
            $relativePath = str_replace('\\', '/', $relativePath);
            $contents = $file->getContents();

            if (!preg_match($resolvesByTenant, $contents)) {
                continue;
            }

            if (preg_match($hasRealGuard, $contents)) {
                continue;
            }

            if (!in_array($relativePath, self::ALLOWLISTED_FILES, true)) {
                $unguarded[] = $relativePath;
            }
        }

        $this->assertEmpty($unguarded, "Found Program-resolving file(s) with no ProgramPolicy::authorize() call and no "
            . "ProtectedTenants::isProtected() guard -- this is exactly the class of bug that let lgu-ids leak through "
            . "four separate side doors. Add a guard, then add the file to ALLOWLISTED_FILES once confirmed: "
            . implode(', ', $unguarded));
    }

    public function test_allowlisted_files_still_exist_and_are_still_guarded(): void
    {
        $basePath = base_path();

        foreach (self::ALLOWLISTED_FILES as $relativePath) {
            $fullPath = $basePath . '/' . $relativePath;
            $this->assertFileExists($fullPath, "Allowlisted file no longer exists: {$relativePath} -- remove it from ALLOWLISTED_FILES.");

            $contents = file_get_contents($fullPath);
            $this->assertMatchesRegularExpression(
                self::HAS_REAL_GUARD,
                $contents,
                "Allowlisted file {$relativePath} no longer contains a guard -- its protection may have been removed."
            );
        }
    }

    /**
     * Test of the test: proves RESOLVES_BY_TENANT actually flags a
     * relationship-closure resolution pattern (no `Program::` token at all),
     * and that an unguarded version of that same snippet would NOT be waved
     * through by HAS_REAL_GUARD. A prior version of this regex only matched
     * `Program::` call sites and would have missed this pattern entirely --
     * this test exists so that gap can't silently reopen.
     */
    public function test_regex_catches_relationship_closure_resolution_pattern(): void
    {
        $unguardedSnippet = <<<'PHP'
            $memberships = SomeModel::with('program')
                ->whereHas('program', fn($q) => $q->where('tenant_id', $tenantId))
                ->get();
            PHP;

        $this->assertMatchesRegularExpression(self::RESOLVES_BY_TENANT, $unguardedSnippet,
            'RESOLVES_BY_TENANT should flag a whereHas(\'program\', ...) relationship-closure pattern.');

        $this->assertDoesNotMatchRegularExpression(self::HAS_REAL_GUARD, $unguardedSnippet,
            'HAS_REAL_GUARD should not false-positive on a snippet with no real guard.');

        $guardedSnippet = "abort_if(\\App\\Support\\ProtectedTenants::isProtected(\$tenantId), 404);\n" . $unguardedSnippet;

        $this->assertMatchesRegularExpression(self::HAS_REAL_GUARD, $guardedSnippet,
            'HAS_REAL_GUARD should match once a real ProtectedTenants::isProtected( guard is present.');
    }
}
