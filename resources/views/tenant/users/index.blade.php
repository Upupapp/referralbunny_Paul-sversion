@extends('layouts.app')
@section('title', 'Users & Roles')

@section('nav')
    @include('tenant._nav')
@endsection

@section('topbar-actions')
    @if($isAdmin || $actingRole === 'manager')
        <button class="btn-primary" onclick="document.getElementById('invite-modal').classList.remove('hidden')">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            <span class="hidden sm:inline">Invite User</span>
        </button>
    @endif
@endsection

@section('content')
<div class="space-y-5">


    @if($errors->any())
        <div class="flex items-start gap-3 bg-red-50 border border-red-200 rounded-2xl px-4 py-3">
            <svg class="w-4 h-4 text-red-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <p class="text-sm text-red-600 font-medium">{{ $errors->first() }}</p>
        </div>
    @endif

    {{-- Header --}}
    <div class="card">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h2 class="text-[#1E1B4B] font-bold text-lg">Users & Roles</h2>
                <p class="text-gray-400 text-sm mt-0.5">Manage team members and their access levels</p>
            </div>
            <div class="flex items-center gap-2 text-xs text-gray-400">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
                {{ $memberships->total() }} member{{ $memberships->total() !== 1 ? 's' : '' }}
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

        {{-- LEFT: Member list --}}
        <div class="lg:col-span-2 space-y-3">

            @forelse($memberships as $m)
                @php
                    $u = $m->tenantUser;
                    $roleBadgeClass = match($m->role) {
                        'owner'   => 'bg-amber-100 text-amber-800',
                        'admin'   => 'bg-violet-100 text-violet-700',
                        'manager' => 'bg-sky-100 text-sky-700',
                        'member'  => 'bg-green-100 text-green-700',
                        default   => 'bg-gray-100 text-gray-600',
                    };
                    $roleLabel = ucfirst($m->role);
                    $initials  = strtoupper(substr($u?->first_name ?? '?', 0, 1) . substr($u?->last_name ?? '', 0, 1));
                @endphp
                <div class="card flex flex-col sm:flex-row sm:items-center gap-4"
                     x-data="{ confirmRemove: false }"
                     @confirm-remove-{{ $u->id }}.window="confirmRemove = true">
                    {{-- Avatar --}}
                    <div class="w-10 h-10 rounded-xl bg-[#EDE9FE] flex items-center justify-center shrink-0 font-semibold text-[#7B61FF] text-sm">
                        {{ $initials }}
                    </div>

                    {{-- Info --}}
                    <div class="flex-1 min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-sm font-semibold text-[#1E1B4B] truncate">
                                {{ $u?->first_name }} {{ $u?->last_name }}
                            </span>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $roleBadgeClass }}">
                                {{ $roleLabel }}
                            </span>
                            @if($m->status === 'suspended')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-red-100 text-red-600">
                                    Suspended
                                </span>
                            @endif
                            @if($m->role === 'manager' && $m->can_manage_billing)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-emerald-50 text-emerald-700">
                                    +Billing
                                </span>
                            @endif
                        </div>
                        <p class="text-xs text-gray-400 mt-0.5 truncate">{{ $u?->email }}</p>
                    @if($u?->last_accessed_at)
                        <p class="text-[11px] text-gray-300 mt-0.5">Last active {{ $u->last_accessed_at->diffForHumans() }}</p>
                    @endif
                    </div>

                    {{-- Actions (admin only) --}}
                    @if($isAdmin && $m->role !== 'owner')
                        <div class="flex items-center gap-2 shrink-0" x-data="{ open: false }">
                            @if($m->role === 'manager')
                                {{-- Billing toggle for managers --}}
                                <form method="POST" action="{{ route('tenant.users.billing-toggle', [$tenant->id, $u->id]) }}"
                                      x-data="{ toggling: false }" @submit="toggling = true">
                                    @csrf
                                    <input type="hidden" name="enable" value="{{ $m->can_manage_billing ? '0' : '1' }}">
                                    <button type="submit"
                                            :disabled="toggling"
                                            class="text-xs px-2.5 py-1.5 rounded-lg border border-gray-200 text-gray-500 hover:border-violet-300 hover:text-violet-700 transition-colors disabled:opacity-60 disabled:cursor-not-allowed"
                                            title="{{ $m->can_manage_billing ? 'Disable billing access' : 'Enable billing access' }}">
                                        <span x-show="!toggling">{{ $m->can_manage_billing ? 'Billing On' : 'Billing Off' }}</span>
                                        <span x-show="toggling" x-cloak>Saving…</span>
                                    </button>
                                </form>
                            @endif

                            <div class="relative" x-data="{ open: false }">
                                <button @click="open = !open"
                                        aria-label="Member actions"
                                        class="w-8 h-8 flex items-center justify-center rounded-xl text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M12 5a1.5 1.5 0 110-3 1.5 1.5 0 010 3zm0 7a1.5 1.5 0 110-3 1.5 1.5 0 010 3zm0 7a1.5 1.5 0 110-3 1.5 1.5 0 010 3z"/>
                                    </svg>
                                </button>
                                <div x-show="open" @click.away="open = false"
                                     class="absolute right-0 mt-1 w-44 bg-white border border-gray-100 rounded-xl shadow-lg z-10 py-1 text-sm">
                                    @if($m->status === 'active')
                                        <form method="POST" action="{{ route('tenant.users.deactivate', [$tenant->id, $u->id]) }}"
                                              x-data="{ submitting: false }" @submit="submitting = true">
                                            @csrf
                                            <button type="submit" :disabled="submitting"
                                                    class="w-full text-left px-3.5 py-2 text-gray-600 hover:bg-gray-50 hover:text-red-600 transition-colors text-sm disabled:opacity-60">
                                                <span x-show="!submitting">Deactivate</span>
                                                <span x-show="submitting" x-cloak>Deactivating…</span>
                                            </button>
                                        </form>
                                    @endif
                                    <button @click="open = false; $dispatch('confirm-remove-{{ $u->id }}')"
                                            class="w-full text-left px-3.5 py-2 text-red-500 hover:bg-red-50 transition-colors text-sm">
                                        Remove from workspace
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- Inline remove confirmation banner --}}
                    @if($isAdmin && $m->role !== 'owner')
                    <div x-show="confirmRemove" x-cloak
                         class="w-full mt-2 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 bg-red-50 border border-red-200 rounded-xl px-4 py-3">
                        <div class="flex items-center gap-2.5">
                            <svg class="w-4 h-4 text-red-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <p class="text-sm text-red-700 font-medium">Remove <strong>{{ $u?->first_name }}</strong> from this workspace?</p>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <button @click="confirmRemove = false"
                                    class="text-sm px-3 py-1.5 rounded-lg text-gray-600 hover:bg-white transition-colors border border-gray-200">
                                Keep
                            </button>
                            <form method="POST" action="{{ route('tenant.users.remove', [$tenant->id, $u->id]) }}"
                                  x-data="{ removing: false }" @submit="removing = true">
                                @csrf
                                @method('DELETE')
                                <button type="submit" :disabled="removing"
                                        class="text-sm px-3.5 py-1.5 rounded-lg bg-red-500 text-white hover:bg-red-600 transition-colors font-semibold disabled:opacity-60">
                                    <span x-show="!removing">Remove</span>
                                    <span x-show="removing" x-cloak>Removing…</span>
                                </button>
                            </form>
                        </div>
                    </div>
                    @endif
                </div>
            @empty
                <div class="card flex flex-col items-center justify-center py-16 text-center">
                    <div class="w-16 h-16 rounded-2xl bg-[#EDE9FE] flex items-center justify-center mb-4">
                        <svg class="w-8 h-8 text-[#7B61FF]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <h3 class="text-[#1E1B4B] font-semibold text-base">No team members yet</h3>
                    <p class="text-gray-400 text-sm mt-1 max-w-xs">Invite team members to collaborate on your referral program.</p>
                    @if($isAdmin)
                        <button class="btn-primary mt-5" onclick="document.getElementById('invite-modal').classList.remove('hidden')">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                            Invite User
                        </button>
                    @endif
                </div>
            @endforelse

            @if($memberships->hasPages())
                <div class="mt-2">{{ $memberships->links() }}</div>
            @endif
        </div>

        {{-- RIGHT: Sidebar --}}
        <div class="space-y-4">

            {{-- Role Reference --}}
            <div class="card">
                <h3 class="font-semibold text-[#1E1B4B] text-sm mb-3">Role Reference</h3>
                <div class="space-y-2">
                    @foreach([
                        ['label' => 'Owner',   'badge' => 'bg-amber-100 text-amber-800',   'desc' => 'Full control. Cannot be removed.'],
                        ['label' => 'Admin',   'badge' => 'bg-violet-100 text-violet-700', 'desc' => 'All access except ownership transfer.'],
                        ['label' => 'Manager', 'badge' => 'bg-sky-100 text-sky-700',       'desc' => 'Daily operations. Billing optional.'],
                        ['label' => 'Member',  'badge' => 'bg-green-100 text-green-700',   'desc' => 'View-only + own tasks.'],
                        ['label' => 'Viewer',  'badge' => 'bg-gray-100 text-gray-600',     'desc' => 'Read-only access.'],
                    ] as $r)
                        <div class="p-2.5 rounded-xl bg-[#F0EFFA]">
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium text-[#1E1B4B]">{{ $r['label'] }}</span>
                                <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $r['badge'] }}">System</span>
                            </div>
                            <p class="text-[11px] text-gray-400 mt-0.5">{{ $r['desc'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Pending invitations summary (links to full table below) --}}
            @if($pendingInvites->count() > 0)
            <div class="card">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <div class="w-7 h-7 rounded-lg bg-amber-100 flex items-center justify-center shrink-0">
                            <svg class="w-3.5 h-3.5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-[#1E1B4B]">
                                {{ $pendingInvites->count() }} Pending Invitation{{ $pendingInvites->count() !== 1 ? 's' : '' }}
                            </p>
                            <p class="text-[11px] text-gray-400 mt-0.5">Awaiting acceptance</p>
                        </div>
                    </div>
                    <a href="#pending-invitations" class="text-[11px] font-semibold text-[#7B61FF] hover:text-purple-800">
                        Manage →
                    </a>
                </div>
            </div>
            @endif

        </div>
    </div>

    {{-- ── PENDING INVITATIONS TABLE ──────────────────────────────── --}}
    @if($pendingInvites->count() > 0 || $isAdmin || $actingRole === 'manager')
    <div id="pending-invitations" class="card">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-xl bg-amber-100 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                </div>
                <div>
                    <h3 class="font-bold text-[#1E1B4B] text-base">Pending Invitations</h3>
                    <p class="text-xs text-gray-400 mt-0.5">Invitations awaiting acceptance</p>
                </div>
            </div>
            @if($pendingInvites->count() > 0)
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold bg-amber-100 text-amber-700">
                    {{ $pendingInvites->count() }}
                </span>
            @endif
        </div>

        @if($pendingInvites->count() === 0)
            <div class="flex flex-col items-center py-10 text-center">
                <img src="/images/mascots/r-bunny-sleeping.webp" alt="" class="w-12 h-12 object-contain mb-3 opacity-60" loading="lazy">
                <p class="text-sm font-semibold text-gray-500">No pending invitations</p>
                <p class="text-xs text-gray-400 mt-1">All invitations have been accepted or you haven't sent any yet.</p>
                @if($isAdmin || $actingRole === 'manager')
                    <button class="btn-primary mt-4 text-sm"
                            onclick="document.getElementById('invite-modal').classList.remove('hidden')">
                        Invite a Team Member
                    </button>
                @endif
            </div>
        @else
            {{-- Desktop table --}}
            <div class="hidden sm:block overflow-x-auto -mx-5 -mb-5">
                <table class="w-full">
                    <thead>
                        <tr class="table-head">
                            <th class="pl-5">Email</th>
                            <th>Role</th>
                            <th>Sent</th>
                            <th>Expires</th>
                            <th>Reminders</th>
                            @if($isAdmin)<th class="pr-5 text-right">Actions</th>@endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($pendingInvites as $invite)
                            @php
                                $expiringSoon    = $invite->expires_at->diffInHours(now()) <= 24;
                                $canManualRemind = ! $invite->last_manual_resend_at || now()->gte($invite->last_manual_resend_at->addHours(24));
                                $autoReminders   = $invite->reminder_count ?? 0;
                            @endphp
                            <tr class="table-row" x-data="{ revoking: false, resending: false }">
                                <td class="pl-5">
                                    <div class="flex items-center gap-2">
                                        <div class="w-7 h-7 rounded-lg bg-[#EDE9FE] flex items-center justify-center shrink-0">
                                            <svg class="w-3 h-3 text-[#7B61FF]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                            </svg>
                                        </div>
                                        <span class="text-sm font-medium text-[#1E1B4B]">{{ $invite->email }}</span>
                                    </div>
                                </td>
                                <td>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold
                                        {{ match($invite->role) {
                                            'admin'   => 'bg-violet-100 text-violet-700',
                                            'manager' => 'bg-sky-100 text-sky-700',
                                            'member'  => 'bg-green-100 text-green-700',
                                            default   => 'bg-gray-100 text-gray-600',
                                        } }}">
                                        {{ ucfirst($invite->role) }}
                                    </span>
                                </td>
                                <td>
                                    <span class="text-xs text-gray-500">{{ $invite->created_at->format('M j, Y') }}</span>
                                </td>
                                <td>
                                    @if($expiringSoon)
                                        <span class="inline-flex items-center gap-1 text-xs font-semibold text-red-600">
                                            <span class="w-1.5 h-1.5 rounded-full bg-red-500 inline-block"></span>
                                            {{ $invite->expires_at->diffForHumans() }}
                                        </span>
                                    @else
                                        <span class="text-xs text-gray-500">{{ $invite->expires_at->format('M j, Y') }}</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="text-xs text-gray-500">{{ $autoReminders }}/3 sent</span>
                                </td>
                                @if($isAdmin)
                                <td class="pr-5 text-right" @click.stop>
                                    <div class="flex items-center justify-end gap-2" x-show="!revoking">
                                        {{-- Resend --}}
                                        <form method="POST" action="{{ route('tenant.invitations.resend', [$tenant->id, $invite->id]) }}"
                                              @submit="resending = true">
                                            @csrf
                                            <button type="submit" :disabled="resending"
                                                    class="text-xs px-2.5 py-1.5 rounded-lg border border-gray-200 text-gray-600 hover:border-violet-300 hover:text-violet-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                                                <span x-show="!resending">Resend</span>
                                                <span x-show="resending">Sending…</span>
                                            </button>
                                        </form>

                                        {{-- Send reminder now --}}
                                        @if($canManualRemind)
                                        <form method="POST" action="{{ route('tenant.invitations.remind-now', [$tenant->id, $invite->id]) }}">
                                            @csrf
                                            <button type="submit"
                                                    class="text-xs px-2.5 py-1.5 rounded-lg border border-violet-200 text-violet-600 hover:bg-violet-50 transition-colors">
                                                Remind
                                            </button>
                                        </form>
                                        @endif

                                        {{-- Revoke trigger --}}
                                        <button @click="revoking = true"
                                                class="text-xs px-2.5 py-1.5 rounded-lg border border-red-200 text-red-500 hover:bg-red-50 transition-colors">
                                            Cancel
                                        </button>
                                    </div>

                                    {{-- Inline revoke confirmation --}}
                                    <div x-show="revoking" x-cloak
                                         class="flex items-center justify-end gap-2">
                                        <span class="text-xs text-gray-500">Cancel this invite?</span>
                                        <button @click="revoking = false"
                                                class="text-xs px-2 py-1 rounded-lg text-gray-500 hover:bg-gray-100 transition-colors">
                                            Keep
                                        </button>
                                        <form method="POST" action="{{ route('tenant.invitations.revoke', [$tenant->id, $invite->id]) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                    class="text-xs px-2.5 py-1.5 rounded-lg bg-red-500 text-white hover:bg-red-600 transition-colors font-semibold">
                                                Yes, cancel
                                            </button>
                                        </form>
                                    </div>
                                </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Mobile cards --}}
            <div class="sm:hidden space-y-3">
                @foreach($pendingInvites as $invite)
                    @php
                        $expiringSoon    = $invite->expires_at->diffInHours(now()) <= 24;
                        $canManualRemind = ! $invite->last_manual_resend_at || now()->gte($invite->last_manual_resend_at->addHours(24));
                    @endphp
                    <div class="p-3 rounded-2xl border border-gray-100 space-y-2"
                         x-data="{ revoking: false, resending: false }">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-[#1E1B4B] truncate">{{ $invite->email }}</p>
                                <div class="flex items-center gap-1.5 mt-1 flex-wrap">
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-semibold
                                        {{ match($invite->role) {
                                            'admin'   => 'bg-violet-100 text-violet-700',
                                            'manager' => 'bg-sky-100 text-sky-700',
                                            'member'  => 'bg-green-100 text-green-700',
                                            default   => 'bg-gray-100 text-gray-600',
                                        } }}">
                                        {{ ucfirst($invite->role) }}
                                    </span>
                                    @if($expiringSoon)
                                        <span class="text-[10px] font-semibold text-red-600">Expires soon</span>
                                    @else
                                        <span class="text-[10px] text-gray-400">expires {{ $invite->expires_at->diffForHumans() }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        @if($isAdmin)
                        <div x-show="!revoking" class="flex flex-wrap gap-2">
                            <form method="POST" action="{{ route('tenant.invitations.resend', [$tenant->id, $invite->id]) }}"
                                  @submit="resending = true">
                                @csrf
                                <button type="submit" :disabled="resending"
                                        class="text-xs px-3 py-1.5 rounded-lg border border-gray-200 text-gray-600 hover:border-violet-300 hover:text-violet-700 transition-colors disabled:opacity-50">
                                    <span x-show="!resending">Resend</span>
                                    <span x-show="resending">Sending…</span>
                                </button>
                            </form>

                            @if($canManualRemind)
                            <form method="POST" action="{{ route('tenant.invitations.remind-now', [$tenant->id, $invite->id]) }}">
                                @csrf
                                <button type="submit"
                                        class="text-xs px-3 py-1.5 rounded-lg border border-violet-200 text-violet-600 hover:bg-violet-50 transition-colors">
                                    Remind
                                </button>
                            </form>
                            @endif

                            <button @click="revoking = true"
                                    class="text-xs px-3 py-1.5 rounded-lg border border-red-200 text-red-500 hover:bg-red-50 transition-colors">
                                Cancel invite
                            </button>
                        </div>

                        <div x-show="revoking" x-cloak class="flex items-center gap-2 pt-1">
                            <span class="text-xs text-gray-500 flex-1">Cancel this invite?</span>
                            <button @click="revoking = false"
                                    class="text-xs px-2.5 py-1.5 rounded-lg text-gray-500 hover:bg-gray-100 transition-colors">
                                Keep
                            </button>
                            <form method="POST" action="{{ route('tenant.invitations.revoke', [$tenant->id, $invite->id]) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                        class="text-xs px-3 py-1.5 rounded-lg bg-red-500 text-white hover:bg-red-600 transition-colors font-semibold">
                                    Yes, cancel
                                </button>
                            </form>
                        </div>
                        @endif

                        @if($expiringSoon && $isAdmin)
                        <div class="flex items-center gap-2 bg-amber-50 rounded-lg px-2.5 py-2">
                            <img src="/images/mascots/r-bunny-warning-error.webp" alt="" class="w-5 h-5 object-contain shrink-0" loading="lazy">
                            <p class="text-[10px] text-amber-700 font-medium">Expires {{ $invite->expires_at->diffForHumans() }}. Resend to extend.</p>
                        </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>
    @endif

</div>

{{-- Invite Modal --}}
@if($isAdmin || $actingRole === 'manager')
<div id="invite-modal"
     class="{{ $errors->any() ? '' : 'hidden' }} fixed inset-0 z-50 flex items-center justify-center p-4"
     style="background:rgba(0,0,0,.5)"
     x-data
     @keydown.escape.window="document.getElementById('invite-modal').classList.add('hidden')">

    {{-- Card — always centered, always constrained to 440px --}}
    <div class="bg-white rounded-2xl shadow-2xl w-full overflow-hidden"
         style="max-width:440px; max-height:90vh; overflow-y:auto"
         @click.stop>

        {{-- Header --}}
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 sticky top-0 bg-white z-10">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-xl bg-violet-100 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                    </svg>
                </div>
                <h3 class="font-bold text-[#1E1B4B] text-base">Invite Team Member</h3>
            </div>
            <button onclick="document.getElementById('invite-modal').classList.add('hidden')"
                    class="w-8 h-8 flex items-center justify-center rounded-xl text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors"
                    aria-label="Close">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <form method="POST" action="{{ route('tenant.users.invite', $tenant->id) }}" class="p-5 space-y-4"
              x-data="{ sending: false }"
              @submit="if (!$el.checkValidity()) return; sending = true">
            @csrf

            {{-- Inline error --}}
            @if($errors->any())
                <div class="flex items-start gap-2.5 bg-red-50 border border-red-200 rounded-xl px-3.5 py-3">
                    <svg class="w-4 h-4 text-red-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <p class="text-sm text-red-600 font-medium">{{ $errors->first() }}</p>
                </div>
            @endif

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5" for="inv-email">Email address</label>
                <input id="inv-email" name="email" type="email" required autocomplete="email"
                       class="w-full border {{ $errors->has('email') ? 'border-red-300 bg-red-50' : 'border-gray-200 bg-gray-50' }} rounded-xl px-3.5 py-2.5 text-sm text-gray-900 outline-none focus:border-violet-400 focus:ring-2 focus:ring-violet-100 transition-all"
                       placeholder="colleague@company.com" value="{{ old('email') }}">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5" for="inv-role">Role</label>
                <select id="inv-role" name="role" required
                        class="w-full border {{ $errors->has('role') ? 'border-red-300 bg-red-50' : 'border-gray-200 bg-gray-50' }} rounded-xl px-3.5 py-2.5 text-sm text-gray-900 outline-none focus:border-violet-400 focus:ring-2 focus:ring-violet-100 transition-all">
                    <option value="">Select a role…</option>
                    @if(in_array($actingRole, ['owner', 'admin']))
                        <option value="admin"   {{ old('role') === 'admin'   ? 'selected' : '' }}>Admin — Full access except ownership transfer</option>
                        <option value="manager" {{ old('role') === 'manager' ? 'selected' : '' }}>Manager — Daily operations, billing optional</option>
                    @endif
                    <option value="member"  {{ old('role') === 'member'  ? 'selected' : '' }}>Member — Standard team access</option>
                    <option value="viewer"  {{ old('role') === 'viewer'  ? 'selected' : '' }}>Viewer — Read-only access</option>
                </select>
                <p class="text-[11px] text-gray-400 mt-1.5">You can only invite roles below your own level.</p>
            </div>

            {{-- Action buttons — always visible --}}
            <div class="flex gap-2.5 pt-2">
                <button type="button"
                        onclick="document.getElementById('invite-modal').classList.add('hidden')"
                        class="flex-1 border border-gray-200 rounded-xl py-2.5 text-sm font-medium text-gray-600 hover:bg-gray-50 transition-colors">
                    Cancel
                </button>
                <button type="submit"
                        :disabled="sending"
                        class="flex-1 bg-[#7c3aed] hover:bg-[#6d28d9] disabled:opacity-60 disabled:cursor-not-allowed text-white rounded-xl py-2.5 text-sm font-semibold transition-colors flex items-center justify-center gap-2">
                    <template x-if="!sending">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                        </svg>
                    </template>
                    <svg x-show="sending" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                    <span x-text="sending ? 'Sending invite…' : 'Send Invitation'"></span>
                </button>
            </div>
        </form>
    </div>
</div>
@endif

@endsection
