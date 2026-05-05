<?php

namespace App\Http\Controllers;

use App\Models\MessageReminderState;
use App\Models\MessageThread;
use App\Models\TenantMembership;
use App\Services\Messaging\MessageReminderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MessageReminderController extends Controller
{
    public function active(MessageReminderService $service): JsonResponse
    {
        [$type, $id, $tenantId] = $this->resolveUser();
        if (!$type || !$id) return response()->json(['reminders' => [], 'count' => 0]);

        $reminders = $service->getActiveForUser($type, $id, $tenantId);

        return response()->json([
            'reminders' => $reminders->map(fn($r) => [
                'id'                => $r->id,
                'reminder_type'     => $r->reminder_type,
                'priority'          => $r->priority,
                'title'             => $r->title,
                'body'              => $r->body,
                'action_url'        => $r->action_url,
                'deduplication_key' => $r->deduplication_key,
                'thread_id'         => $r->thread_id,
                'deal_id'           => $r->deal_id,
                'reminder_count'    => $r->reminder_count,
                'metadata'          => $r->metadata,
            ]),
            'count' => $reminders->count(),
        ]);
    }

    public function snooze(Request $request, MessageReminderService $service): JsonResponse
    {
        $request->validate([
            'deduplication_key' => 'required|string',
            'minutes'           => 'required|integer|min:15|max:10080',
        ]);

        [$type, $id] = $this->resolveUser();
        if (!$type || !$id) return response()->json(['ok' => false], 403);

        $service->snooze($request->deduplication_key, $type, $id, (int) $request->minutes);
        return response()->json(['ok' => true]);
    }

    public function resolve(Request $request, MessageReminderService $service): JsonResponse
    {
        $request->validate(['deduplication_key' => 'required|string']);
        [$type, $id] = $this->resolveUser();
        if (!$type || !$id) return response()->json(['ok' => false], 403);
        $service->resolve($request->deduplication_key, $type, $id);
        return response()->json(['ok' => true]);
    }

    public function dismiss(Request $request, MessageReminderService $service): JsonResponse
    {
        $request->validate(['deduplication_key' => 'required|string']);
        [$type, $id] = $this->resolveUser();
        if (!$type || !$id) return response()->json(['ok' => false], 403);
        $service->dismiss($request->deduplication_key, $type, $id);
        return response()->json(['ok' => true]);
    }

    public function suggestedReplies(Request $request, MessageReminderService $service): JsonResponse
    {
        $type = $request->input('reminder_type', 'unread_message');
        return response()->json(['suggestions' => $service->getSuggestedReplies($type)]);
    }

    public function needsReply(Request $request): JsonResponse
    {
        [$type, $id, $tenantId] = $this->resolveUser();
        if (!$type || !$id || !$tenantId) return response()->json(['threads' => []]);

        $threadIds = MessageReminderState::forUser($type, $id)
            ->whereIn('status', ['active', 'snoozed'])
            ->whereNotNull('thread_id')
            ->pluck('thread_id');

        $threads = MessageThread::whereIn('id', $threadIds)
            ->where('tenant_id', $tenantId)
            ->with('reseller:id,name,is_anonymous')
            ->orderByDesc('last_message_at')
            ->get()
            ->map(fn($t) => [
                'id'                   => $t->id,
                'reseller_name'        => ($t->reseller?->is_anonymous)
                    ? 'Anonymous Reseller' : ($t->reseller?->name ?? 'Unknown'),
                'last_message_preview' => $t->last_message_preview,
                'last_message_at'      => $t->last_message_at?->diffForHumans(),
                'admin_unread'         => $t->admin_unread,
                'reseller_unread'      => $t->reseller_unread,
            ]);

        return response()->json(['threads' => $threads]);
    }

    private function resolveUser(): array
    {
        if (Auth::guard('web')->check()) {
            return ['super_admin', (string) Auth::guard('web')->id(), null];
        }
        if (Auth::guard('tenant')->check()) {
            $user     = Auth::guard('tenant')->user();
            $tenantId = TenantMembership::where('tenant_user_id', $user->id)
                ->where('status', 'active')->value('tenant_id');
            return ['tenant_admin', (string) $user->id, $tenantId];
        }
        if (Auth::guard('reseller')->check()) {
            $user = Auth::guard('reseller')->user();
            return ['reseller', (string) $user->id, (string) $user->tenant_id];
        }
        return [null, null, null];
    }
}
