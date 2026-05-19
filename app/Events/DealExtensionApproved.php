<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DealExtensionApproved
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string  $leadId,
        public readonly string  $leadName,
        public readonly string  $tenantId,
        public readonly string  $resellerName,
        public readonly int     $approvedDays,
        public readonly int     $newDaysLeft,
        public readonly ?string $adminNote      = null,
        public readonly ?string $resellerEmail  = null,
        public readonly ?string $resellerId     = null,
    ) {}
}
