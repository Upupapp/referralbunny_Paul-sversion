<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DealStageMoved
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string  $leadId,
        public readonly string  $leadName,
        public readonly string  $tenantId,
        public readonly string  $resellerName,
        public readonly string  $fromStage,
        public readonly string  $toStage,
        public readonly float   $dealValue,
        public readonly ?string $movedByName  = null,
        public readonly ?string $resellerEmail = null,
        public readonly ?string $resellerId   = null,
    ) {}
}
