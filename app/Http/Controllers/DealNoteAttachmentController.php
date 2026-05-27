<?php

namespace App\Http\Controllers;

use App\Models\DealComment;
use App\Models\DealNoteAttachment;
use App\Models\Lead;
use App\Services\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
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
        'application/excel',
        'application/x-excel',
        'application/x-msexcel',
        'text/csv', 'text/plain',
        // Browsers/OS often report legacy Office files as generic binary
        'application/octet-stream',
        // xlsx/docx are ZIP-based; some OS/browser MIME detectors report this
        'application/zip',
    ];

    private const ALLOWED_EXT = [
        'jpg', 'jpeg', 'png', 'webp', 'gif',
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt',
    ];

    private const MAX_SIZE_BYTES = 10 * 1024 * 1024; // 10 MB
    private const MAX_FILES_PER_NOTE = 5;

    // ── GET /reseller/{tenantId}/deals/{dealId}/comments/{commentId}/attachments/{attachmentId}
    // ── GET /partner/deals/{dealId}/comments/{commentId}/attachments/{attachmentId}
    // Web-accessible download for session-authenticated resellers and partners.
    // Opens inline in a new tab (Content-Disposition: inline).

    public function downloadForWeb(Request $request, string $dealId, string $commentId, string $attachmentId): StreamedResponse|JsonResponse
    {
        // Resolve tenant from session auth — TenantContext is not set on web routes
        $tenantId = $this->resolveTenantIdFromSession($request);

        return $this->serveAttachment($tenantId, $dealId, $commentId, $attachmentId, 'inline');
    }

    // ── GET /api/deals/{dealId}/comments/{commentId}/attachments/{attachmentId}
    // Authorized file download — tenant-scoped, role-enforced

    public function download(Request $request, string $dealId, string $commentId, string $attachmentId): StreamedResponse|JsonResponse
    {
        try {
            $tenantId = TenantContext::requireId();
        } catch (\Throwable) {
            abort(403, 'Tenant context required.');
        }
        return $this->serveAttachment($tenantId, $dealId, $commentId, $attachmentId, 'inline');
    }

    // ── Shared file-serving logic ─────────────────────────────────────────────

    private function serveAttachment(string $tenantId, string $dealId, string $commentId, string $attachmentId, string $disposition): StreamedResponse|JsonResponse
    {
        try {
            Lead::where('id', $dealId)->where('tenant_id', $tenantId)->firstOrFail();
        } catch (\Throwable) {
            abort(404, 'Deal not found.');
        }

        try {
            $comment = DealComment::where('id', $commentId)
                ->where('deal_id', $dealId)
                ->where('tenant_id', $tenantId)
                ->whereNull('deleted_at')
                ->firstOrFail();
        } catch (\Throwable) {
            abort(404, 'Note not found.');
        }

        [$actorId, $role] = $this->resolveActor();
        if (in_array($role, ['referrer', 'partner']) && $comment->isInternal()) {
            abort(403, 'You do not have access to this attachment.');
        }

        try {
            $attachment = DealNoteAttachment::where('id', $attachmentId)
                ->where('deal_comment_id', $commentId)
                ->where('tenant_id', $tenantId)
                ->firstOrFail();
        } catch (\Throwable) {
            abort(404, 'Attachment not found.');
        }

        $disk = $attachment->disk ?: 'local';
        $path = $attachment->path ?? '';

        if (!$path || !Storage::disk($disk)->exists($path)) {
            abort(404, 'File not found on storage.');
        }

        $safe     = str_replace(['"', '\\'], '', $attachment->original_filename ?? 'download');
        $mimeType = $attachment->mime_type ?: 'application/octet-stream';
        $fileSize = $attachment->file_size;

        try {
            $stream = Storage::disk($disk)->readStream($path);
        } catch (\Throwable) {
            abort(500, 'Could not read file from storage.');
        }

        return response()->stream(
            function () use ($stream) {
                if (is_resource($stream)) {
                    fpassthru($stream);
                    fclose($stream);
                }
            },
            200,
            [
                'Content-Type'        => $mimeType,
                'Content-Disposition' => "{$disposition}; filename=\"{$safe}\"",
                'Content-Length'      => $fileSize,
                'Cache-Control'       => 'private, no-store',
            ]
        );
    }

    private function resolveTenantIdFromSession(Request $request): string
    {
        if (Auth::guard('reseller')->check()) {
            return Auth::guard('reseller')->user()->tenant_id;
        }
        if (Auth::guard('partner')->check()) {
            return Auth::guard('partner')->user()->tenant_id;
        }
        if (Auth::guard('tenant')->check() || Auth::guard('web')->check()) {
            $tid = $request->route('tenantId');
            if ($tid) return $tid;
        }
        abort(403, 'Authentication required.');
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

            // Strip non-alphanumeric chars from extension to prevent path traversal
            $ext  = preg_replace('/[^a-z0-9]/', '', strtolower($file->getClientOriginalExtension()));
            $mime = $file->getMimeType() ?? 'application/octet-stream';

            // Extension is the primary security gate — extension must be explicitly allowed.
            // MIME check is secondary and permissive because getMimeType() can return
            // 'application/octet-stream' for valid Excel/Word files depending on the OS.
            if (!in_array($ext, self::ALLOWED_EXT)) continue;
            if (!in_array($mime, self::ALLOWED_MIME)) continue;

            $stored_name = Str::uuid() . '.' . $ext;
            $path        = "tenants/{$tenantId}/deals/{$dealId}/notes/{$commentId}/{$stored_name}";

            $stream = fopen($file->getRealPath(), 'r');
            if (!is_resource($stream)) continue;
            try {
                Storage::disk('local')->put($path, $stream);
            } catch (\Throwable $e) {
                if (is_resource($stream)) fclose($stream);
                Log::warning('DealNoteAttachment disk write failed', [
                    'file'  => $file->getClientOriginalName(),
                    'error' => $e->getMessage(),
                ]);
                continue;
            }
            if (is_resource($stream)) fclose($stream);

            try {
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
            } catch (\Throwable $e) {
                // Orphan cleanup — remove file from disk since DB insert failed
                try { Storage::disk('local')->delete($path); } catch (\Throwable) {}
                Log::warning('DealNoteAttachment DB insert failed', [
                    'file'  => $file->getClientOriginalName(),
                    'error' => $e->getMessage(),
                ]);
                continue;
            }
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
