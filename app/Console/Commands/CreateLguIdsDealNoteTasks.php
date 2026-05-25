<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Services\LguIds\LguIdsDealNoteTaskService;
use Illuminate\Console\Command;

/**
 * Backfill note-reminder tasks for existing LGU IDS deals that are in a
 * target stage (Presentation / Contract Stage / Paid) with no notes yet.
 *
 * Safe to re-run — idempotent per referrer+deal combination.
 */
class CreateLguIdsDealNoteTasks extends Command
{
    protected $signature = 'lgu-ids:create-note-tasks
                            {--dry-run : Preview which deals/referrers would get tasks without creating them}
                            {--no-notify : Skip in-app notifications}
                            {--no-email : Skip email notifications}
                            {--deal-id= : Only process a single deal by ID}
                            {--limit=0 : Maximum number of deals to process (0 = all)}';

    protected $description = 'Create referrer note-reminder tasks for existing LGU IDS deals with no notes';

    public function handle(LguIdsDealNoteTaskService $service): int
    {
        $dryRun   = $this->option('dry-run');
        $notify   = ! $this->option('no-notify');
        $email    = ! $this->option('no-email');
        $dealId   = $this->option('deal-id');
        $limit    = (int) $this->option('limit');

        $tenant = $service->getLguIdsTenant();
        if (! $tenant) {
            $this->error('[LGU IDS] lgu-ids tenant not found — aborting.');
            return self::FAILURE;
        }

        $this->info('[LGU IDS] Creating referrer note tasks for existing deals…');
        if ($dryRun) {
            $this->warn('  DRY RUN — no tasks will be created.');
        }

        // Build query
        $query = Lead::where('tenant_id', $tenant->id)
            ->whereNull('deleted_at')
            ->whereIn('stage', LguIdsDealNoteTaskService::TARGET_STAGES)
            ->whereIn('status', ['active', 'expiring'])
            ->when($dealId, fn ($q) => $q->where('id', $dealId))
            ->when($limit > 0, fn ($q) => $q->limit($limit))
            ->orderBy('created_at');

        $deals = $query->get();

        if ($deals->isEmpty()) {
            $this->info('  No eligible deals found.');
            return self::SUCCESS;
        }

        $this->info("  Found {$deals->count()} candidate deal(s) in target stages.");

        $processed   = 0;
        $skipped     = 0;
        $taskCreated = 0;
        $errors      = 0;

        foreach ($deals as $deal) {
            // Check notes in service — skip if already has notes
            if (! $service->dealHasNoNotes($deal->id)) {
                $skipped++;
                if ($dryRun) {
                    $this->line("  SKIP  [{$deal->stage}] {$deal->name} — has notes");
                }
                continue;
            }

            $referrers = $service->getReferrersForDeal($deal);
            if ($referrers->isEmpty()) {
                $skipped++;
                if ($dryRun) {
                    $this->line("  SKIP  [{$deal->stage}] {$deal->name} — no active referrers found");
                }
                continue;
            }

            foreach ($referrers as $referrer) {
                $hasOpen = $service->hasOpenTask($deal->id, $referrer->id);

                if ($dryRun) {
                    $flag = $hasOpen ? '[HAS TASK]' : '[WOULD CREATE]';
                    $this->line("  {$flag}  [{$deal->stage}] {$deal->name} → {$referrer->name}");
                    if (! $hasOpen) {
                        $taskCreated++;
                    }
                    continue;
                }

                if ($hasOpen) {
                    $skipped++;
                    continue;
                }

                try {
                    $task = $service->createForReferrer($deal, $referrer, $tenant, 'backfill', $notify, $email);
                    if ($task !== null) {
                        $taskCreated++;
                        $this->line("  CREATED  [{$deal->stage}] {$deal->name} → {$referrer->name}");
                    }
                } catch (\Throwable $e) {
                    $errors++;
                    $this->error("  ERROR  [{$deal->stage}] {$deal->name} → {$referrer->name}: {$e->getMessage()}");
                }
            }

            $processed++;
        }

        $this->newLine();
        $this->info("  Deals processed : {$processed}");
        $this->info("  Tasks created   : {$taskCreated}");
        $this->info("  Skipped         : {$skipped}");
        if ($errors > 0) {
            $this->warn("  Errors          : {$errors}");
        }

        if ($dryRun) {
            $this->warn('  DRY RUN complete — no changes were made.');
        }

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }
}
