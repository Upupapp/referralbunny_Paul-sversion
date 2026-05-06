<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Services\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MessageController extends Controller
{
    public function index(): JsonResponse
    {
        $tenantId = TenantContext::id();
        $query = Message::orderBy('created_at', 'desc');
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        } elseif (!TenantContext::isSuperAdmin()) {
            abort(403, 'Tenant context required.');
        }
        return response()->json($query->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'target_type'     => 'required|in:all,tenant,group,user',
            'target_ids_json' => 'nullable|array',
            'channel'         => 'required|in:email,in_app,both',
            'subject'         => 'required|string|max:255',
            'body'            => 'required|string',
            'status'          => 'nullable|in:draft,sent,scheduled,failed',
            'scheduled_at'    => 'nullable|date',
        ]);

        $message = Message::create([
            ...$data,
            'status'          => $data['status'] ?? 'sent',
            'sent_at'         => ($data['status'] ?? 'sent') === 'sent' ? now() : null,
            'target_ids_json' => $data['target_ids_json'] ?? [],
        ]);

        return response()->json($message, 201);
    }

    public function show(Message $message): JsonResponse
    {
        return response()->json($message);
    }

    public function update(Request $request, Message $message): JsonResponse
    {
        $data = $request->validate([
            'status' => 'required|in:draft,sent,scheduled,failed',
        ]);

        $message->update([
            'status'   => $data['status'],
            'sent_at'  => $data['status'] === 'sent' ? now() : $message->sent_at,
        ]);

        return response()->json($message);
    }

    public function destroy(Message $message): JsonResponse
    {
        $message->delete();
        return response()->json(['message' => 'Message deleted.']);
    }
}
