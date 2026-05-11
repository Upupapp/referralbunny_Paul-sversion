<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ApplyLguIdsDefaultAmounts extends Command
{
    protected $signature = 'deals:apply-lguids-default-amounts
                            {--dry-run : Preview changes without writing to the database}';

    protected $description = 'Backfill LGU IDS deals that have no deal amount with the ₱4,000,000 default and mark them for confirmation.';

    private const TENANT_ID     = 'lgu-ids';
    private const DEFAULT_AMOUNT = 4_000_000.00;

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');

        $this->info('LGU IDS Default Amount Backfill' . ($isDryRun ? ' [DRY RUN]' : ''));
        $this->line('Tenant: ' . self::TENANT_ID);
        $this->newLine();

        // Find deals with missing/null/zero/negative amounts
        $leads = DB::table('leads')
            ->where('tenant_id', self::TENANT_ID)
            ->where(function ($q) {
                $q->whereNull('deal_value')
                  ->orWhere('deal_value', '<=', 0);
            })
            ->select('id', 'name', 'deal_value', 'data', 'stage', 'status')
            ->get();

        $scanned = $leads->count();
        $updated = 0;
        $skipped = 0;
        $errors  = 0;

        $this->line("Deals scanned: {$scanned}");

        if ($scanned === 0) {
            $this->info('No deals with missing amounts found. Nothing to do.');
            return 0;
        }

        foreach ($leads as $lead) {
            try {
                $data = json_decode($lead->data ?? '{}', true) ?? [];

                // Skip if already processed and confirmed/updated
                $status = $data['amount_confirmation_status'] ?? null;
                if (in_array($status, ['confirmed', 'updated'])) {
                    $this->line("  SKIP [{$lead->id}] {$lead->name} — already {$status}");
                    $skipped++;
                    continue;
                }

                if ($isDryRun) {
                    $this->line("  WOULD UPDATE [{$lead->id}] {$lead->name} (deal_value: " . ($lead->deal_value ?? 'null') . ')');
                    $updated++;
                    continue;
                }

                $data['amount_defaulted']           = true;
                $data['amount_confirmation_status'] = 'pending';
                $data['amount_default_reason']      = 'LGU IDS default applied during backfill — no deal amount was present.';

                DB::table('leads')->where('id', $lead->id)->update([
                    'deal_value' => self::DEFAULT_AMOUNT,
                    'data'       => json_encode($data),
                    'updated_at' => now(),
                ]);

                // Log to lead_history
                try {
                    DB::table('lead_history')->insert([
                        'id'         => (string) \Illuminate\Support\Str::uuid(),
                        'lead_id'    => $lead->id,
                        'tenant_id'  => self::TENANT_ID,
                        'action'     => 'LGU IDS default deal amount of ₱4,000,000 applied during system backfill — no amount was present.',
                        'type'       => 'amount',
                        'category'   => 'financial',
                        'actor_name' => 'System (Backfill)',
                        'actor_role' => 'system',
                        'new_values' => json_encode(['deal_value' => self::DEFAULT_AMOUNT, 'amount_source' => 'lgu_ids_default_backfill']),
                        'date'       => now()->toDateString(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } catch (\Throwable) {}

                $this->line("  UPDATED [{$lead->id}] {$lead->name}");
                $updated++;
            } catch (\Throwable $e) {
                $this->error("  ERROR [{$lead->id}] {$lead->name}: " . $e->getMessage());
                $errors++;
            }
        }

        $this->newLine();
        $this->info('Summary:');
        $this->line("  Scanned : {$scanned}");
        $this->line("  Updated : {$updated}" . ($isDryRun ? ' (dry run — no writes)' : ''));
        $this->line("  Skipped : {$skipped}");
        $this->line("  Errors  : {$errors}");

        if ($isDryRun) {
            $this->warn('Dry run complete — no changes written. Run without --dry-run to apply.');
        } else {
            $this->info('Backfill complete.');
        }

        return $errors > 0 ? 1 : 0;
    }
}
