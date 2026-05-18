<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when any invited user completes account activation.
 *
 * Invite types:
 *   'tenant_user' — Admin/Manager accepted a TenantInvitation
 *   'reseller'    — Referrer completed setup via setup_token
 *   'partner'     — Partner completed setup via setup_token
 *
 * The listener HandleInviteAccepted:
 *   - Records ActivityLog / AuditLog
 *   - Notifies the inviter (if known and different from acceptee)
 *   - Notifies tenant admins (for types not already covered by other events)
 *   - Does NOT duplicate notifications already sent by ResellerJoined
 *
 * acceptedAt is stored as an ISO 8601 string so the event serializes safely
 * when HandleInviteAccepted is dispatched to the queue.
 */
class InviteAcceptedEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string  $tenantId,
        public readonly string  $inviteType,        // 'tenant_user' | 'reseller' | 'partner'
        public readonly string  $acceptedUserId,    // TenantUser.id | Reseller.id | Partner.id
        public readonly string  $acceptedUserName,
        public readonly string  $acceptedUserEmail,
        public readonly string  $acceptedRole,      // admin|manager|referrer|partner|member|viewer
        public readonly ?string $invitedById,       // TenantUser.id who sent the invite
        public readonly ?string $inviteId,          // TenantInvitation.id or similar
        public readonly ?string $relatedDealId,     // for deal-specific Partner invites
        public readonly ?string $relatedDealName,
        public readonly string  $acceptedAt,        // ISO 8601 string — safe for queue serialization
    ) {}
}
