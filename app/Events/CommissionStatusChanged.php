<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CommissionStatusChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string  $leadId,
        public readonly string  $leadName,
        public readonly string  $tenantId,
        public readonly string  $resellerName,
        public readonly string  $newStatus,   // pending | locked | paid
        public readonly float   $dealValue,
        public readonly ?string $resellerEmail = null,
    ) {}
}
