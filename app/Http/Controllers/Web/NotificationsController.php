<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Tenant;
use App\Services\NotificationDispatchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationsController extends Controller
{
    public function index(Request $request, string $tenantId)
    {
        $tenant = Tenant::findOrFail($tenantId);
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
                ->update(['is_read' => true]);
        }
        return response()->json(['ok' => true]);
    }

    private function findUserNotification(string $notificationId): Notification
    {
        [$type, $id] = $this->resolveCurrentUser();
        return Notification::where('id', $notificationId)
            ->where('notifiable_type', $type)
            ->where('notifiable_id', $id)
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
        return [null, null];
    }
}
