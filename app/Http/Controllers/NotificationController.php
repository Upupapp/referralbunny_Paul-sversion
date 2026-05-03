<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Notification::orderByDesc('created_at');
        if ($request->filled('tenant_id')) $query->where('tenant_id', $request->tenant_id);
        if ($request->filled('priority'))  $query->where('priority', $request->priority);
        if ($request->filled('category'))  $query->where('category', $request->category);
        if ($request->boolean('unread'))   $query->unread();
        return response()->json($query->limit(200)->get());
    }

    public function store(Request $request, NotificationService $service): JsonResponse
    {
        $data = $request->validate([
            'category'   => 'required|in:billing,tenant_health,analytics,messaging,system,growth,approvals',
            'type'       => 'required|in:info,warning,action_required,system_alert',
            'priority'   => 'required|in:low,medium,high,critical',
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
        return response()->json($notification);
    }

    public function update(Request $request, Notification $notification): JsonResponse
    {
        if ($request->has('is_read'))      $notification->update(['is_read'      => $request->boolean('is_read')]);
        if ($request->has('is_dismissed')) $notification->update(['is_dismissed' => $request->boolean('is_dismissed')]);
        return response()->json($notification);
    }

    public function destroy(Notification $notification): JsonResponse
    {
        $notification->delete();
        return response()->json(['message' => 'Deleted.']);
    }

    public function markAllRead(Request $request, NotificationService $service): JsonResponse
    {
        $service->markAllRead($request->tenant_id ?? null);
        return response()->json(['message' => 'All marked as read.']);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $q = Notification::unread();
        if ($request->filled('tenant_id')) $q->where('tenant_id', $request->tenant_id);
        return response()->json(['count' => $q->count()]);
    }
}
