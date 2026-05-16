<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Services\NotificationDispatchService;
use App\Services\NotificationService;
use App\Services\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Notification::orderByDesc('created_at');

        // Derive tenant from authenticated context, never from user input
        $tenantId = TenantContext::id();
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        } elseif (!TenantContext::isSuperAdmin()) {
            abort(403, 'Tenant context required.');
        }

        if ($request->filled('priority'))  $query->where('priority', $request->priority);
        if ($request->filled('category'))  $query->where('category', $request->category);
        if ($request->boolean('unread') || $request->input('is_read') === 'false') $query->unread();
        return response()->json($query->limit((int) ($request->input('limit', 200)))->get());
    }

    /**
     * Return notifications for the currently authenticated user.
     * Auth-aware: super admin, tenant admin, or reseller.
     */
    public function mine(Request $request): JsonResponse
    {
        [$type, $id, $tenantId] = $this->resolveCurrentUser();

        if (!$type || !$id) {
            return response()->json([]);
        }

        $q = NotificationDispatchService::queryForUser($type, $id, $tenantId)
            ->orderByDesc('created_at');

        if ($request->boolean('unread') || $request->input('is_read') === 'false') {
            $q->where('is_read', false)->where('is_dismissed', false);
        }

        if ($request->filled('category')) {
            $q->where('category', $request->category);
        }

        if ($request->filled('priority')) {
            $q->where('priority', $request->priority);
        }

        $limit  = min((int) ($request->input('limit', 50)), 200);
        $offset = (int) ($request->input('offset', 0));

        $unreadCount = (clone $q)->where('is_read', false)->where('is_dismissed', false)->count();
        $total       = (clone $q)->count();
        $items       = $q->skip($offset)->take($limit)->get()->map(fn($n) => [
            'id'            => $n->id,
            'category'      => $n->category,
            'priority'      => $n->priority,
            'title'         => $n->display_title,
            'message'       => $n->message,
            'action_url'    => $n->action_url,
            'action_label'  => $n->action_label ?? 'View',
            'is_read'       => $n->is_read,
            'is_dismissed'  => $n->is_dismissed,
            'metadata'      => $n->metadata_json,
            'created_at'    => $n->created_at?->toIso8601String(),
            'created_ago'   => $n->created_at?->diffForHumans(),
            'priority_color'=> $n->priority_color,
        ]);

        return response()->json([
            'items'        => $items,
            'total'        => $total,
            'unread_count' => $unreadCount,
        ]);
    }

    public function mineUnreadCount(): JsonResponse
    {
        [$type, $id, $tenantId] = $this->resolveCurrentUser();
        if (!$type || !$id) return response()->json(['count' => 0]);

        $count = NotificationDispatchService::queryForUser($type, $id, $tenantId)
            ->where('is_read', false)
            ->where('is_dismissed', false)
            ->count();

        return response()->json(['count' => $count]);
    }

    public function markMineRead(Request $request): JsonResponse
    {
        [$type, $id] = $this->resolveCurrentUser();
        if (!$type || !$id) return response()->json(['ok' => false]);

        Notification::where('notifiable_type', $type)
            ->where('notifiable_id', $id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        // Bust the partner nav badge cache so the sidebar reflects immediately
        if ($type === 'partner') {
            Cache::forget("partner_notif_unread:{$id}");
        }

        return response()->json(['ok' => true]);
    }

    public function store(Request $request, NotificationService $service): JsonResponse
    {
        $data = $request->validate([
            'category'   => 'required|string',
            'type'       => 'required|in:info,warning,action_required,system_alert',
            'priority'   => 'required|in:low,normal,medium,high,critical,urgent',
            'message'    => 'required|string',
            'tenant_id'  => 'nullable|string|exists:tenants,id',
            'action_url' => 'nullable|string',
            'channel'    => 'nullable|in:in_app,email,sms,all',
            'metadata'   => 'nullable|array',
        ]);

        $notification = $service->send(
            category:  $data['category'],
            type:      $data['type'],
            priority:  $data['priority'],
            message:   $data['message'],
            tenantId:  $data['tenant_id']  ?? null,
            actionUrl: $data['action_url'] ?? null,
            channel:   $data['channel']    ?? 'in_app',
            metadata:  $data['metadata']   ?? [],
        );

        return response()->json($notification, 201);
    }

    public function show(Notification $notification): JsonResponse
    {
        $this->authorizeNotificationAccess($notification);
        return response()->json($notification);
    }

    public function update(Request $request, Notification $notification): JsonResponse
    {
        $this->authorizeNotificationAccess($notification);
        if ($request->has('is_read'))      $notification->update(['is_read'      => $request->boolean('is_read')]);
        if ($request->has('is_dismissed')) $notification->update(['is_dismissed' => $request->boolean('is_dismissed')]);
        if ($request->has('archived_at'))  $notification->update(['archived_at'  => $request->input('archived_at') ? now() : null]);
        return response()->json($notification);
    }

    public function destroy(Notification $notification): JsonResponse
    {
        $this->authorizeNotificationAccess($notification);
        $notification->delete();
        return response()->json(['message' => 'Deleted.']);
    }

    private function authorizeNotificationAccess(Notification $notification): void
    {
        [$type, $id] = $this->resolveCurrentUser();
        // SA can access all; others can only access their own notifications
        if (!TenantContext::isSuperAdmin()) {
            if ((string) $notification->notifiable_id !== (string) $id
                || $notification->notifiable_type !== $type) {
                abort(403, 'You do not have access to this notification.');
            }
        }
    }

    /** Mark a single notification as read — called from web routes (tenant/reseller portals). */
    public function markNotifRead(Request $request, string $notifId): \Illuminate\Http\JsonResponse
    {
        [$type, $id] = $this->resolveCurrentUser();
        if (!$type || !$id) return response()->json(['ok' => false], 403);

        Notification::where('id', $notifId)
            ->where('notifiable_type', $type)
            ->where('notifiable_id', $id)
            ->update(['is_read' => true]);

        return response()->json(['ok' => true]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        [$type, $id, $tenantId] = $this->resolveCurrentUser();
        if (!$type || !$id) return response()->json(['ok' => false], 401);

        Notification::where('notifiable_type', $type)
            ->where('notifiable_id', $id)
            ->where('is_read', false)
            ->where('is_dismissed', false)
            ->update(['is_read' => true]);

        return response()->json(['message' => 'All marked as read.']);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $q = Notification::unread();
        $tenantId = TenantContext::id();
        if ($tenantId) $q->where('tenant_id', $tenantId);
        return response()->json(['count' => $q->count()]);
    }

    /** Resolve (notifiable_type, notifiable_id, tenant_id) for the current session. */
    private function resolveCurrentUser(): array
    {
        if (Auth::guard('web')->check()) {
            return ['super_admin', (string) Auth::guard('web')->id(), null];
        }
        if (Auth::guard('tenant')->check()) {
            $user     = Auth::guard('tenant')->user();
            $tenantId = request()->route('tenantId')
                ?? \App\Models\TenantMembership::where('tenant_user_id', $user->id)
                    ->where('status', 'active')->value('tenant_id');
            return ['tenant_admin', (string) $user->id, $tenantId];
        }
        if (Auth::guard('reseller')->check()) {
            $user = Auth::guard('reseller')->user();
            return ['reseller', (string) $user->id, $user->tenant_id];
        }
        if (Auth::guard('partner')->check()) {
            $user = Auth::guard('partner')->user();
            return ['partner', (string) $user->id, $user->tenant_id];
        }
        return [null, null, null];
    }
}
