<?php

namespace App\Console\Commands;

use App\Services\IndexingService;
use Illuminate\Console\Command;

class ReindexSearch extends Command
{
    protected $signature   = 'search:reindex {type? : Entity type to reindex (omit for all)}';
    protected $description = 'Rebuild the global search index';

    public function handle(IndexingService $indexing): void
    {
        $type = $this->argument('type');

        if ($type) {
            $this->info("Reindexing {$type}...");
            $count = $indexing->reindexType($type);
            $this->info("Done — {$count} records indexed.");
            return;
        }

        $this->info('Reindexing all entity types...');
        $results = $indexing->reindexAll();

        foreach ($results as $entityType => $count) {
            $this->line("  {$entityType}: {$count} records");
        }

        $this->info('Total: ' . array_sum($results) . ' records indexed.');
    }
}
