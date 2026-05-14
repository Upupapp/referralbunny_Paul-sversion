<?php

namespace App\Jobs;

use App\Services\GoogleCalendarService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DeleteGoogleCalendarEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 30;

    public function __construct(
        public readonly string $entityType,
        public readonly string $entityId,
    ) {}

    public function handle(GoogleCalendarService $svc): void
    {
        $svc->deleteEntityEvents($this->entityType, $this->entityId);
    }
}
