<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\DealComment;
use App\Models\DealNoteMention;
use App\Models\Lead;
use App\Services\NotificationDispatchService;
use App\Services\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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

        $deal = Lead::where('id', $dealId)->where('tenant_id', $tenantId)->firstOrFail();

        [, $role] = $this->resolveActor();

        $query = DealComment::with(['attachments', 'mentions'])
            ->where('deal_id', $dealId)
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->whereNull('parent_comment_id')
            ->orderByDesc('created_at');

        // Referrers and Partners cannot see internal_admin notes
        if (in_array($role, ['referrer', 'partner'])) {
            $query->where('visibility', 'shared');
        }

        $comments = $query->get()->map(fn(DealComment $c) => $this->formatComment($c, $role, $dealId));

        return response()->json($comments);
    }

    // ── POST /api/deals/{dealId}/comments ─────────────────────────────────
    // Accepts multipart/form-data (for file attachments) or application/json.

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

        // Permission: Partner must be associated with the deal.
        // Check both deal_partners (formal) and deal_partner_splits (referrer-invited partners).
        if ($role === 'partner') {
            $inDealPartners = DB::table('deal_partners')
                ->where('deal_id', $dealId)
                ->where('partner_user_id', $actorId)
                ->whereIn('status', ['active', 'invited'])
                ->exists();

            $inSplits = DB::table('deal_partner_splits')
                ->where('deal_id', $dealId)
                ->where('partner_user_id', $actorId)
                ->whereNull('deleted_at')
                ->where('status', '!=', 'removed')
                ->exists();

            if (!$inDealPartners && !$inSplits) {
                return response()->json(['error' => 'You are not associated with this deal.'], 403);
            }
        }

        $data = $request->validate([
            'body'              => 'nullable|string|max:10000',
            'visibility'        => 'nullable|in:shared,internal_admin',
            'parent_comment_id' => 'nullable|string|exists:deal_comments,id',
            'mentions'          => 'nullable|string', // JSON-encoded array
            'files'             => 'nullable|array|max:5',
            'files.*'           => 'nullable|file|max:10240', // 10MB each
            'client_request_id' => 'nullable|string|max:64',
        ]);

        // Require either body or at least one file
        $hasBody  = !empty(trim($data['body'] ?? ''));
        $hasFiles = !empty($request->file('files'));
        if (!$hasBody && !$hasFiles) {
            return response()->json(['error' => 'Please add a note or attach a file.'], 422);
        }

        // ── Idempotency: return existing note if same client_request_id within 30s ──
        $clientRequestId = $data['client_request_id'] ?? null;
        if ($clientRequestId) {
            $existing = DealComment::where('tenant_id', $tenantId)
                ->where('deal_id', $dealId)
                ->where('author_user_id', $actorId)
                ->where('client_request_id', $clientRequestId)
                ->where('created_at', '>=', now()->subSeconds(30))
                ->first();
            if ($existing) {
                try { $existing->load(['attachments', 'mentions']); } catch (\Throwable) {}
                return response()->json($this->formatComment($existing, $role, $dealId), 200);
            }
        }

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
            'body'              => $hasBody ? strip_tags($data['body']) : '',
            'visibility'        => $visibility,
            'parent_comment_id' => $data['parent_comment_id'] ?? null,
            'client_request_id' => $clientRequestId,
        ]);

        // ── Save mentions ──────────────────────────────────────────────────
        $mentionedUsers = [];
        if (!empty($data['mentions'])) {
            $mentions = json_decode($data['mentions'], true) ?? [];
            foreach ($mentions as $m) {
                if (empty($m['id']) || empty($m['type']) || empty($m['name'])) continue;

                // Validate mention belongs to this tenant (cross-tenant guard)
                if (!$this->validateMentionTarget($m['type'], $m['id'], $tenantId, $dealId)) continue;

                DealNoteMention::create([
                    'tenant_id'            => $tenantId,
                    'deal_comment_id'      => $comment->id,
                    'mentionable_type'     => $m['type'],
                    'mentionable_id'       => $m['id'],
                    'display_name_snapshot'=> $m['name'],
                ]);

                $mentionedUsers[] = $m;
            }
        }

        // ── Save attachments ───────────────────────────────────────────────
        $attachmentWarning = false;
        if ($hasFiles) {
            try {
                $stored = DealNoteAttachmentController::storeFiles(
                    $request->file('files'),
                    $tenantId,
                    $dealId,
                    $comment->id,
                    $actorId,
                    $role
                );
                // Flag partial failure so frontend can warn the user
                if (count($stored) < count($request->file('files'))) {
                    $attachmentWarning = true;
                }
            } catch (\Throwable $e) {
                Log::warning('DealCommentController storeFiles failed', ['error' => $e->getMessage()]);
                $attachmentWarning = true;
            }
        }

        // ── Notify mentioned users ─────────────────────────────────────────
        $this->notifyMentions($mentionedUsers, $comment, $deal, $tenantId, $actorId, $role);

        // Audit log
        $this->auditLog($tenantId, $dealId, $actorId, 'deal_comment_created');

        // Lead history — only top-level notes (not replies); internal notes labelled distinctly
        if (empty($data['parent_comment_id'])) {
            try {
                $lead = Lead::find($dealId);
                if ($lead) {
                    if ($visibility === 'internal_admin') {
                        $label = 'Internal note';
                    } elseif ($hasFiles && !$hasBody) {
                        $label = 'Attachment';
                    } else {
                        $label = 'Note';
                    }
                    app(\App\Services\DealActivityService::class)->noteAdded(
                        $lead,
                        $comment->body ?? '',
                        ['label' => $label, 'metadata' => ['comment_id' => $comment->id, 'visibility' => $visibility]]
                    );
                }
            } catch (\Throwable) {}
        }

        // LGU IDS: auto-complete open note tasks when a shared top-level note is added
        if ($tenantId === 'lgu-ids' && $visibility === 'shared' && empty($data['parent_comment_id'])) {
            try {
                \App\Models\Task::where('tenant_id', $tenantId)
                    ->where('source_type', \App\Services\LguIds\LguIdsDealNoteTaskService::SOURCE_TYPE)
                    ->where('taskable_type', 'lead')
                    ->where('taskable_id', $dealId)
                    ->whereIn('status', ['open', 'in_progress', 'waiting'])
                    ->whereNull('deleted_at')
                    ->each(function ($task) use ($tenantId, $actorId, $role, $actor) {
                        $task->update([
                            'status'            => 'completed',
                            'completed_at'      => now(),
                            'completed_by_type' => $role,
                            'completed_by_id'   => $actorId,
                        ]);
                        \App\Models\TaskActivity::create([
                            'tenant_id'   => $tenantId,
                            'task_id'     => $task->id,
                            'actor_type'  => $role,
                            'actor_id'    => $actorId,
                            'actor_name'  => $actor?->full_name ?? $actor?->name ?? 'Admin',
                            'action_type' => 'task_completed',
                            'new_values'  => ['status' => 'completed', 'trigger' => 'admin_comment_added'],
                        ]);
                    });
            } catch (\Throwable) {}
        }

        // Reload with relations — wrapped so a missing table never 500s the response
        try {
            $comment->load(['attachments', 'mentions']);
        } catch (\Throwable) {}

        $formatted = $this->formatComment($comment, $role, $dealId);
        if ($attachmentWarning) {
            $formatted['attachment_warning'] = 'Some files could not be attached. Please try re-uploading.';
        }

        return response()->json($formatted, 201);
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

        if ($comment->author_user_id !== $actorId && !in_array($role, ['tenant_admin', 'super_admin'])) {
            return response()->json(['error' => 'You cannot edit this note.'], 403);
        }

        $data = $request->validate(['body' => 'required|string|max:10000']);

        $comment->update([
            'body'      => strip_tags($data['body']),
            'edited_at' => now(),
        ]);

        $this->auditLog($tenantId, $dealId, $actorId, 'deal_comment_edited');

        $comment->load(['attachments', 'mentions']);

        return response()->json($this->formatComment($comment->fresh(), $role, $dealId));
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
            return response()->json(['error' => 'You cannot delete this note.'], 403);
        }

        $comment->update(['deleted_at' => now()]);

        $this->auditLog($tenantId, $dealId, $actorId, 'deal_comment_deleted');

        return response()->json(['deleted' => true]);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    private function formatComment(DealComment $c, string $viewerRole, string $dealId): array
    {
        $authorName = 'User';
        try {
            $authorName = match ($c->author_role) {
                'referrer' => DB::table('resellers')->where('id', $c->author_user_id)->value('name') ?? 'Referrer',
                'partner'  => trim(
                    (DB::table('partner_users')->where('id', $c->author_user_id)->value('first_name') ?? '') . ' ' .
                    (DB::table('partner_users')->where('id', $c->author_user_id)->value('last_name')  ?? '')
                ),
                default    => DB::table('tenant_users')->where('id', $c->author_user_id)->value('name')
                           ?? DB::table('users')->where('id', $c->author_user_id)->value('name')
                           ?? 'Admin',
            };
        } catch (\Throwable) {}

        // Format attachments — safe when relation not loaded (missing table)
        $attachments = [];
        try {
            if ($c->relationLoaded('attachments')) {
                $attachments = $c->attachments->map(fn($a) => [
                    'id'                => $a->id,
                    'original_filename' => $a->original_filename,
                    'mime_type'         => $a->mime_type,
                    'file_size'         => $a->file_size,
                    'file_type_group'   => $a->file_type_group,
                    'download_url'      => "/api/deals/{$dealId}/comments/{$c->id}/attachments/{$a->id}",
                ])->toArray();
            }
        } catch (\Throwable) {}

        // Format mentions — safe when relation not loaded
        $mentions = [];
        try {
            if ($c->relationLoaded('mentions')) {
                $mentions = $c->mentions->map(fn($m) => [
                    'id'   => $m->mentionable_id,
                    'type' => $m->mentionable_type,
                    'name' => $m->display_name_snapshot,
                ])->toArray();
            }
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
            'attachments'       => $c->isDeleted() ? [] : $attachments,
            'mentions'          => $mentions,
        ];
    }

    private function validateMentionTarget(string $type, string $id, string $tenantId, string $dealId): bool
    {
        return match ($type) {
            'tenant_admin' => DB::table('tenant_memberships')
                ->join('tenant_users', 'tenant_users.id', '=', 'tenant_memberships.tenant_user_id')
                ->where('tenant_memberships.tenant_id', $tenantId)
                ->where('tenant_users.id', $id)
                ->where('tenant_memberships.status', 'active')
                ->exists(),

            'referrer' => DB::table('resellers')
                ->where('id', $id)
                ->where('tenant_id', $tenantId)
                ->exists(),

            'partner' => DB::table('deal_partners')
                ->where('deal_id', $dealId)
                ->where('partner_user_id', $id)
                ->whereIn('status', ['active', 'invited'])
                ->exists(),

            'contact' => DB::table('contacts')
                ->where('id', $id)
                ->where('tenant_id', $tenantId)
                ->exists(),

            default => false,
        };
    }

    private function notifyMentions(array $mentions, DealComment $comment, Lead $deal, string $tenantId, string $actorId, string $role): void
    {
        if (empty($mentions)) return;

        // Resolve author display name for notification
        $authorName = match ($role) {
            'referrer' => DB::table('resellers')->where('id', $actorId)->value('name') ?? 'Referrer',
            'partner'  => 'Partner',
            default    => DB::table('tenant_users')->where('id', $actorId)->value('name') ?? 'Admin',
        };

        $svc    = app(NotificationDispatchService::class);
        $dedupBase = "mention:{$comment->id}";

        foreach ($mentions as $m) {
            // Do not notify the author themselves
            if ($m['type'] !== 'contact' && $m['id'] === $actorId) continue;

            // Only notify entity types that have portal access (not contacts by default)
            $notifiableType = match ($m['type']) {
                'tenant_admin' => 'tenant_admin',
                'referrer'     => 'reseller',
                'partner'      => 'partner',
                default        => null,
            };
            if (!$notifiableType) continue;

            $dealUrl = match ($notifiableType) {
                'reseller' => "/reseller/{$tenantId}/deals/{$deal->id}",
                'partner'  => "/partner/deals/{$deal->id}",
                default    => "/tenant/{$tenantId}/deals/{$deal->id}",
            };

            try {
                $svc->dispatch(
                    category:          'deal_pipeline',
                    priority:          'normal',
                    title:             'You were mentioned in a note',
                    body:              "{$authorName} mentioned you in a note on {$deal->name}.",
                    notifiableType:    $notifiableType,
                    notifiableId:      $m['id'],
                    tenantId:          $tenantId,
                    actionUrl:         $dealUrl,
                    actionLabel:       'View note',
                    deduplicationKey:  "{$dedupBase}:{$m['id']}",
                    metadata:          ['deal_id' => $deal->id, 'comment_id' => $comment->id],
                );
            } catch (\Throwable) {}
        }
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
