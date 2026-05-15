<?php

namespace App\Services;

use App\Models\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * User-specific in-app notification dispatch with deduplication.
 *
 * The existing NotificationService handles tenant-wide/platform notifications.
 * This service handles per-user notifications with dedup key safety.
 */
class NotificationDispatchService
{
    /**
     * Dispatch a notification to a single user.
     * Returns null if deduplication key already exists (silent skip).
     */
    public function dispatch(
        string  $category,
        string  $priority,
        string  $title,
        string  $body,
        string  $notifiableType,
        string  $notifiableId,
        ?string $tenantId          = null,
        ?string $actionUrl         = null,
        ?string $actionLabel       = null,
        ?string $deduplicationKey  = null,
        array   $metadata          = [],
    ): ?Notification {
        // Dedup check
        if ($deduplicationKey) {
            $exists = Notification::where('deduplication_key', $deduplicationKey)->exists();
            if ($exists) return null;
        }

        return Notification::create([
            'id'                => (string) Str::uuid(),
            'notifiable_type'   => $notifiableType,
            'notifiable_id'     => $notifiableId,
            'tenant_id'         => $tenantId,
            'category'          => $category,
            'type'              => 'info',
            'priority'          => $priority,
            'title'             => $title,
            'message'           => $body,
            'action_url'        => $actionUrl,
            'action_label'      => $actionLabel,
            'channel'           => 'in_app',
            'deduplication_key' => $deduplicationKey,
            'metadata_json'     => $metadata,
            'is_read'           => false,
            'is_dismissed'      => false,
            'sent_at'           => now(),
        ]);
    }

    /**
     * Dispatch to all active tenant admins (owners + admins) in a tenant.
     */
    public function dispatchToTenantAdmins(
        string  $tenantId,
        string  $category,
        string  $priority,
        string  $title,
        string  $body,
        ?string $actionUrl    = null,
        ?string $actionLabel  = null,
        ?string $dedupeSuffix = null,
        array   $metadata     = [],
    ): void {
        $admins = DB::table('tenant_memberships as tm')
            ->join('tenant_users as u', 'tm.tenant_user_id', '=', 'u.id')
            ->where('tm.tenant_id', $tenantId)
            ->where('tm.status', 'active')
            ->whereIn('tm.role', ['owner', 'admin', 'manager'])
            ->select('u.id')
            ->get();

        foreach ($admins as $admin) {
            $key = $dedupeSuffix ? "{$category}:{$admin->id}:{$dedupeSuffix}" : null;
            $this->dispatch(
                category:          $category,
                priority:          $priority,
                title:             $title,
                body:              $body,
                notifiableType:    'tenant_admin',
                notifiableId:      (string) $admin->id,
                tenantId:          $tenantId,
                actionUrl:         $actionUrl,
                actionLabel:       $actionLabel,
                deduplicationKey:  $key,
                metadata:          $metadata,
            );
        }
    }

    /**
     * Dispatch to a specific reseller.
     */
    public function dispatchToReseller(
        string  $resellerId,
        string  $tenantId,
        string  $category,
        string  $priority,
        string  $title,
        string  $body,
        ?string $actionUrl    = null,
        ?string $actionLabel  = null,
        ?string $dedupeSuffix = null,
        array   $metadata     = [],
    ): void {
        $key = $dedupeSuffix ? "{$category}:{$resellerId}:{$dedupeSuffix}" : null;
        $this->dispatch(
            category:         $category,
            priority:         $priority,
            title:            $title,
            body:             $body,
            notifiableType:   'reseller',
            notifiableId:     $resellerId,
            tenantId:         $tenantId,
            actionUrl:        $actionUrl,
            actionLabel:      $actionLabel,
            deduplicationKey: $key,
            metadata:         $metadata,
        );
    }

    /**
     * Dispatch to a specific Partner (partner_users table).
     */
    public function dispatchToPartner(
        string  $partnerId,
        string  $tenantId,
        string  $category,
        string  $priority,
        string  $title,
        string  $body,
        ?string $actionUrl    = null,
        ?string $actionLabel  = null,
        ?string $dedupeSuffix = null,
        array   $metadata     = [],
    ): void {
        $key = $dedupeSuffix ? "{$category}:{$partnerId}:{$dedupeSuffix}" : null;
        $this->dispatch(
            category:         $category,
            priority:         $priority,
            title:            $title,
            body:             $body,
            notifiableType:   'partner',
            notifiableId:     $partnerId,
            tenantId:         $tenantId,
            actionUrl:        $actionUrl,
            actionLabel:      $actionLabel,
            deduplicationKey: $key,
            metadata:         $metadata,
        );
    }

    /**
     * Dispatch to all super admins (users table).
     */
    public function dispatchToSuperAdmins(
        string  $category,
        string  $priority,
        string  $title,
        string  $body,
        ?string $actionUrl    = null,
        ?string $actionLabel  = null,
        ?string $dedupeSuffix = null,
        array   $metadata     = [],
    ): void {
        $admins = DB::table('users')->select('id')->where('status', '!=', 'suspended')->get();

        foreach ($admins as $admin) {
            $key = $dedupeSuffix ? "{$category}:{$admin->id}:{$dedupeSuffix}" : null;
            $this->dispatch(
                category:         $category,
                priority:         $priority,
                title:            $title,
                body:             $body,
                notifiableType:   'super_admin',
                notifiableId:     (string) $admin->id,
                actionUrl:        $actionUrl,
                actionLabel:      $actionLabel,
                deduplicationKey: $key,
                metadata:         $metadata,
            );
        }
    }

    /**
     * Return query builder for a user's active in-app notifications.
     */
    public static function queryForUser(string $type, string $id, ?string $tenantId = null)
    {
        $q = Notification::where('notifiable_type', $type)
                         ->where('notifiable_id', $id)
                         ->whereNull('archived_at')
                         ->where(fn($q) =>
                             $q->whereNull('expires_at')->orWhere('expires_at', '>', now())
                         );

        // Super admins also see tenant-null platform-wide notifications
        if ($type === 'super_admin') {
            $q = Notification::where(fn($q) =>
                $q->where(fn($q) =>
                    $q->where('notifiable_type', 'super_admin')->where('notifiable_id', $id)
                )->orWhere(fn($q) =>
                    $q->whereNull('notifiable_type')->whereNull('tenant_id')
                )
            )->whereNull('archived_at')
             ->where(fn($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
        }

        if ($tenantId && $type === 'tenant_admin') {
            // Also show tenant-scoped platform notifications (no notifiable_id)
            $q = Notification::where(fn($outer) =>
                $outer->where(fn($q) =>
                    $q->where('notifiable_type', 'tenant_admin')->where('notifiable_id', $id)
                )->orWhere(fn($q) =>
                    $q->where('tenant_id', $tenantId)->whereNull('notifiable_type')
                )
            )->whereNull('archived_at')
             ->where(fn($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
        }

        return $q;
    }
}
