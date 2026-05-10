<?php

namespace App\Http\Controllers;

use App\Models\DealComment;
use App\Models\DealNoteAttachment;
use App\Models\Lead;
use App\Services\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DealNoteAttachmentController extends Controller
{
    private const ALLOWED_MIME = [
        'image/jpeg', 'image/png', 'image/webp', 'image/gif',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/csv', 'text/plain',
    ];

    private const ALLOWED_EXT = [
        'jpg', 'jpeg', 'png', 'webp', 'gif',
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt',
    ];

    private const MAX_SIZE_BYTES = 10 * 1024 * 1024; // 10 MB
    private const MAX_FILES_PER_NOTE = 5;

    // ── GET /api/deals/{dealId}/comments/{commentId}/attachments/{attachmentId}
    // Authorized file download — tenant-scoped, role-enforced

    public function download(Request $request, string $dealId, string $commentId, string $attachmentId): Response|JsonResponse
    {
        $tenantId = TenantContext::requireId();

        // Verify deal belongs to tenant
        $deal = Lead::where('id', $dealId)->where('tenant_id', $tenantId)->firstOrFail();

        // Verify comment belongs to deal + tenant
        $comment = DealComment::where('id', $commentId)
            ->where('deal_id', $dealId)
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->firstOrFail();

        // Referrers/Partners only see shared notes
        [$actorId, $role] = $this->resolveActor();
        if (in_array($role, ['referrer', 'partner']) && $comment->isInternal()) {
            abort(403, 'You do not have access to this attachment.');
        }

        $attachment = DealNoteAttachment::where('id', $attachmentId)
            ->where('deal_comment_id', $commentId)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        if (!Storage::disk($attachment->disk)->exists($attachment->path)) {
            abort(404, 'File not found.');
        }

        $content = Storage::disk($attachment->disk)->get($attachment->path);

        return response($content, 200, [
            'Content-Type'        => $attachment->mime_type,
            'Content-Disposition' => 'attachment; filename="' . $attachment->original_filename . '"',
            'Content-Length'      => $attachment->file_size,
        ]);
    }

    // ── DELETE /api/deals/{dealId}/comments/{commentId}/attachments/{attachmentId}

    public function destroy(string $dealId, string $commentId, string $attachmentId): JsonResponse
    {
        $tenantId = TenantContext::requireId();

        $comment = DealComment::where('id', $commentId)
            ->where('deal_id', $dealId)
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->firstOrFail();

        [$actorId, $role] = $this->resolveActor();

        // Only author or admin can delete
        if ($comment->author_user_id !== $actorId && !in_array($role, ['tenant_admin', 'super_admin'])) {
            return response()->json(['error' => 'Not authorized.'], 403);
        }

        $attachment = DealNoteAttachment::where('id', $attachmentId)
            ->where('deal_comment_id', $commentId)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        // Remove physical file
        try {
            Storage::disk($attachment->disk)->delete($attachment->path);
        } catch (\Throwable) {}

        $attachment->delete();

        return response()->json(['deleted' => true]);
    }

    // ── Static helper used by DealCommentController::store()

    public static function storeFiles(array $files, string $tenantId, string $dealId, string $commentId, string $actorId, string $actorRole): array
    {
        $stored = [];

        foreach (array_slice($files, 0, self::MAX_FILES_PER_NOTE) as $file) {
            if (!$file->isValid()) continue;
            if ($file->getSize() > self::MAX_SIZE_BYTES) continue;

            $mime = $file->getMimeType() ?? '';
            $ext  = strtolower($file->getClientOriginalExtension());

            if (!in_array($mime, self::ALLOWED_MIME) || !in_array($ext, self::ALLOWED_EXT)) continue;

            $stored_name = Str::uuid() . '.' . $ext;
            $path        = "tenants/{$tenantId}/deals/{$dealId}/notes/{$commentId}/{$stored_name}";

            try {
                Storage::disk('local')->put($path, file_get_contents($file->getRealPath()));
            } catch (\Throwable) {
                continue;
            }

            $attachment = DealNoteAttachment::create([
                'tenant_id'        => $tenantId,
                'deal_comment_id'  => $commentId,
                'uploaded_by_id'   => $actorId,
                'uploaded_by_role' => $actorRole,
                'disk'             => 'local',
                'path'             => $path,
                'original_filename'=> $file->getClientOriginalName(),
                'stored_filename'  => $stored_name,
                'mime_type'        => $mime,
                'file_size'        => $file->getSize(),
                'file_type_group'  => DealNoteAttachment::typeGroup($mime),
            ]);

            $stored[] = $attachment;
        }

        return $stored;
    }

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
                return [$user->id, $role];
            }
        }
        return [null, 'unknown'];
    }
}
