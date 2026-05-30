<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Services\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class MessageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tenantId = TenantContext::id();
        $query = Message::orderBy('created_at', 'desc');
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        } elseif (!TenantContext::isSuperAdmin()) {
            abort(403, 'Tenant context required.');
        }
        $perPage   = min(100, max(1, (int) $request->get('per_page', 50)));
        $paginated = $query->paginate($perPage);
        return response()->json([
            'data'      => $paginated->items(),
            'total'     => $paginated->total(),
            'page'      => $paginated->currentPage(),
            'per_page'  => $paginated->perPage(),
            'last_page' => $paginated->lastPage(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless(TenantContext::isSuperAdmin(), 403, 'Only super admins can create messages.');

        $data = $request->validate([
            'target_type'     => 'required|in:all,tenant,group,user',
            'target_ids_json' => 'nullable|array',
            'channel'         => 'required|in:email,in_app,both',
            'subject'         => 'required|string|max:255',
            'body'            => 'required|string',
            'status'          => 'nullable|in:draft,sent,scheduled,failed',
            'scheduled_at'    => 'nullable|date',
        ]);

        $tenantId = ($data['target_type'] === 'tenant' && !empty($data['target_ids_json']))
            ? ($data['target_ids_json'][0] ?? null)
            : null;

        $message = Message::create([
            ...$data,
            'tenant_id'       => $tenantId,
            'status'          => $data['status'] ?? 'sent',
            'sent_at'         => ($data['status'] ?? 'sent') === 'sent' ? now() : null,
            'target_ids_json' => $data['target_ids_json'] ?? [],
        ]);

        return response()->json($message, 201);
    }

    public function show(Message $message): JsonResponse
    {
        if (!TenantContext::isSuperAdmin()) {
            $tenantId = TenantContext::id();
            if ($message->tenant_id && $message->tenant_id !== $tenantId) {
                abort(404);
            }
        }
        return response()->json($message);
    }

    public function update(Request $request, Message $message): JsonResponse
    {
        abort_unless(TenantContext::isSuperAdmin(), 403, 'Only super admins can update messages.');

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
        abort_unless(TenantContext::isSuperAdmin(), 403, 'Only super admins can delete messages.');

        $message->delete();
        return response()->json(['message' => 'Message deleted.']);
    }
}
