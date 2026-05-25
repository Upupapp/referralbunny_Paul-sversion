<?php

namespace App\Console\Commands;

use App\Services\LguIdsReferrerDealNoteReminderService;
use Illuminate\Console\Command;

class SendLguIdsReferrerNoteReminders extends Command
{
    protected $signature   = 'lgu-ids:send-referrer-note-reminders';
    protected $description = 'Send weekly deal-note reminder to LGU IDS referrers with no-note active deals';

    public function handle(LguIdsReferrerDealNoteReminderService $service): int
    {
        $this->info('[LGU IDS] Running weekly referrer deal-note reminder…');

        $result = $service->runWeeklyReminder();

        $this->info("  Sent:    {$result['sent']}");
        $this->info("  Skipped: {$result['skipped']}");
        $this->info("  Failed:  {$result['failed']}");

        if ($result['failed'] > 0) {
            $this->warn('Some reminders failed — check logs for details.');
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
