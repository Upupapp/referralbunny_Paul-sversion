<?php

namespace App\Console\Commands;

use Database\Seeders\ReferralBunnyQaSeeder;
use Illuminate\Console\Command;

class StitchSeedCommand extends Command
{
    protected $signature   = 'stitch:seed';
    protected $description = 'Seed the QA role/tenant matrix for STITCH runtime tests (non-production only).';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('stitch:seed MUST NOT be run in production.');
            return self::FAILURE;
        }

        $this->call('db:seed', ['--class' => ReferralBunnyQaSeeder::class]);

        $matrix = config('stitch.qa_matrix', []);

        $this->newLine();
        $this->info('QA Matrix — ' . count($matrix) . ' sessions seeded:');
        $this->table(
            ['#', 'Role', 'Tenant', 'Email'],
            collect($matrix)->values()->map(fn($s, $i) => [
                $i + 1,
                $s['role'],
                $s['tenant_id'] ?? '(platform)',
                $s['email'],
            ])->all()
        );
        $this->newLine();
        $this->line('Shared password: <comment>QaPassword123!</comment>');
        $this->line('Run Playwright:  <comment>npx playwright test</comment>');
        $this->newLine();

        return self::SUCCESS;
    }
}
