<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class ProgramContractStatusChanged
{
    use Dispatchable;

    public function __construct(
        public readonly string $contractId,
        public readonly string $newStatus,
    ) {}
}
