<?php

namespace App\Console\Commands;

use App\Services\EmailDigestService;
use Illuminate\Console\Command;

class SendEmailDigests extends Command
{
    protected $signature   = 'email:send-digests';
    protected $description = 'Send all due batched email digests (run every 30 minutes via scheduler)';

    public function handle(EmailDigestService $digestService): int
    {
        $this->info('Processing due email digests…');
        [$sent, $failed] = $digestService->sendDue();
        $this->info("Done. Sent: {$sent}  Failed: {$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
