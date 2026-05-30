<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ResourceFile;
use App\Models\ResourceFolder;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TenantResourceController extends Controller
{
    private const ALLOWED_MIME = [
        'image/jpeg','image/png','image/webp','image/gif',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'text/plain','text/csv',
        'application/zip','application/x-zip-compressed',
    ];

    private const MAX_FILE_SIZE = 50 * 1024 * 1024; // 50 MB

    // ── Main view ─────────────────────────────────────────────────────────────

    public function index(string $tenantId, ?string $folderId = null)
    {
        $tenant = Tenant::findOrFail($tenantId);

        $currentFolder = $folderId
            ? ResourceFolder::where('tenant_id', $tenantId)->findOrFail($folderId)
            : null;

        // Breadcrumb trail
        $breadcrumbs = [];
        $node = $currentFolder;
        while ($node) {
            array_unshift($breadcrumbs, $node);
            $node = $node->parent_id ? ResourceFolder::find($node->parent_id) : null;
        }

        // Folders in current level
        $folders = ResourceFolder::where('tenant_id', $tenantId)
            ->where('parent_id', $currentFolder?->id)
            ->orderBy('name')
            ->get();

        // Files in current level
        $files = ResourceFile::where('tenant_id', $tenantId)
            ->where('folder_id', $currentFolder?->id)
            ->orderBy('name')
            ->get();

        // Full folder tree for sidebar
        $allFolders = ResourceFolder::where('tenant_id', $tenantId)->orderBy('name')->get();

        return view('tenant.resources.index', compact(
            'tenant', 'tenantId', 'currentFolder', 'breadcrumbs', 'folders', 'files', 'allFolders', 'folderId'
        ));
    }

    // ── Create folder ─────────────────────────────────────────────────────────

    public function createFolder(Request $request, string $tenantId)
    {
        $data = $request->validate([
            'name'      => 'required|string|max:255',
            'parent_id' => 'nullable|string',
        ]);

        [$actorType, $actorId, $actorName] = $this->resolveActor();

        $folder = ResourceFolder::create([
            'tenant_id'        => $tenantId,
            'name'             => trim($data['name']),
            'parent_id'        => $data['parent_id'] ?: null,
            'created_by_type'  => $actorType,
            'created_by_id'    => $actorId,
            'created_by_name'  => $actorName,
        ]);

        return response()->json(['folder' => $folder, 'message' => 'Folder created.']);
    }

    // ── Rename folder ─────────────────────────────────────────────────────────

    public function renameFolder(Request $request, string $tenantId, string $folderId)
    {
        $data   = $request->validate(['name' => 'required|string|max:255']);
        $folder = ResourceFolder::where('tenant_id', $tenantId)->findOrFail($folderId);
        $folder->update(['name' => trim($data['name'])]);
        return response()->json(['message' => 'Folder renamed.']);
    }

    // ── Delete folder ─────────────────────────────────────────────────────────

    public function deleteFolder(Request $request, string $tenantId, string $folderId)
    {
        $folder = ResourceFolder::where('tenant_id', $tenantId)->findOrFail($folderId);

        // Delete all files inside recursively
        $this->deleteFolderContents($tenantId, $folderId);
        $folder->delete();

        return response()->json(['message' => 'Folder deleted.']);
    }

    // ── Upload file ───────────────────────────────────────────────────────────

    public function upload(Request $request, string $tenantId)
    {
        $request->validate([
            'file'      => 'required|file|max:51200',
            'folder_id' => 'nullable|string',
        ]);

        $file      = $request->file('file');
        $mimeType  = $file->getMimeType();

        if (!in_array($mimeType, self::ALLOWED_MIME)) {
            return response()->json(['error' => 'File type not allowed.'], 422);
        }
        if ($file->getSize() > self::MAX_FILE_SIZE) {
            return response()->json(['error' => 'File exceeds 50 MB limit.'], 422);
        }

        $dangerousExt = ['php', 'php3', 'php4', 'php5', 'phtml', 'exe', 'sh', 'bat', 'cmd', 'ps1', 'py', 'rb', 'pl', 'cgi', 'htaccess', 'htpasswd', 'phar'];
        $ext = strtolower($file->getClientOriginalExtension());
        if (in_array($ext, $dangerousExt)) {
            return response()->json(['error' => 'File type not allowed.'], 422);
        }

        [$actorType, $actorId, $actorName] = $this->resolveActor();

        $folderId  = $request->input('folder_id') ?: null;
        $original  = $file->getClientOriginalName();
        $safeName  = preg_replace('/[^a-zA-Z0-9._\-]/', '_', $original);
        $stored    = Str::uuid() . '_' . $safeName;
        $path      = "tenants/{$tenantId}/resources/" . ($folderId ?? 'root') . "/{$stored}";

        Storage::disk('local')->put($path, file_get_contents($file->getRealPath()));

        $typeGroup = match(true) {
            str_starts_with($mimeType, 'image/')          => 'image',
            $mimeType === 'application/pdf'               => 'pdf',
            str_contains($mimeType, 'spreadsheet')
            || str_contains($mimeType, 'excel')
            || $mimeType === 'text/csv'                   => 'spreadsheet',
            str_contains($mimeType, 'word')
            || $mimeType === 'text/plain'                 => 'document',
            str_contains($mimeType, 'presentation')
            || str_contains($mimeType, 'powerpoint')      => 'presentation',
            default                                       => 'other',
        };

        $resourceFile = ResourceFile::create([
            'tenant_id'          => $tenantId,
            'folder_id'          => $folderId,
            'name'               => $original,
            'disk'               => 'local',
            'path'               => $path,
            'size'               => $file->getSize(),
            'mime_type'          => $mimeType,
            'file_type_group'    => $typeGroup,
            'uploaded_by_type'   => $actorType,
            'uploaded_by_id'     => $actorId,
            'uploaded_by_name'   => $actorName,
        ]);

        return response()->json([
            'file'    => array_merge($resourceFile->toArray(), ['formatted_size' => $resourceFile->formattedSize()]),
            'message' => 'File uploaded.',
        ]);
    }

    // ── Rename file ───────────────────────────────────────────────────────────

    public function renameFile(Request $request, string $tenantId, string $fileId)
    {
        $data = $request->validate(['name' => 'required|string|max:255']);
        $file = ResourceFile::where('tenant_id', $tenantId)->findOrFail($fileId);
        $file->update(['name' => trim($data['name'])]);
        return response()->json(['message' => 'File renamed.']);
    }

    // ── Move file to another folder ───────────────────────────────────────────

    public function moveFile(Request $request, string $tenantId, string $fileId)
    {
        $data = $request->validate(['folder_id' => 'nullable|string']);
        $file = ResourceFile::where('tenant_id', $tenantId)->findOrFail($fileId);
        $file->update(['folder_id' => $data['folder_id'] ?: null]);
        return response()->json(['message' => 'File moved.']);
    }

    // ── Delete file ───────────────────────────────────────────────────────────

    public function deleteFile(Request $request, string $tenantId, string $fileId)
    {
        $file = ResourceFile::where('tenant_id', $tenantId)->findOrFail($fileId);
        try { Storage::disk($file->disk)->delete($file->path); } catch (\Throwable) {}
        $file->delete();
        return response()->json(['message' => 'File deleted.']);
    }

    // ── Download file ─────────────────────────────────────────────────────────

    public function download(string $tenantId, string $fileId)
    {
        $file = ResourceFile::where('tenant_id', $tenantId)->findOrFail($fileId);

        if (!Storage::disk($file->disk)->exists($file->path)) {
            abort(404, 'File not found.');
        }

        return Storage::disk($file->disk)->download($file->path, $file->name);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function resolveActor(): array
    {
        if ($user = Auth::guard('tenant')->user()) {
            return ['tenant_user', (string) $user->id, $user->full_name ?? $user->email];
        }
        if ($user = Auth::guard('web')->user()) {
            return ['super_admin', (string) $user->id, $user->name ?? $user->email];
        }
        return ['unknown', '', 'Unknown'];
    }

    private function deleteFolderContents(string $tenantId, string $folderId): void
    {
        ResourceFile::where('tenant_id', $tenantId)->where('folder_id', $folderId)
            ->each(function ($file) {
                try { Storage::disk($file->disk)->delete($file->path); } catch (\Throwable) {}
                $file->delete();
            });

        ResourceFolder::where('tenant_id', $tenantId)->where('parent_id', $folderId)
            ->each(function ($child) use ($tenantId) {
                $this->deleteFolderContents($tenantId, (string) $child->id);
                $child->delete();
            });
    }
}
