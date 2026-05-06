<?php

namespace App\Console\Commands;

use App\Services\InvitationReminderService;
use Illuminate\Console\Command;

class SendInvitationReminders extends Command
{
    protected $signature   = 'invitations:send-reminders';
    protected $description = 'Send pending invitation reminders to invitees and inviters';

    public function handle(InvitationReminderService $service): int
    {
        $inviteeSent = $service->processInviteeReminders();
        $inviterSent = $service->processInviterReminders();

        $this->info("Invitation reminders sent — invitee: {$inviteeSent}, inviter: {$inviterSent}");

        return Command::SUCCESS;
    }
}
