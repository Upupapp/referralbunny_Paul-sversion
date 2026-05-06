<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SetLguIdsMaxPlanCommand extends Command
{
    protected $signature = 'referralbunny:set-lgu-ids-max
                            {--dry-run : Preview the changes without saving anything}';

    protected $description = 'Grant LGU IDS tenant a 1-year Max plan subscription (internal billing, no payment required)';

    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');

        if ($isDryRun) {
            $this->warn('[DRY RUN] No changes will be saved.');
        }

        // ── Locate LGU IDS tenant ─────────────────────────────────────────────
        $tenant = Tenant::where('slug', 'lgu-ids')
            ->orWhere('slug', 'lgu_ids')
            ->orWhere('slug', 'lguids')
            ->orWhere(function ($q) {
                $q->whereRaw("LOWER(name) LIKE '%lgu%ids%'");
            })
            ->first();

        if (! $tenant) {
            $this->error('LGU IDS tenant not found. Checked slugs: lgu-ids, lgu_ids, lguids and name LIKE "%lgu%ids%".');
            return self::FAILURE;
        }

        $this->info("Tenant found: {$tenant->name} (id={$tenant->id}, slug={$tenant->slug})");

        // ── Locate Max plan ───────────────────────────────────────────────────
        $plan = Plan::where('plan_key', 'max')->first()
            ?? Plan::whereRaw("LOWER(name) = 'max'")->first();

        if (! $plan) {
            $this->error('Max plan not found in the plans table. Run migration_v35.sql first.');
            return self::FAILURE;
        }

        $this->info("Plan found: {$plan->name} (id={$plan->id})");

        // ── Compute subscription dates ────────────────────────────────────────
        $start = Carbon::today();
        $end   = Carbon::today()->addYear();

        $this->line('');
        $this->line('  Subscription details:');
        $this->line("    Plan            : {$plan->name}");
        $this->line("    Status          : active");
        $this->line("    Billing status  : internal");
        $this->line("    Billing cycle   : yearly");
        $this->line("    Start date      : {$start->toDateString()}");
        $this->line("    End date        : {$end->toDateString()}");
        $this->line("    Payment required: false");
        $this->line("    Auto-renew      : false");
        $this->line('');

        if ($isDryRun) {
            $this->warn('[DRY RUN] Stopping here. No changes saved.');
            return self::SUCCESS;
        }

        // ── Upsert subscription ───────────────────────────────────────────────
        $subscription = Subscription::where('tenant_id', $tenant->id)->first();

        $data = [
            'plan_id'               => $plan->id,
            'status'                => 'active',
            'billing_status'        => 'internal',
            'billing_cycle'         => 'yearly',
            'start_date'            => $start->toDateString(),
            'subscription_end_date' => $end->toDateString(),
            'next_billing_date'     => $end->toDateString(),
            'trial_end_date'        => null,
            'canceled_at'           => null,
            'payment_required'      => false,
            'auto_renew'            => false,
            'manual_note'           => 'LGU IDS granted 1-year Max subscription by platform owner instruction.',
            'assigned_by'           => 'artisan_command',
        ];

        if ($subscription) {
            $subscription->update($data);
            $this->line("  Updated existing subscription (id={$subscription->id}).");
        } else {
            $subscription = Subscription::create(array_merge(['tenant_id' => $tenant->id], $data));
            $this->line("  Created new subscription (id={$subscription->id}).");
        }

        // ── Audit log ─────────────────────────────────────────────────────────
        ActivityLog::create([
            'tenant_id' => $tenant->id,
            'user_id'   => null,
            'action'    => 'tenant_subscription_manually_set_to_max',
            'entity'    => 'subscription',
            'entity_id' => $subscription->id,
            'metadata'  => [
                'plan'           => 'Max',
                'start'          => $start->toDateString(),
                'end'            => $end->toDateString(),
                'billing_status' => 'internal',
                'source'         => 'artisan_command',
            ],
        ]);

        $this->info("✓ LGU IDS tenant set to Max plan. Subscription active until {$end->toDateString()}.");

        return self::SUCCESS;
    }
}
