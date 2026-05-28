@extends('layouts.app')
@section('title', 'Deleted & Archived Deals')
@section('nav') @include('tenant._nav') @endsection

@section('content')
@php
$_badgeCounts = \Illuminate\Support\Facades\Cache::remember("subtab_badge_counts:{$tenant->id}", 60, fn() => [
    'expired'         => \App\Models\Lead::where('tenant_id', $tenant->id)->where('status','expired')->whereNull('deleted_at')->count(),
    'pending_archive' => \App\Models\DealApprovalRequest::where('tenant_id', $tenant->id)->where('type','deal_archive')->where('status','pending')->count(),
    'deleted_archived'=> \App\Models\Lead::withTrashed()->where('tenant_id', $tenant->id)->where(fn($q) => $q->where('status','archived')->orWhereNotNull('deleted_at'))->count(),
]);
$subtabCounts = array_merge($_badgeCounts, ['deleted_archived' => $metrics['archived'] + $metrics['deleted']]);
@endphp

<div class="space-y-5"
     x-data="{
         deleteModal:  { open: false, dealId: '', dealName: '', reason: '' },
         restoreModal: { open: false, dealId: '', dealName: '' },
         submitting: false,
         openDelete(id, name)  { this.deleteModal  = { open: true, dealId: id, dealName: name, reason: '' }; },
         openRestore(id, name) { this.restoreModal = { open: true, dealId: id, dealName: name }; },
     }">

    {{-- Subtab navigation --}}
    @include('tenant.deals._subtabs')

    {{-- Flash messages --}}
    @if(session('success'))
        <div class="rounded-xl bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700 flex items-center gap-2">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700 flex items-center gap-2">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            {{ session('error') }}
        </div>
    @endif

    {{-- Metrics --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
        <div class="card p-4">
            <p class="text-xs text-gray-500 font-medium uppercase tracking-wide">Archived</p>
            <p class="text-2xl font-bold text-purple-600 mt-1">{{ number_format($metrics['archived']) }}</p>
            <p class="text-xs text-gray-400 mt-0.5">₱{{ number_format($metrics['archived_value'], 0) }}</p>
        </div>
        <div class="card p-4">
            <p class="text-xs text-gray-500 font-medium uppercase tracking-wide">Soft Deleted</p>
            <p class="text-2xl font-bold text-red-500 mt-1">{{ number_format($metrics['deleted']) }}</p>
            <p class="text-xs text-gray-400 mt-0.5">Auto-purged after 10 days</p>
        </div>
        <div class="card p-4 col-span-2 sm:col-span-1">
            <p class="text-xs text-gray-500 font-medium uppercase tracking-wide">Total</p>
            <p class="text-2xl font-bold text-gray-700 mt-1">{{ number_format($metrics['archived'] + $metrics['deleted']) }}</p>
        </div>
    </div>

    {{-- Filter bar --}}
    <div class="card space-y-3">
        <form method="GET" action="{{ route('tenant.deals.deleted-archived', $tenant->id) }}" class="flex flex-wrap gap-2 items-center">
            <div class="search-group flex-1 min-w-[180px]">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" name="q" value="{{ $search }}" placeholder="Search deals…">
            </div>
            <select name="filter" class="pill-select">
                <option value="all"      @selected($filter === 'all')>All</option>
                <option value="archived" @selected($filter === 'archived')>Archived Only</option>
                <option value="deleted"  @selected($filter === 'deleted')>Deleted Only</option>
            </select>
            <select name="stage" class="pill-select">
                <option value="">All Stages</option>
                @foreach(['introduction','presentation','contract_sent','signed','paid'] as $s)
                    <option value="{{ $s }}" @selected($stage === $s)>{{ ucwords(str_replace('_',' ',$s)) }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn-primary">Filter</button>
            @if($search || ($filter && $filter !== 'all') || $stage)
                <a href="{{ route('tenant.deals.deleted-archived', $tenant->id) }}" class="btn-secondary">Clear</a>
            @endif
        </form>
    </div>

    {{-- Table --}}
    <div class="card overflow-hidden">
        @if($deals->isEmpty())
            <div class="flex flex-col items-center justify-center py-16 text-center px-4">
                <div class="w-16 h-16 rounded-2xl bg-gray-100 flex items-center justify-center mb-4">
                    <svg class="w-8 h-8 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8l1 12a2 2 0 002 2h8a2 2 0 002-2l1-12"/>
                    </svg>
                </div>
                <p class="text-gray-500 font-medium">No deleted or archived deals</p>
                <p class="text-gray-400 text-sm mt-1">Deals deleted or archived by admins appear here.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 bg-gray-50/50">
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Deal</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide hidden sm:table-cell">Stage</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide hidden md:table-cell">Referrer</th>
                            <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide hidden lg:table-cell">Value</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide hidden md:table-cell">Type</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide hidden md:table-cell">When</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($deals as $deal)
                        @php
                            $isDeleted  = !is_null($deal->deleted_at);
                            $isArchived = $deal->status === 'archived' && is_null($deal->deleted_at);
                            $typeLabel  = $isDeleted ? 'Deleted' : 'Archived';
                            $typeCls    = $isDeleted ? 'bg-red-100 text-red-700' : 'bg-purple-100 text-purple-700';
                            $whenAt     = $isDeleted ? $deal->deleted_at : ($deal->archived_at ?? $deal->updated_at);
                        @endphp
                        <tr class="hover:bg-gray-50/50 transition-colors {{ $isDeleted ? 'opacity-75' : '' }}">
                            <td class="px-4 py-3">
                                @if(!$isDeleted)
                                    <a href="{{ route('tenant.deals.show', [$tenant->id, $deal->id]) }}"
                                       class="font-semibold text-[#1E1B4B] hover:text-[#7B61FF] transition-colors">
                                        {{ $deal->name }}
                                    </a>
                                @else
                                    <span class="font-semibold text-gray-500 line-through">{{ $deal->name }}</span>
                                @endif
                                <p class="text-xs text-gray-400 mt-0.5 sm:hidden">
                                    {{ ucwords(str_replace('_',' ',$deal->stage)) }}
                                    @if($deal->reseller_name) · {{ $deal->reseller_name }} @endif
                                </p>
                            </td>
                            <td class="px-4 py-3 hidden sm:table-cell">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-lg text-xs font-medium bg-gray-100 text-gray-600">
                                    {{ ucwords(str_replace('_',' ',$deal->stage)) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-600 hidden md:table-cell">
                                {{ $deal->reseller_name ?: '—' }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-gray-700 hidden lg:table-cell">
                                ₱{{ number_format((float)$deal->deal_value, 0) }}
                            </td>
                            <td class="px-4 py-3 hidden md:table-cell">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-lg text-xs font-medium {{ $typeCls }}">
                                    {{ $typeLabel }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-400 text-xs hidden md:table-cell">
                                {{ $whenAt?->diffForHumans() }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-2">
                                    {{-- Restore --}}
                                    <button @click="openRestore('{{ $deal->id }}', '{{ addslashes($deal->name) }}')"
                                            class="text-xs text-green-600 hover:underline font-medium whitespace-nowrap">
                                        Restore
                                    </button>
                                    {{-- Soft-delete archived deals (reversible — lands in Deleted Only filter) --}}
                                    @if(!$isDeleted)
                                        <button @click="openDelete('{{ $deal->id }}', '{{ addslashes($deal->name) }}')"
                                                class="text-xs text-red-500 hover:underline font-medium whitespace-nowrap">
                                            Soft Delete
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($deals->hasPages())
                <div class="px-4 py-3 border-t border-gray-100">
                    {{ $deals->links() }}
                </div>
            @endif
        @endif
    </div>

    {{-- Restore confirm modal --}}
    <div x-show="restoreModal.open" x-cloak
         class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4"
         role="dialog" aria-modal="true"
         @keydown.escape.window="restoreModal.open = false">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm" @click.stop>
            <div class="p-6 space-y-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-green-100 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                    </div>
                    <div>
                        <p class="font-semibold text-[#1E1B4B]">Restore deal?</p>
                        <p class="text-sm text-gray-500" x-text="'\"' + restoreModal.dealName + '\" will be set back to active.'"></p>
                    </div>
                </div>
                <div class="flex justify-end gap-3">
                    <button @click="restoreModal.open = false" class="btn-secondary">Cancel</button>
                    <form :action="`{{ url('/tenant/' . $tenant->id . '/deals/') }}${restoreModal.dealId}/restore`"
                          method="POST" @submit="submitting = true">
                        @csrf
                        <button type="submit" :disabled="submitting" class="btn-primary bg-green-600 hover:bg-green-700">
                            <span x-show="!submitting">Restore</span>
                            <span x-show="submitting">Restoring…</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- Soft-delete confirm modal (reversible — deal can still be restored) --}}
    <div x-show="deleteModal.open" x-cloak
         class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4"
         role="dialog" aria-modal="true"
         @keydown.escape.window="deleteModal.open = false">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm" @click.stop>
            <form :action="`{{ url('/tenant/' . $tenant->id . '/deals/') }}${deleteModal.dealId}/soft-delete`"
                  method="POST" @submit="submitting = true">
                @csrf
                @method('DELETE')
                <div class="p-6 space-y-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-red-100 flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                        </div>
                        <div>
                            <p class="font-semibold text-[#1E1B4B]">Soft-delete this deal?</p>
                            <p class="text-sm text-gray-500" x-text="'\"' + deleteModal.dealName + '\" — this is reversible and can be restored.'"></p>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Reason (optional)</label>
                        <input type="text" name="reason" x-model="deleteModal.reason"
                               class="form-input w-full" placeholder="e.g. Duplicate entry" maxlength="500">
                    </div>
                    <div class="flex justify-end gap-3">
                        <button type="button" @click="deleteModal.open = false" class="btn-secondary">Cancel</button>
                        <button type="submit" :disabled="submitting" class="btn-primary bg-red-600 hover:bg-red-700">
                            <span x-show="!submitting">Soft Delete</span>
                            <span x-show="submitting">Deleting…</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
