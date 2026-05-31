<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class NotificationsController extends Controller
{
    public function index(Request $request, string $tenantId)
    {
        $tenant = Tenant::findOrFail($tenantId);

        [$type, $id] = $this->resolveCurrentUser();
        if ($type && $id) {
            Notification::where('notifiable_type', $type)
                ->where('notifiable_id', $id)
                ->where('is_read', false)
                ->where(fn($q) => $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id'))
                ->update(['is_read' => true]);
            $this->bustBadgeCaches($type, $id, $tenantId);
        }

        return view('tenant.notifications.index', compact('tenant'));
    }

    public function markRead(Request $request, string $tenantId, string $notificationId)
    {
        $this->findUserNotification($notificationId)->update(['is_read' => true]);
        return response()->json(['ok' => true]);
    }

    public function archive(Request $request, string $tenantId, string $notificationId)
    {
        $this->findUserNotification($notificationId)->update(['archived_at' => now()]);
        return response()->json(['ok' => true]);
    }

    public function markAllRead(Request $request, string $tenantId)
    {
        [$type, $id] = $this->resolveCurrentUser();
        if ($type && $id) {
            Notification::where('notifiable_type', $type)
                ->where('notifiable_id', $id)
                ->where('is_read', false)
                ->where(fn($q) =>
                    $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id')
                )
                ->update(['is_read' => true]);
            $this->bustBadgeCaches($type, $id, $tenantId);
        }
        return response()->json(['ok' => true]);
    }

    public function markReadForPartner(Request $request, string $notificationId)
    {
        $this->findUserNotification($notificationId)->update(['is_read' => true]);
        if (Auth::guard('partner')->check()) {
            $partnerId = (string) Auth::guard('partner')->id();
            Cache::forget('partner_notif_unread:' . $partnerId);
            Cache::forget("notif_unread_partner_{$partnerId}");
        }
        return response()->json(['ok' => true]);
    }

    private function bustBadgeCaches(string $type, string $id, string $tenantId): void
    {
        Cache::forget("notif_unread_{$type}_{$id}");
        if ($type === 'partner') {
            Cache::forget("partner_notif_unread:{$id}");
        }
        if ($type === 'reseller') {
            Cache::forget("ca_rs_suppressed:{$id}");
        } elseif (in_array($type, ['tenant_admin', 'super_admin'])) {
            Cache::forget("ca_badge_{$tenantId}_{$id}");
            Cache::forget("ca_badge_suppressed:{$tenantId}:{$id}");
            Cache::forget("ca_badge_urgent:{$tenantId}:{$id}");
        }
    }

    private function findUserNotification(string $notificationId): Notification
    {
        [$type, $id] = $this->resolveCurrentUser();
        $tenantId = request()->route('tenantId');

        if (!$tenantId && $type === 'partner' && Auth::guard('partner')->check()) {
            $tenantId = Auth::guard('partner')->user()->tenant_id;
        }

        if ($type !== 'super_admin' && !$tenantId) {
            abort(403, 'Tenant context required.');
        }

        return Notification::where('id', $notificationId)
            ->where('notifiable_type', $type)
            ->where('notifiable_id', $id)
            ->when($tenantId, fn($q) =>
                $q->where(fn($q) => $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id'))
            )
            ->firstOrFail();
    }

    // Guard order: web first so SA-on-tenant-page resolves as super_admin
    private function resolveCurrentUser(): array
    {
        if (Auth::guard('web')->check()) {
            return ['super_admin', (string) Auth::guard('web')->id()];
        }
        if (Auth::guard('tenant')->check()) {
            return ['tenant_admin', (string) Auth::guard('tenant')->id()];
        }
        if (Auth::guard('reseller')->check()) {
            return ['reseller', (string) Auth::guard('reseller')->id()];
        }
        if (Auth::guard('partner')->check()) {
            return ['partner', (string) Auth::guard('partner')->id()];
        }
        return [null, null];
    }
}
