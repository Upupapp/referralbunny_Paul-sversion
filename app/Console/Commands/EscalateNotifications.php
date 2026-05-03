<?php

namespace App\Console\Commands;

use App\Services\NotificationService;
use Illuminate\Console\Command;

class EscalateNotifications extends Command
{
    protected $signature   = 'notifications:escalate';
    protected $description = 'Escalate unresolved high/critical notifications';

    public function handle(NotificationService $notifications): void
    {
        $notifications->escalate();
        $this->info('Escalation check complete.');
    }
}
