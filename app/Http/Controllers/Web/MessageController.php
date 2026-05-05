<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\MessageThread;
use App\Models\Reseller;
use App\Models\ThreadMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MessageController extends Controller
{
    public function threads($tenantId)
    {
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

        return response()->json($threads);
    }

    public function startThread(Request $request, $tenantId)
    {
        $request->validate([
            'reseller_id' => 'required|string',
            'body'        => 'required|string|max:5000',
        ]);

        $reseller = Reseller::where('tenant_id', $tenantId)
            ->where('id', $request->reseller_id)
            ->firstOrFail();

        $thread = MessageThread::firstOrCreate(
            ['tenant_id' => $tenantId, 'reseller_id' => $reseller->id],
            ['id' => (string) Str::uuid()]
        );

        $senderName = $this->senderName();

        $msg = ThreadMessage::create([
            'id'          => (string) Str::uuid(),
            'thread_id'   => $thread->id,
            'tenant_id'   => $tenantId,
            'sender_type' => 'admin',
            'sender_id'   => (string) (auth('tenant')->id() ?? auth('web')->id()),
            'sender_name' => $senderName,
            'body'        => $request->body,
        ]);

        $thread->update([
            'last_message_at'      => now(),
            'last_message_preview' => Str::limit($request->body, 80),
            'reseller_unread'      => DB::raw('reseller_unread + 1'),
        ]);

        return response()->json([
            'thread_id' => $thread->id,
            'message'   => $this->formatMessage($msg),
        ]);
    }

    public function threadMessages($tenantId, $threadId)
    {
        $thread = MessageThread::where('tenant_id', $tenantId)
            ->where('id', $threadId)
            ->with('reseller:id,name,email')
            ->firstOrFail();

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
    }

    public function sendMessage(Request $request, $tenantId, $threadId)
    {
        $request->validate(['body' => 'required|string|max:5000']);

        $thread = MessageThread::where('tenant_id', $tenantId)
            ->where('id', $threadId)
            ->firstOrFail();

        $senderName = $this->senderName();

        $msg = ThreadMessage::create([
            'id'          => (string) Str::uuid(),
            'thread_id'   => $threadId,
            'tenant_id'   => $tenantId,
            'sender_type' => 'admin',
            'sender_id'   => (string) (auth('tenant')->id() ?? auth('web')->id()),
            'sender_name' => $senderName,
            'body'        => $request->body,
        ]);

        $thread->update([
            'last_message_at'      => now(),
            'last_message_preview' => Str::limit($request->body, 80),
            'reseller_unread'      => DB::raw('reseller_unread + 1'),
        ]);

        return response()->json($this->formatMessage($msg));
    }

    private function senderName(): string
    {
        if (auth('tenant')->check()) return auth('tenant')->user()->name;
        if (auth('web')->check())    return auth('web')->user()->name;
        return 'Admin';
    }

    private function formatMessage(ThreadMessage $m): array
    {
        return [
            'id'              => $m->id,
            'sender_type'     => $m->sender_type,
            'sender_name'     => $m->sender_name,
            'body'            => $m->body,
            'created_at'      => $m->created_at->diffForHumans(),
            'created_at_full' => $m->created_at->format('M j, Y g:i A'),
        ];
    }
}
