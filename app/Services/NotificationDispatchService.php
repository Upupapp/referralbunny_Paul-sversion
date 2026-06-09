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
        // Dedup check — scoped to tenant_id when available to prevent theoretical
        // cross-tenant collisions if deal IDs are ever non-UUID (e.g., sequential ints).
        if ($deduplicationKey) {
            $q = Notification::where('deduplication_key', $deduplicationKey)
                             ->where('notifiable_type', $notifiableType)
                             ->where('notifiable_id', $notifiableId);
            // Always scope by tenant; for SA (null tenant) explicitly match NULL rows
            if ($tenantId) {
                $q->where('tenant_id', $tenantId);
            } else {
                $q->whereNull('tenant_id');
            }
            if ($q->exists()) return null;
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
     *
     * When a dedupeSuffix is provided we run a single prefix EXISTS check against
     * the notifications table before loading the admin-list JOIN query.  On a dedup
     * hit (e.g. second partner removal within the same 5-minute bucket) this saves
     * the admin-list JOIN, all per-admin EXISTS checks, and all INSERT attempts —
     * replacing them with one indexed-column EXISTS query.
     *
     * Key pattern checked: "{category}:%:{dedupeSuffix}" — matches any per-admin key
     * written by a previous successful dispatch with the same suffix.
     *
     * Side-effects: after a successful dispatch (non-dedup-hit), internally busts
     * admin bell keys (notif_unread_tenant_admin_{uid}) and all CA badge cache keys
     * via CriticalActionService::invalidateAllAdminBadges. Callsites do NOT need to
     * perform these busts manually after calling this method.
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
        // ── Tenant-level dedup short-circuit ─────────────────────────────────
        // Check if any per-admin notification with this dedup suffix already exists
        // for this tenant before paying for the admin-list JOIN query.
        // LIKE on deduplication_key: "{category}:%:{dedupeSuffix}" — the % matches
        // any admin UUID. This is an indexed prefix scan when the column is indexed.
        if ($dedupeSuffix) {
            $escapedSuffix   = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $dedupeSuffix);
            $escapedCategory = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $category);
            $alreadySent = Notification::where('tenant_id', $tenantId)
                ->where('notifiable_type', 'tenant_admin')
                ->where('deduplication_key', 'like', "{$escapedCategory}:%:{$escapedSuffix}")
                ->exists();
            if ($alreadySent) return;
        }

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

        // invalidateAllAdminBadges busts CA badge keys + bell keys atomically.
        try {
            app(\App\Services\CriticalActionService::class)->invalidateAllAdminBadges($tenantId);
        } catch (\Throwable) {}
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
        // The `users` table is Super Admin–only by architecture (no tenant users here).
        // No status column exists — all rows are active SA accounts.
        $admins = DB::table('users')->select('id')->get();

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

        if ($type === 'tenant_admin') {
            if (!$tenantId) {
                // No workspace context — return empty result to prevent cross-tenant bleed.
                return $q->whereRaw('1=0');
            }
            // Also show tenant-scoped platform notifications (no notifiable_id).
            // Both branches are scoped to $tenantId — without it, an admin who belongs
            // to multiple tenants would see notifications from all their tenants in the
            // bell, and clicking one could navigate to a different tenant's page.
            $q = Notification::where(fn($outer) =>
                $outer->where(fn($q) =>
                    $q->where('notifiable_type', 'tenant_admin')
                       ->where('notifiable_id', $id)
                       ->where('tenant_id', $tenantId)
                )->orWhere(fn($q) =>
                    $q->where('tenant_id', $tenantId)->whereNull('notifiable_type')
                )
            )->whereNull('archived_at')
             ->where(fn($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
        }

        return $q;
    }
}
