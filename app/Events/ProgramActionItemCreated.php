<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class ProgramActionItemCreated
{
    use Dispatchable;

    public function __construct(
        public readonly string $actionItemId,
    ) {}
}
