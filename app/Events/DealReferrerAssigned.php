<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired whenever a Referrer is assigned or reassigned to a Deal (lead).
 * Covers: new_assignment, reassignment, pending_referrer_assignment.
 * Does NOT fire for initial deal creation — DealCreated covers that case.
 */
class DealReferrerAssigned
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string  $leadId,
        public readonly string  $leadName,
        public readonly string  $tenantId,
        public readonly string  $resellerName,
        public readonly string  $stage,
        public readonly float   $dealValue,
        // 'new_assignment' | 'reassignment' | 'pending_referrer_assignment'
        public readonly string  $assignmentType  = 'new_assignment',
        public readonly ?string $resellerEmail   = null,
        public readonly ?string $resellerId      = null,
        public readonly ?string $oldResellerName = null,
        public readonly ?string $assignedByName  = null,
        public readonly ?string $assignedByRole  = null,
    ) {}
}
