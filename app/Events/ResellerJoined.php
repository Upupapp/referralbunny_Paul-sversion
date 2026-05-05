<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ResellerJoined
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $resellerId,
        public readonly string $resellerName,
        public readonly string $resellerEmail,
        public readonly string $tenantId,
    ) {}
}
