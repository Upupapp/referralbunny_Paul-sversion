<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DealAmountUpdated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string  $leadId,
        public readonly string  $leadName,
        public readonly string  $tenantId,
        public readonly string  $resellerName,
        public readonly float   $oldAmount,
        public readonly float   $newAmount,
        public readonly ?string $updatedByName  = null,
        public readonly ?string $resellerEmail  = null,
        public readonly ?string $resellerId     = null,
    ) {}
}
