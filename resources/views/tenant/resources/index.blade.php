@extends('layouts.app')
@section('title', 'Resources')
@section('nav') @include('tenant._nav') @endsection

@section('content')
@php
    $csrf    = csrf_token();
    $baseUrl = "/tenant/{$tenantId}/resources";
@endphp
<div x-data="resourcesPage('{{ $tenantId }}', '{{ $currentFolder?->id }}', '{{ $csrf }}')"
     x-init="init()"
     class="flex h-full gap-0 -mx-4 sm:-mx-6 lg:-mx-8 -mt-4">

    {{-- ── Sidebar folder tree ─────────────────────────────────────────────── --}}
    <div class="w-56 shrink-0 bg-gray-50 border-r border-gray-200 flex flex-col overflow-y-auto hidden md:flex">
        <div class="px-3 py-3 border-b border-gray-200">
            <a href="{{ route('tenant.resources', $tenantId) }}"
               class="flex items-center gap-2 px-2 py-1.5 rounded-lg text-sm font-semibold {{ !$currentFolder ? 'bg-purple-100 text-purple-700' : 'text-gray-700 hover:bg-gray-100' }} transition-colors">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
                All Files
            </a>
        </div>
        <div class="flex-1 p-2 space-y-0.5 overflow-y-auto">
            @php
                function renderFolderTree($folders, $parentId, $currentFolderId, $tenantId) {
                    $children = $folders->where('parent_id', $parentId)->values();
                    if ($children->isEmpty()) return;
                    echo '<div class="pl-3 space-y-0.5">';
                    foreach ($children as $folder) {
                        $active = $currentFolderId === $folder->id;
                        echo '<div>';
                        echo '<a href="/tenant/' . $tenantId . '/resources/folder/' . $folder->id . '"
                                class="flex items-center gap-2 px-2 py-1 rounded-lg text-xs font-medium transition-colors ' . ($active ? 'bg-purple-100 text-purple-700' : 'text-gray-600 hover:bg-gray-100') . '">
                                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
                                <span class="truncate">' . htmlspecialchars($folder->name) . '</span>
                              </a>';
                        renderFolderTree($folders, $folder->id, $currentFolderId, $tenantId);
                        echo '</div>';
                    }
                    echo '</div>';
                }
                renderFolderTree($allFolders, null, $currentFolder?->id, $tenantId);
            @endphp
        </div>
    </div>

    {{-- ── Main content area ───────────────────────────────────────────────── --}}
    <div class="flex-1 flex flex-col overflow-hidden min-w-0">

        {{-- Toolbar --}}
        <div class="flex items-center justify-between gap-3 px-4 py-3 bg-white border-b border-gray-200 shrink-0 flex-wrap gap-y-2">
            {{-- Breadcrumb --}}
            <nav class="flex items-center gap-1 text-sm min-w-0">
                <a href="{{ route('tenant.resources', $tenantId) }}"
                   class="text-gray-500 hover:text-[#7B61FF] transition-colors font-medium shrink-0">All Files</a>
                @foreach($breadcrumbs as $bc)
                    <svg class="w-3.5 h-3.5 text-gray-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    <a href="{{ route('tenant.resources.folder', [$tenantId, $bc->id]) }}"
                       class="text-gray-600 hover:text-[#7B61FF] transition-colors truncate max-w-[120px]">{{ $bc->name }}</a>
                @endforeach
            </nav>

            {{-- Actions --}}
            <div class="flex items-center gap-2">
                <button @click="newFolderOpen = true; $nextTick(() => $refs.newFolderInput?.focus())"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-gray-200 text-xs font-semibold text-gray-600 hover:bg-gray-50 transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m-9 1V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z"/></svg>
                    New Folder
                </button>
                <label class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-white cursor-pointer transition-all hover:opacity-90"
                       style="background:linear-gradient(135deg,#7B61FF,#5b4cdb)">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                    <span x-text="uploading ? 'Uploading…' : 'Upload File'">Upload File</span>
                    <input type="file" class="sr-only" multiple @change="uploadFiles($event)" :disabled="uploading">
                </label>
            </div>
        </div>

        {{-- New folder inline form --}}
        <div x-show="newFolderOpen" x-cloak
             class="flex items-center gap-2 px-4 py-2.5 bg-purple-50 border-b border-purple-100">
            <svg class="w-4 h-4 text-purple-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
            <input x-ref="newFolderInput" x-model="newFolderName" type="text" placeholder="Folder name"
                   class="flex-1 border border-purple-200 rounded-lg px-2.5 py-1 text-sm bg-white outline-none focus:border-purple-400 focus:ring-1 focus:ring-purple-100"
                   @keydown.enter="createFolder()" @keydown.escape="newFolderOpen = false; newFolderName = ''">
            <button @click="createFolder()" :disabled="!newFolderName.trim()"
                    class="px-3 py-1 rounded-lg text-xs font-semibold text-white disabled:opacity-50"
                    style="background:#7B61FF">Create</button>
            <button @click="newFolderOpen = false; newFolderName = ''"
                    class="px-3 py-1 rounded-lg text-xs font-medium text-gray-600 border border-gray-200 hover:bg-gray-50">Cancel</button>
        </div>

        {{-- Upload progress --}}
        <div x-show="uploading" class="px-4 py-2 bg-blue-50 border-b border-blue-100 text-xs text-blue-600 flex items-center gap-2">
            <svg class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
            <span x-text="uploadStatus"></span>
        </div>

        {{-- Error --}}
        <div x-show="error" class="px-4 py-2 bg-red-50 border-b border-red-100 text-xs text-red-600 flex items-center justify-between">
            <span x-text="error"></span>
            <button @click="error=''" class="text-red-400 hover:text-red-600 ml-2">✕</button>
        </div>

        {{-- File grid --}}
        <div class="flex-1 overflow-y-auto p-4">

            {{-- Empty state --}}
            <template x-if="folders.length === 0 && files.length === 0">
                <div class="flex flex-col items-center justify-center py-20 text-center">
                    <svg class="w-16 h-16 text-gray-200 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
                    </svg>
                    <p class="text-sm font-semibold text-gray-400 mb-1">This folder is empty</p>
                    <p class="text-xs text-gray-400">Create a subfolder or upload files to get started.</p>
                </div>
            </template>

            {{-- Folders section --}}
            <template x-if="folders.length > 0">
                <div class="mb-6">
                    <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wide mb-2">Folders</p>
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-3">
                        <template x-for="folder in folders" :key="folder.id">
                            <div class="group relative bg-white border border-gray-200 rounded-xl p-3 cursor-pointer hover:border-purple-300 hover:shadow-sm transition-all"
                                 @dblclick="navigateFolder(folder.id)">
                                <div class="flex items-center gap-2 mb-1">
                                    <svg class="w-8 h-8 text-yellow-400 shrink-0" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M10 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-8l-2-2z"/>
                                    </svg>
                                    <span class="flex-1 text-xs font-semibold text-gray-800 truncate" x-text="folder.name"></span>
                                </div>
                                {{-- Context menu --}}
                                <div class="absolute top-2 right-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <div x-data="{ open: false }" class="relative">
                                        <button @click.stop="open = !open"
                                                class="w-6 h-6 rounded-lg bg-gray-100 flex items-center justify-center text-gray-400 hover:bg-gray-200">
                                            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="5" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="12" cy="19" r="1.5"/></svg>
                                        </button>
                                        <div x-show="open" @click.outside="open=false" x-cloak
                                             class="absolute right-0 top-7 bg-white rounded-xl shadow-lg border border-gray-100 py-1 z-10 w-36">
                                            <button @click="open=false; navigateFolder(folder.id)"
                                                    class="w-full text-left px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-50 flex items-center gap-2">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                                Open
                                            </button>
                                            <button @click="open=false; startRenameFolder(folder)"
                                                    class="w-full text-left px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-50 flex items-center gap-2">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                                Rename
                                            </button>
                                            <button @click="open=false; deleteFolder(folder.id)"
                                                    class="w-full text-left px-3 py-1.5 text-xs text-red-600 hover:bg-red-50 flex items-center gap-2">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                Delete
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </template>

            {{-- Files section --}}
            <template x-if="files.length > 0">
                <div>
                    <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wide mb-2">Files</p>
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-3">
                        <template x-for="file in files" :key="file.id">
                            <div class="group relative bg-white border border-gray-200 rounded-xl p-3 hover:border-purple-300 hover:shadow-sm transition-all">
                                {{-- File type icon --}}
                                <div class="w-10 h-10 rounded-xl flex items-center justify-center mb-2 mx-auto"
                                     :class="fileIconBg(file.file_type_group)">
                                    <svg class="w-5 h-5" :class="fileIconColor(file.file_type_group)" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="fileIconPath(file.file_type_group)"/>
                                    </svg>
                                </div>
                                <p class="text-xs font-medium text-gray-700 truncate text-center" :title="file.name" x-text="file.name"></p>
                                <p class="text-[10px] text-gray-400 text-center mt-0.5" x-text="file.formatted_size || formatSize(file.size)"></p>

                                {{-- Context menu --}}
                                <div class="absolute top-2 right-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <div x-data="{ open: false }" class="relative">
                                        <button @click.stop="open = !open"
                                                class="w-6 h-6 rounded-lg bg-gray-100 flex items-center justify-center text-gray-400 hover:bg-gray-200">
                                            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="5" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="12" cy="19" r="1.5"/></svg>
                                        </button>
                                        <div x-show="open" @click.outside="open=false" x-cloak
                                             class="absolute right-0 top-7 bg-white rounded-xl shadow-lg border border-gray-100 py-1 z-10 w-36">
                                            <a :href="`/tenant/${tenantId}/resources/files/${file.id}/download`"
                                               @click="open=false"
                                               class="w-full text-left px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-50 flex items-center gap-2 no-underline">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                                Download
                                            </a>
                                            <button @click="open=false; startRenameFile(file)"
                                                    class="w-full text-left px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-50 flex items-center gap-2">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                                Rename
                                            </button>
                                            <button @click="open=false; deleteFile(file.id)"
                                                    class="w-full text-left px-3 py-1.5 text-xs text-red-600 hover:bg-red-50 flex items-center gap-2">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                Delete
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </template>
        </div>
    </div>

    {{-- ── Rename modal ─────────────────────────────────────────────────────── --}}
    <template x-teleport="body">
    <div x-show="renameModal.open" x-cloak
         class="fixed inset-0 z-[9999] flex items-center justify-center p-4"
         style="background:rgba(0,0,0,.5)"
         @keydown.escape.window="renameModal.open=false">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm p-5 space-y-4" @click.stop>
            <h3 class="font-bold text-[#1E1B4B]" x-text="'Rename ' + renameModal.type"></h3>
            <input x-ref="renameInput" x-model="renameModal.name" type="text" maxlength="255"
                   class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm bg-gray-50 outline-none focus:border-[#7B61FF] focus:ring-2 focus:ring-purple-100"
                   @keydown.enter="submitRename()" @keydown.escape="renameModal.open=false">
            <div class="flex gap-2 justify-end">
                <button @click="renameModal.open=false" class="px-4 py-2 rounded-xl border border-gray-200 text-sm text-gray-600 hover:bg-gray-50">Cancel</button>
                <button @click="submitRename()" :disabled="!renameModal.name.trim()"
                        class="px-4 py-2 rounded-xl text-sm font-semibold text-white disabled:opacity-50"
                        style="background:#7B61FF">Rename</button>
            </div>
        </div>
    </div>
    </template>

</div>
@endsection

@push('scripts')
<script>
function resourcesPage(tenantId, currentFolderId, csrf) {
    const hdrs = () => ({ 'Content-Type':'application/json','X-CSRF-TOKEN':csrf,'Accept':'application/json','X-Requested-With':'XMLHttpRequest' });

    return {
        tenantId,
        currentFolderId: currentFolderId || null,
        folders: @json($folders),
        files:   @json($files->map(fn($f) => array_merge($f->toArray(), ['formatted_size' => $f->formattedSize()]))),
        uploading:    false,
        uploadStatus: '',
        newFolderOpen: false,
        newFolderName: '',
        error: '',
        renameModal: { open: false, type: '', id: '', name: '', isFolder: false },

        init() {},

        navigateFolder(id) {
            window.location.href = `/tenant/${tenantId}/resources/folder/${id}`;
        },

        async createFolder() {
            if (!this.newFolderName.trim()) return;
            try {
                const res = await fetch(`/tenant/${tenantId}/resources/folders`, {
                    method: 'POST', headers: hdrs(),
                    body: JSON.stringify({ name: this.newFolderName.trim(), parent_id: this.currentFolderId }),
                });
                const data = await res.json();
                if (!res.ok) { this.error = data.error || 'Could not create folder.'; return; }
                this.folders.push(data.folder);
                this.newFolderOpen = false; this.newFolderName = '';
            } catch { this.error = 'Network error.'; }
        },

        async uploadFiles(event) {
            const fileList = Array.from(event.target.files);
            if (!fileList.length) return;
            this.uploading = true;
            let done = 0;
            for (const file of fileList) {
                this.uploadStatus = `Uploading ${done + 1} of ${fileList.length}: ${file.name}`;
                const fd = new FormData();
                fd.append('file', file);
                if (this.currentFolderId) fd.append('folder_id', this.currentFolderId);
                fd.append('_token', csrf);
                try {
                    const res = await fetch(`/tenant/${tenantId}/resources/upload`, {
                        method: 'POST', credentials: 'same-origin',
                        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                        body: fd,
                    });
                    const data = await res.json();
                    if (res.ok) this.files.push(data.file);
                    else this.error = data.error || 'Upload failed for ' + file.name;
                } catch { this.error = 'Network error uploading ' + file.name; }
                done++;
            }
            this.uploading = false; this.uploadStatus = '';
            event.target.value = '';
        },

        startRenameFolder(folder) {
            this.renameModal = { open: true, type: 'Folder', id: folder.id, name: folder.name, isFolder: true };
            this.$nextTick(() => this.$refs.renameInput?.focus());
        },

        startRenameFile(file) {
            this.renameModal = { open: true, type: 'File', id: file.id, name: file.name, isFolder: false };
            this.$nextTick(() => this.$refs.renameInput?.focus());
        },

        async submitRename() {
            const { id, name, isFolder } = this.renameModal;
            if (!name.trim()) return;
            const url = isFolder
                ? `/tenant/${tenantId}/resources/folders/${id}`
                : `/tenant/${tenantId}/resources/files/${id}`;
            try {
                const res = await fetch(url, { method: 'PATCH', headers: hdrs(), body: JSON.stringify({ name: name.trim() }) });
                if (res.ok) {
                    if (isFolder) { const f = this.folders.find(x => x.id === id); if (f) f.name = name.trim(); }
                    else          { const f = this.files.find(x => x.id === id);   if (f) f.name = name.trim(); }
                    this.renameModal.open = false;
                } else { const d = await res.json(); this.error = d.error || 'Rename failed.'; }
            } catch { this.error = 'Network error.'; }
        },

        async deleteFolder(id) {
            if (!confirm('Delete this folder and all its contents? This cannot be undone.')) return;
            try {
                const res = await fetch(`/tenant/${tenantId}/resources/folders/${id}`, { method: 'DELETE', headers: hdrs() });
                if (res.ok) { this.folders = this.folders.filter(f => f.id !== id); }
                else { const d = await res.json(); this.error = d.error || 'Delete failed.'; }
            } catch { this.error = 'Network error.'; }
        },

        async deleteFile(id) {
            if (!confirm('Delete this file? This cannot be undone.')) return;
            try {
                const res = await fetch(`/tenant/${tenantId}/resources/files/${id}`, { method: 'DELETE', headers: hdrs() });
                if (res.ok) { this.files = this.files.filter(f => f.id !== id); }
                else { const d = await res.json(); this.error = d.error || 'Delete failed.'; }
            } catch { this.error = 'Network error.'; }
        },

        formatSize(bytes) {
            if (!bytes) return '0 B';
            if (bytes < 1024) return bytes + ' B';
            if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
            return (bytes / 1048576).toFixed(1) + ' MB';
        },

        fileIconBg(type)   { return { image:'bg-pink-50', pdf:'bg-red-50', spreadsheet:'bg-green-50', document:'bg-blue-50', presentation:'bg-orange-50' }[type] || 'bg-gray-50'; },
        fileIconColor(type){ return { image:'text-pink-500', pdf:'text-red-500', spreadsheet:'text-green-600', document:'text-blue-500', presentation:'text-orange-500' }[type] || 'text-gray-400'; },
        fileIconPath(type) {
            const paths = {
                image:        'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z',
                pdf:          'M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z',
                spreadsheet:  'M3 10h18M3 14h18M10 3v18M6 3h12a1 1 0 011 1v16a1 1 0 01-1 1H6a1 1 0 01-1-1V4a1 1 0 011-1z',
                document:     'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
                presentation: 'M7 4v16M17 4v16M3 8h4m10 0h4M3 12h18M3 16h4m10 0h4M4 20h16a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v14a1 1 0 001 1z',
            };
            return paths[type] || 'M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z';
        },
    };
}
</script>
@endpush
