<?php

namespace App\Console\Commands;

use App\Services\Messaging\MessageReminderService;
use Illuminate\Console\Command;

class CheckMessageReminders extends Command
{
    protected $signature   = 'messages:check-reminders';
    protected $description = 'Scan unanswered message threads and create smart reminder states';

    public function handle(MessageReminderService $service): int
    {
        $this->info('Checking message threads for reminders…');
        $service->checkAllTenants();
        $this->info('Done.');
        return self::SUCCESS;
    }
}
