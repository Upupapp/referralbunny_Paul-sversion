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

        // Auto-mark all as read when the notifications page is opened
        [$type, $id] = $this->resolveCurrentUser();
        if ($type && $id) {
            Notification::where('notifiable_type', $type)
                ->where('notifiable_id', $id)
                ->where('is_read', false)
                ->where(fn($q) => $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id'))
                ->update(['is_read' => true]);
            Cache::forget("notif_unread_{$type}_{$id}");
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
        }
        return response()->json(['ok' => true]);
    }

    public function markReadForPartner(Request $request, string $notificationId)
    {
        $this->findUserNotification($notificationId)->update(['is_read' => true]);
        if (Auth::guard('partner')->check()) {
            Cache::forget('partner_notif_unread:' . Auth::guard('partner')->id());
        }
        return response()->json(['ok' => true]);
    }

    private function findUserNotification(string $notificationId): Notification
    {
        [$type, $id] = $this->resolveCurrentUser();
        $tenantId = request()->route('tenantId');

        // For partner guard, derive tenant_id from the authenticated partner user
        if (!$tenantId && $type === 'partner' && Auth::guard('partner')->check()) {
            $tenantId = Auth::guard('partner')->user()->tenant_id;
        }

        return Notification::where('id', $notificationId)
            ->where('notifiable_type', $type)
            ->where('notifiable_id', $id)
            ->when($tenantId, fn($q) =>
                $q->where(fn($q) => $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id'))
            )
            ->firstOrFail();
    }

    private function resolveCurrentUser(): array
    {
        if (Auth::guard('tenant')->check()) {
            return ['tenant_admin', (string) Auth::guard('tenant')->id()];
        }
        if (Auth::guard('web')->check()) {
            return ['super_admin', (string) Auth::guard('web')->id()];
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
