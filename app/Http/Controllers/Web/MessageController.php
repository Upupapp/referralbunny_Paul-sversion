<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\MessageThread;
use App\Models\Partner;
use App\Models\PartnerMessage;
use App\Models\PartnerThread;
use App\Models\Reseller;
use App\Models\ThreadMessage;
use App\Services\Messaging\MessageReminderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MessageController extends Controller
{
    public function threads($tenantId)
    {
        try {
            $threads = MessageThread::where('tenant_id', $tenantId)
                ->with('reseller:id,name,email')
                ->orderByDesc('last_message_at')
                ->get()
                ->map(fn($t) => [
                    'id'                   => $t->id,
                    'reseller_id'          => $t->reseller_id,
                    'reseller_name'        => $t->reseller?->name ?? 'Unknown',
                    'reseller_email'       => $t->reseller?->email ?? '',
                    'last_message_preview' => $t->last_message_preview,
                    'last_message_at'      => $t->last_message_at?->diffForHumans(),
                    'admin_unread'         => $t->admin_unread,
                ]);
        } catch (\Throwable) {
            $threads = collect();
        }

        return response()->json($threads);
    }

    public function startThread(Request $request, $tenantId)
    {
        $request->validate([
            'reseller_id' => 'required|string',
            'body'        => 'required|string|max:5000',
        ]);

        try {
            $reseller = Reseller::where('tenant_id', $tenantId)
                ->where('id', $request->reseller_id)
                ->firstOrFail();

            $thread = MessageThread::firstOrCreate(
                ['tenant_id' => $tenantId, 'reseller_id' => $reseller->id],
                ['id' => (string) Str::uuid()]
            );

            $isReseller = auth('reseller')->check();
            $senderType = $isReseller ? 'reseller' : 'admin';
            $senderId   = $isReseller
                ? (string) auth('reseller')->id()
                : (string) (auth('tenant')->id() ?? auth('web')->id());
            $senderName = $this->senderName();

            $msg = ThreadMessage::create([
                'id'          => (string) Str::uuid(),
                'thread_id'   => $thread->id,
                'tenant_id'   => $tenantId,
                'sender_type' => $senderType,
                'sender_id'   => $senderId,
                'sender_name' => $senderName,
                'body'        => $request->body,
            ]);

            if ($isReseller) {
                $thread->update([
                    'last_message_at'      => now(),
                    'last_message_preview' => Str::limit($request->body, 80),
                    'admin_unread'         => DB::raw('admin_unread + 1'),
                ]);
                // Notify admins of new message from Referrer
                try {
                    app(\App\Services\NotificationDispatchService::class)->dispatchToTenantAdmins(
                        tenantId:     $tenantId,
                        category:     'tenant_workspace',
                        priority:     'normal',
                        title:        'New message from Referrer',
                        body:         $senderName . ' sent a message: "' . Str::limit($request->body, 80) . '"',
                        actionUrl:    url("/tenant/{$tenantId}/messages"),
                        actionLabel:  'View Message',
                        dedupeSuffix: 'thread:' . $thread->id . ':reseller_msg:' . now()->format('YmdH'),
                        metadata:     ['sender_name' => $senderName, 'thread_id' => $thread->id],
                    );
                } catch (\Throwable) {}
            } else {
                $thread->update([
                    'last_message_at'      => now(),
                    'last_message_preview' => Str::limit($request->body, 80),
                    'reseller_unread'      => DB::raw('reseller_unread + 1'),
                ]);
                // Notify the Referrer of new message from admin
                try {
                    $notifReseller = $reseller; // already resolved above
                    if ($notifReseller) {
                        app(\App\Services\NotificationDispatchService::class)->dispatch(
                            category:         'tenant_workspace',
                            priority:         'normal',
                            title:            'New message from your workspace',
                            body:             $senderName . ' sent you a message: "' . Str::limit($request->body, 80) . '"',
                            notifiableType:   'reseller',
                            notifiableId:     (string) $notifReseller->id,
                            tenantId:         $tenantId,
                            actionUrl:        url("/reseller/{$tenantId}/messages"),
                            actionLabel:      'View Message',
                            deduplicationKey: 'thread:' . $thread->id . ':admin_msg:' . now()->format('YmdH'),
                            metadata:         ['sender_name' => $senderName, 'thread_id' => $thread->id],
                        );
                    }
                } catch (\Throwable) {}
            }

            return response()->json([
                'thread_id' => $thread->id,
                'message'   => $this->formatMessage($msg),
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Referrer not found.'], 404);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('startThread error', [
                'tenant_id'   => $tenantId,
                'reseller_id' => $request->reseller_id,
                'error'       => $e->getMessage(),
            ]);
            return response()->json(['message' => 'Could not send message. Please try again.'], 500);
        }
    }

    public function threadMessages($tenantId, $threadId)
    {
        try {
            $q = MessageThread::where('tenant_id', $tenantId)->where('id', $threadId);

            // Resellers may only access their own thread — prevent IDOR
            if (auth('reseller')->check()) {
                $q->where('reseller_id', auth('reseller')->id());
            }

            $thread = $q->with('reseller:id,name,email')->firstOrFail();

            ThreadMessage::where('thread_id', $threadId)
                ->where('sender_type', 'reseller')
                ->where('is_read', false)
                ->update(['is_read' => true, 'read_at' => now()]);

            $thread->update(['admin_unread' => 0]);

            $messages = ThreadMessage::where('thread_id', $threadId)
                ->orderBy('created_at')
                ->get()
                ->map(fn($m) => $this->formatMessage($m));

            return response()->json([
                'thread'   => [
                    'id'             => $thread->id,
                    'reseller_id'    => $thread->reseller_id,
                    'reseller_name'  => $thread->reseller?->name ?? 'Unknown',
                    'reseller_email' => $thread->reseller?->email ?? '',
                ],
                'messages' => $messages,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json(['message' => 'Conversation not found.'], 404);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('threadMessages error', [
                'tenant_id' => $tenantId,
                'thread_id' => $threadId,
                'error'     => $e->getMessage(),
            ]);
            return response()->json(['message' => 'Failed to load messages.'], 500);
        }
    }

    public function fetchMessages($tenantId, $threadId)
    {
        try {
            $thread = MessageThread::where('tenant_id', $tenantId)
                ->where('id', $threadId)
                ->firstOrFail();

            $messages = ThreadMessage::where('thread_id', $threadId)
                ->orderBy('created_at')
                ->get()
                ->map(fn($m) => $this->formatMessage($m));

            return response()->json(['messages' => $messages]);
        } catch (\Throwable) {
            return response()->json(['messages' => []]);
        }
    }

    public function sendMessage(Request $request, $tenantId, $threadId)
    {
        $request->validate(['body' => 'required|string|max:5000']);

        try {
            $thread = MessageThread::where('tenant_id', $tenantId)
                ->where('id', $threadId)
                ->firstOrFail();

            $isReseller = Auth::guard('reseller')->check();
            $senderType = $isReseller ? 'reseller' : 'admin';
            $senderId   = $isReseller
                ? (string) Auth::guard('reseller')->id()
                : (string) (Auth::guard('tenant')->id() ?? Auth::guard('web')->id());
            $senderName = $this->senderName();

            // Resolve any active reminders for this thread (best-effort, never crash send)
            try {
                if (Auth::guard('tenant')->check()) {
                    app(MessageReminderService::class)->resolveForThread($threadId, 'tenant_admin', (string) Auth::guard('tenant')->id());
                } elseif (Auth::guard('reseller')->check()) {
                    app(MessageReminderService::class)->resolveForThread($threadId, 'reseller', (string) Auth::guard('reseller')->id());
                }
            } catch (\Throwable) {}

            $msg = ThreadMessage::create([
                'id'          => (string) Str::uuid(),
                'thread_id'   => $threadId,
                'tenant_id'   => $tenantId,
                'sender_type' => $senderType,
                'sender_id'   => $senderId,
                'sender_name' => $senderName,
                'body'        => $request->body,
            ]);

            if ($isReseller) {
                $thread->update([
                    'last_message_at'      => now(),
                    'last_message_preview' => Str::limit($request->body, 80),
                    'admin_unread'         => DB::raw('admin_unread + 1'),
                ]);
                // Notify tenant admins that the Referrer replied
                try {
                    app(\App\Services\NotificationDispatchService::class)->dispatchToTenantAdmins(
                        tenantId:     $tenantId,
                        category:     'tenant_workspace',
                        priority:     'normal',
                        title:        'New message from Referrer',
                        body:         $senderName . ' sent a message: "' . Str::limit($request->body, 80) . '"',
                        actionUrl:    url("/tenant/{$tenantId}/messages"),
                        actionLabel:  'View Message',
                        dedupeSuffix: 'thread:' . $threadId . ':reseller_msg:' . now()->format('YmdH'),
                        metadata:     ['sender_name' => $senderName, 'thread_id' => $threadId],
                    );
                } catch (\Throwable) {}
            } else {
                $thread->update([
                    'last_message_at'      => now(),
                    'last_message_preview' => Str::limit($request->body, 80),
                    'reseller_unread'      => DB::raw('reseller_unread + 1'),
                ]);
                // Notify the Referrer that admin sent a message
                try {
                    $reseller = Reseller::find($thread->reseller_id);
                    if ($reseller) {
                        app(\App\Services\NotificationDispatchService::class)->dispatch(
                            category:         'tenant_workspace',
                            priority:         'normal',
                            title:            'New message from your workspace',
                            body:             $senderName . ' sent you a message: "' . Str::limit($request->body, 80) . '"',
                            notifiableType:   'reseller',
                            notifiableId:     (string) $reseller->id,
                            tenantId:         $tenantId,
                            actionUrl:        url("/reseller/{$tenantId}/messages"),
                            actionLabel:      'View Message',
                            deduplicationKey: 'thread:' . $threadId . ':admin_msg:' . now()->format('YmdH'),
                            metadata:         ['sender_name' => $senderName, 'thread_id' => $threadId],
                        );
                    }
                } catch (\Throwable) {}
            }

            return response()->json($this->formatMessage($msg));
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Conversation not found.'], 404);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('sendMessage error', [
                'tenant_id' => $tenantId,
                'thread_id' => $threadId,
                'error'     => $e->getMessage(),
            ]);
            return response()->json(['message' => 'Could not send message. Please try again.'], 500);
        }
    }

    /**
     * Broadcast a message to one or many recipients across different role types.
     * Referrers  → MessageThread + ThreadMessage (they see it in their Messages inbox)
     * Partners   → PartnerThread + PartnerMessage (they see it in partner portal Messages)
     * Admins/Managers → in-app notification (platform-side, no dedicated DM table)
     * Contacts   → in-app notification (external contacts receive notifications)
     */
    public function broadcastMessage(Request $request, $tenantId)
    {
        $data = $request->validate([
            'recipients'   => 'required|array|min:1|max:100',
            'recipients.*' => 'required|string',  // "type:id" format e.g. "reseller:uuid"
            'body'         => 'required|string|max:5000',
            'subject'      => 'nullable|string|max:200',
        ]);

        $senderName = $this->senderName();
        $senderId   = (string) (Auth::guard('tenant')->id() ?? Auth::guard('web')->id() ?? 'system');
        $body       = $data['body'];
        $sent       = 0;
        $errors     = [];

        foreach ($data['recipients'] as $recipientToken) {
            [$type, $id] = array_pad(explode(':', $recipientToken, 2), 2, null);
            if (!$type || !$id) continue;

            try {
                match ($type) {
                    'reseller' => $this->sendToReseller($tenantId, $id, $body, $senderName, $senderId),
                    'partner'  => $this->sendToPartner($tenantId, $id, $body, $senderName, $senderId),
                    'admin', 'manager', 'owner' => $this->notifyTenantUser($tenantId, $id, $body, $senderName),
                    'contact'  => $this->notifyContact($tenantId, $id, $body, $senderName),
                    default    => null,
                };
                $sent++;
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('broadcastMessage failed for recipient', [
                    'tenant_id' => $tenantId, 'type' => $type, 'id' => $id, 'error' => $e->getMessage(),
                ]);
                $errors[] = $type . ':' . substr($id, 0, 8);
            }
        }

        if ($sent === 0) {
            return response()->json(['error' => 'Could not send to any recipients. Please try again.'], 500);
        }

        $msg = 'Message sent to ' . $sent . ' recipient' . ($sent > 1 ? 's' : '') . '.';
        if (!empty($errors)) {
            $msg .= ' Failed for ' . count($errors) . '.';
        }

        return response()->json(['success' => true, 'message' => $msg, 'sent' => $sent]);
    }

    // ── Partner inbox (admin reads + replies) ────────────────────────────────

    public function partnerThreads(string $tenantId): \Illuminate\Http\JsonResponse
    {
        $threads = PartnerThread::where('tenant_id', $tenantId)
            ->with('partner:id,first_name,last_name,email')
            ->orderByDesc('last_message_at')
            ->get()
            ->map(fn($t) => [
                'id'             => $t->id,
                'thread_type'    => $t->thread_type ?? 'deal',
                'deal_id'        => $t->deal_id,
                'partner_id'     => $t->partner_id,
                'partner_name'   => $t->partner
                    ? (trim(($t->partner->first_name ?? '') . ' ' . ($t->partner->last_name ?? '')) ?: $t->partner->email)
                    : 'Partner',
                'partner_email'  => $t->partner?->email ?? '',
                'last_preview'   => $t->last_message_preview,
                'last_at'        => $t->last_message_at?->diffForHumans(),
                'admin_unread'   => $t->admin_unread ?? 0,
            ]);

        return response()->json($threads);
    }

    public function partnerThreadMessages(string $tenantId, string $threadId): \Illuminate\Http\JsonResponse
    {
        $thread = PartnerThread::where('tenant_id', $tenantId)->findOrFail($threadId);

        // Mark partner messages as read for admin
        $thread->update(['admin_unread' => 0]);

        PartnerMessage::where('thread_id', $threadId)
            ->where('sender_type', 'partner')
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);

        $partner = Partner::find($thread->partner_id);

        $messages = PartnerMessage::where('thread_id', $threadId)
            ->orderBy('created_at')
            ->get()
            ->map(fn($m) => [
                'id'              => $m->id,
                'sender_type'     => $m->sender_type,
                'sender_name'     => $m->sender_name,
                'body'            => $m->body,
                'created_at'      => $m->created_at?->diffForHumans() ?? 'just now',
                'created_at_full' => $m->created_at?->format('M j, Y g:i A') ?? '',
            ]);

        return response()->json([
            'thread'   => [
                'id'           => $thread->id,
                'thread_type'  => $thread->thread_type ?? 'deal',
                'deal_id'      => $thread->deal_id,
                'partner_id'   => $thread->partner_id,
                'partner_name' => $partner
                    ? (trim(($partner->first_name ?? '') . ' ' . ($partner->last_name ?? '')) ?: $partner->email)
                    : 'Partner',
            ],
            'messages' => $messages,
        ]);
    }

    public function replyToPartnerThread(Request $request, string $tenantId, string $threadId): \Illuminate\Http\JsonResponse
    {
        $request->validate(['body' => 'required|string|max:5000']);

        $thread = PartnerThread::where('tenant_id', $tenantId)->findOrFail($threadId);

        $senderName = $this->senderName();
        $senderId   = (string) (Auth::guard('tenant')->id() ?? Auth::guard('web')->id());

        $message = PartnerMessage::create([
            'thread_id'   => $threadId,
            'tenant_id'   => $tenantId,
            'deal_id'     => $thread->deal_id,
            'sender_type' => 'admin',
            'sender_id'   => $senderId,
            'sender_name' => $senderName,
            'body'        => $request->body,
            'is_read'     => false,
        ]);

        $thread->update([
            'last_message_at'      => now(),
            'last_message_preview' => Str::limit($request->body, 80),
            'partner_unread'       => DB::raw('partner_unread + 1'),
        ]);

        // Notify the partner
        try {
            $partner = Partner::find($thread->partner_id);
            if ($partner) {
                app(\App\Services\NotificationDispatchService::class)->dispatch(
                    category:         'tenant_workspace',
                    priority:         'normal',
                    title:            'New message from your workspace',
                    body:             $senderName . ' replied: "' . Str::limit($request->body, 80) . '"',
                    notifiableType:   'partner',
                    notifiableId:     (string) $partner->id,
                    tenantId:         $tenantId,
                    actionUrl:        url('/partner/messages'),
                    actionLabel:      'View Message',
                    deduplicationKey: 'partner_reply:' . $threadId . ':' . now()->format('YmdH'),
                    metadata:         ['sender_name' => $senderName, 'thread_id' => $threadId],
                );
            }
        } catch (\Throwable) {}

        return response()->json([
            'id'              => $message->id,
            'sender_type'     => $message->sender_type,
            'sender_name'     => $message->sender_name,
            'body'            => $message->body,
            'created_at'      => 'just now',
            'created_at_full' => now()->format('M j, Y g:i A'),
        ]);
    }

    private function sendToReseller(string $tenantId, string $resellerId, string $body, string $senderName, string $senderId): void
    {
        $reseller = Reseller::where('tenant_id', $tenantId)->where('id', $resellerId)->firstOrFail();

        $thread = MessageThread::firstOrCreate(
            ['tenant_id' => $tenantId, 'reseller_id' => $reseller->id],
            ['id' => (string) Str::uuid()]
        );

        ThreadMessage::create([
            'id'          => (string) Str::uuid(),
            'thread_id'   => $thread->id,
            'tenant_id'   => $tenantId,
            'sender_type' => 'admin',
            'sender_id'   => $senderId,
            'sender_name' => $senderName,
            'body'        => $body,
        ]);

        $thread->update([
            'last_message_at'      => now(),
            'last_message_preview' => Str::limit($body, 80),
            'reseller_unread'      => DB::raw('reseller_unread + 1'),
        ]);

        // Notify the Referrer via in-app
        app(\App\Services\NotificationDispatchService::class)->dispatch(
            category:         'tenant_workspace',
            priority:         'normal',
            title:            'New message from your workspace',
            body:             $senderName . ' sent you a message: "' . Str::limit($body, 80) . '"',
            notifiableType:   'reseller',
            notifiableId:     (string) $reseller->id,
            tenantId:         $tenantId,
            actionUrl:        url("/reseller/{$tenantId}/messages"),
            actionLabel:      'View Message',
            deduplicationKey: 'broadcast:reseller:' . $reseller->id . ':msg:' . now()->format('YmdH'),
            metadata:         ['sender_name' => $senderName, 'thread_id' => $thread->id],
        );
    }

    private function sendToPartner(string $tenantId, string $partnerId, string $body, string $senderName, string $senderId): void
    {
        $partner = \App\Models\Partner::where('tenant_id', $tenantId)->where('id', $partnerId)->firstOrFail();

        // Reuse existing direct thread (no deal context) if one exists
        $thread = \App\Models\PartnerThread::where('tenant_id', $tenantId)
            ->where('partner_id', $partner->id)
            ->whereNull('deal_id')
            ->first();

        if (!$thread) {
            $thread = \App\Models\PartnerThread::create([
                'id'          => (string) Str::uuid(),
                'tenant_id'   => $tenantId,
                'partner_id'  => $partner->id,
                'deal_id'     => null,
                'reseller_id' => null,
            ]);
        }

        \App\Models\PartnerMessage::create([
            'thread_id'   => $thread->id,
            'tenant_id'   => $tenantId,
            'deal_id'     => null,
            'sender_type' => 'admin',
            'sender_id'   => $senderId,
            'sender_name' => $senderName,
            'body'        => $body,
            'is_read'     => false,
        ]);

        $thread->update([
            'last_message_at'      => now(),
            'last_message_preview' => Str::limit($body, 80),
            'partner_unread'       => DB::raw('partner_unread + 1'),
        ]);

        // Notify the Partner via in-app
        app(\App\Services\NotificationDispatchService::class)->dispatch(
            category:         'tenant_workspace',
            priority:         'normal',
            title:            'New message from your workspace',
            body:             $senderName . ' sent you a message: "' . Str::limit($body, 80) . '"',
            notifiableType:   'partner',
            notifiableId:     (string) $partner->id,
            tenantId:         $tenantId,
            actionUrl:        url('/partner/messages'),
            actionLabel:      'View Message',
            deduplicationKey: 'broadcast:partner:' . $partner->id . ':msg:' . now()->format('YmdH'),
            metadata:         ['sender_name' => $senderName, 'thread_id' => $thread->id],
        );
    }

    private function notifyTenantUser(string $tenantId, string $userId, string $body, string $senderName): void
    {
        app(\App\Services\NotificationDispatchService::class)->dispatch(
            category:         'tenant_workspace',
            priority:         'normal',
            title:            'Message from ' . $senderName,
            body:             $body,
            notifiableType:   'tenant_admin',
            notifiableId:     $userId,
            tenantId:         $tenantId,
            deduplicationKey: null,
        );
    }

    private function notifyContact(string $tenantId, string $contactId, string $body, string $senderName): void
    {
        // Contacts receive in-app notifications (they may also be referrers with platform access)
        app(\App\Services\NotificationDispatchService::class)->dispatch(
            category:         'tenant_workspace',
            priority:         'normal',
            title:            'Message from ' . $senderName,
            body:             $body,
            notifiableType:   'contact',
            notifiableId:     $contactId,
            tenantId:         $tenantId,
            deduplicationKey: null,
        );
    }

    private function senderName(): string
    {
        if (auth('reseller')->check()) {
            return (string) (auth('reseller')->user()?->name ?? 'Referrer');
        }
        if (auth('tenant')->check()) {
            $u = auth('tenant')->user();
            return (string) ($u?->full_name ?: ($u?->first_name . ' ' . $u?->last_name) ?: 'Admin');
        }
        if (auth('web')->check()) {
            return (string) (auth('web')->user()?->name ?? 'Admin');
        }
        return 'Unknown';
    }

    private function formatMessage(ThreadMessage $m): array
    {
        return [
            'id'              => $m->id,
            'sender_type'     => $m->sender_type,
            'sender_name'     => $m->sender_name,
            'body'            => $m->body,
            'created_at'      => $m->created_at?->diffForHumans() ?? 'just now',
            'created_at_full' => $m->created_at?->format('M j, Y g:i A') ?? '',
        ];
    }
}
