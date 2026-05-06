<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\DealComment;
use App\Models\Lead;
use App\Services\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DealCommentController extends Controller
{
    // ── Resolve caller ─────────────────────────────────────────────────────

    private function resolveActor(): array
    {
        foreach (['tenant', 'web', 'reseller', 'partner'] as $guard) {
            if (Auth::guard($guard)->check()) {
                $user = Auth::guard($guard)->user();
                $role = match ($guard) {
                    'tenant'   => 'tenant_admin',
                    'web'      => 'super_admin',
                    'reseller' => 'referrer',
                    'partner'  => 'partner',
                    default    => 'unknown',
                };
                return [$user->id, $role, $user];
            }
        }
        return [null, 'unknown', null];
    }

    // ── GET /api/deals/{dealId}/comments ───────────────────────────────────

    public function index(Request $request, string $dealId): JsonResponse
    {
        $tenantId = TenantContext::requireId();

        // Verify deal belongs to tenant
        $deal = Lead::where('id', $dealId)->where('tenant_id', $tenantId)->firstOrFail();

        [, $role] = $this->resolveActor();

        $query = DealComment::where('deal_id', $dealId)
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->whereNull('parent_comment_id') // top-level only; replies via nested
            ->orderByDesc('created_at');

        // Referrers and Partners cannot see internal_admin comments
        if (in_array($role, ['referrer', 'partner'])) {
            $query->where('visibility', 'shared');
        }

        $comments = $query->get()->map(function (DealComment $c) use ($role) {
            return $this->formatComment($c, $role);
        });

        return response()->json($comments);
    }

    // ── POST /api/deals/{dealId}/comments ──────────────────────────────────

    public function store(Request $request, string $dealId): JsonResponse
    {
        $tenantId = TenantContext::requireId();

        $deal = Lead::where('id', $dealId)->where('tenant_id', $tenantId)->firstOrFail();

        [$actorId, $role, $actor] = $this->resolveActor();
        if (!$actorId) return response()->json(['error' => 'Unauthenticated.'], 401);

        // Permission: Referrer must be assigned to the deal
        if ($role === 'referrer') {
            if ($deal->reseller_name !== ($actor->name ?? '')) {
                return response()->json(['error' => 'You are not assigned to this deal.'], 403);
            }
        }

        // Permission: Partner must be associated with the deal
        if ($role === 'partner') {
            $associated = DB::table('deal_partners')
                ->where('deal_id', $dealId)
                ->where('partner_user_id', $actorId)
                ->whereIn('status', ['active', 'invited'])
                ->exists();
            if (!$associated) {
                return response()->json(['error' => 'You are not associated with this deal.'], 403);
            }
        }

        $data = $request->validate([
            'body'              => 'required|string|max:5000',
            'visibility'        => 'nullable|in:shared,internal_admin',
            'parent_comment_id' => 'nullable|string|exists:deal_comments,id',
        ]);

        // Only admins/managers can post internal notes
        $visibility = $data['visibility'] ?? 'shared';
        if ($visibility === 'internal_admin' && !in_array($role, ['tenant_admin', 'super_admin'])) {
            $visibility = 'shared';
        }

        $comment = DealComment::create([
            'tenant_id'         => $tenantId,
            'deal_id'           => $dealId,
            'author_user_id'    => $actorId,
            'author_role'       => $role,
            'body'              => strip_tags($data['body']),
            'visibility'        => $visibility,
            'parent_comment_id' => $data['parent_comment_id'] ?? null,
        ]);

        // Audit log
        $this->auditLog($tenantId, $dealId, $actorId, 'deal_comment_created');

        return response()->json($this->formatComment($comment, $role), 201);
    }

    // ── PATCH /api/deals/{dealId}/comments/{commentId} ──────────────────────

    public function update(Request $request, string $dealId, string $commentId): JsonResponse
    {
        $tenantId = TenantContext::requireId();

        $comment = DealComment::where('id', $commentId)
            ->where('deal_id', $dealId)
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->firstOrFail();

        [$actorId, $role] = $this->resolveActor();

        // Only the author (or admin) can edit
        if ($comment->author_user_id !== $actorId && !in_array($role, ['tenant_admin', 'super_admin'])) {
            return response()->json(['error' => 'You cannot edit this comment.'], 403);
        }

        $data = $request->validate(['body' => 'required|string|max:5000']);

        $comment->update([
            'body'      => strip_tags($data['body']),
            'edited_at' => now(),
        ]);

        $this->auditLog($tenantId, $dealId, $actorId, 'deal_comment_edited');

        return response()->json($this->formatComment($comment->fresh(), $role));
    }

    // ── DELETE /api/deals/{dealId}/comments/{commentId} ─────────────────────

    public function destroy(string $dealId, string $commentId): JsonResponse
    {
        $tenantId = TenantContext::requireId();

        $comment = DealComment::where('id', $commentId)
            ->where('deal_id', $dealId)
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->firstOrFail();

        [$actorId, $role] = $this->resolveActor();

        if ($comment->author_user_id !== $actorId && !in_array($role, ['tenant_admin', 'super_admin'])) {
            return response()->json(['error' => 'You cannot delete this comment.'], 403);
        }

        $comment->update(['deleted_at' => now()]);

        $this->auditLog($tenantId, $dealId, $actorId, 'deal_comment_deleted');

        return response()->json(['deleted' => true]);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    private function formatComment(DealComment $c, string $viewerRole): array
    {
        // Resolve author display name
        $authorName = 'User';
        try {
            $authorName = match ($c->author_role) {
                'referrer' => DB::table('resellers')->where('id', $c->author_user_id)->value('name') ?? 'Referrer',
                'partner'  => DB::table('partner_users')->where('id', $c->author_user_id)->value('first_name') . ' ' . DB::table('partner_users')->where('id', $c->author_user_id)->value('last_name'),
                default    => DB::table('tenant_users')->where('id', $c->author_user_id)->value('name')
                           ?? DB::table('users')->where('id', $c->author_user_id)->value('name')
                           ?? 'Admin',
            };
        } catch (\Throwable) {}

        return [
            'id'                => $c->id,
            'body'              => $c->isDeleted() ? null : $c->body,
            'visibility'        => $c->visibility,
            'author_user_id'    => $c->author_user_id,
            'author_role'       => $c->author_role,
            'author_name'       => trim($authorName) ?: 'User',
            'parent_comment_id' => $c->parent_comment_id,
            'edited_at'         => $c->edited_at?->toIso8601String(),
            'deleted_at'        => $c->deleted_at?->toIso8601String(),
            'is_deleted'        => $c->isDeleted(),
            'is_internal'       => $c->isInternal(),
            'created_at'        => $c->created_at?->toIso8601String(),
        ];
    }

    private function auditLog(string $tenantId, string $dealId, string $actorId, string $action): void
    {
        try {
            ActivityLog::create([
                'id'        => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'user_id'   => $actorId,
                'action'    => $action,
                'entity'    => 'lead',
                'entity_id' => $dealId,
                'metadata'  => json_encode(['timestamp' => now()->toIso8601String()]),
            ]);
        } catch (\Throwable) {}
    }
}
