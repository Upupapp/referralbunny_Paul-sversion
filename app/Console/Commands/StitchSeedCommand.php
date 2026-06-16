<?php

namespace App\Console\Commands;

use Database\Seeders\ReferralBunnyQaSeeder;
use Illuminate\Console\Command;

/**
 * Seeds the STITCH Phase 2 QA role/tenant matrix (13 sessions across
 * qa-tenant-a/b/c + 1 platform super admin) via ReferralBunnyQaSeeder.
 */
class StitchSeedCommand extends Command
{
    protected $signature = 'stitch:seed';

    protected $description = 'Seed the STITCH QA role/tenant matrix (qa-tenant-a/b/c + super admin)';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('stitch:seed MUST NOT be run on production.');

            return self::FAILURE;
        }

        $this->call('db:seed', ['--class' => ReferralBunnyQaSeeder::class]);

        $this->table(
            ['Role', 'Tenant', 'Email', 'Login Route', 'Dashboard Route'],
            collect(config('stitch.qa_matrix', []))->map(fn (array $s) => [
                $s['role'], $s['tenant_id'] ?? '—', $s['email'], $s['login_route'], $s['dashboard_route'],
            ])->all()
        );

        return self::SUCCESS;
    }
}
