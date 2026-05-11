<?php

namespace App\Console\Commands;

use App\Models\Lead;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PurgeArchivedLeads extends Command
{
    protected $signature   = 'leads:purge-archived';
    protected $description = 'Permanently delete soft-deleted (archived) leads older than 10 days';

    public function handle(): int
    {
        $cutoff = Carbon::now()->subDays(10);

        $leads = Lead::onlyTrashed()
            ->where('deleted_at', '<', $cutoff)
            ->get();

        $count = 0;
        foreach ($leads as $lead) {
            try {
                $lead->forceDelete();
                $count++;
            } catch (\Throwable $e) {
                Log::error("leads:purge-archived — failed to purge lead {$lead->id}: {$e->getMessage()}");
            }
        }

        Log::info("leads:purge-archived: purged {$count} leads archived before {$cutoff->toDateTimeString()}.");
        $this->info("Purged {$count} archived deal(s).");

        return self::SUCCESS;
    }
}
