@extends('layouts.app')
@section('title', 'Users & Roles')

@section('nav')
    @include('tenant._nav')
@endsection

@section('topbar-actions')
    @if($isAdmin)
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

    {{-- Flash messages --}}
    @if(session('success'))
        <div class="flex items-center gap-3 bg-green-50 border border-green-200 rounded-2xl px-4 py-3">
            <svg class="w-4 h-4 text-green-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            <p class="text-sm text-green-700 font-medium">{{ session('success') }}</p>
        </div>
    @endif

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
                {{ $memberships->count() }} member{{ $memberships->count() !== 1 ? 's' : '' }}
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
                <div class="card flex flex-col sm:flex-row sm:items-center gap-4">
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
                    </div>

                    {{-- Actions (admin only) --}}
                    @if($isAdmin && $m->role !== 'owner')
                        <div class="flex items-center gap-2 shrink-0" x-data="{ open: false }">
                            @if($m->role === 'manager')
                                {{-- Billing toggle for managers --}}
                                <form method="POST" action="{{ route('tenant.users.billing-toggle', [$tenant->id, $u->id]) }}">
                                    @csrf
                                    <input type="hidden" name="enable" value="{{ $m->can_manage_billing ? '0' : '1' }}">
                                    <button type="submit"
                                            class="text-xs px-2.5 py-1.5 rounded-lg border border-gray-200 text-gray-500 hover:border-violet-300 hover:text-violet-700 transition-colors"
                                            title="{{ $m->can_manage_billing ? 'Disable billing access' : 'Enable billing access' }}">
                                        {{ $m->can_manage_billing ? 'Billing On' : 'Billing Off' }}
                                    </button>
                                </form>
                            @endif

                            <div class="relative" x-data="{ open: false }">
                                <button @click="open = !open"
                                        class="w-8 h-8 flex items-center justify-center rounded-xl text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M12 5a1.5 1.5 0 110-3 1.5 1.5 0 010 3zm0 7a1.5 1.5 0 110-3 1.5 1.5 0 010 3zm0 7a1.5 1.5 0 110-3 1.5 1.5 0 010 3z"/>
                                    </svg>
                                </button>
                                <div x-show="open" @click.away="open = false"
                                     class="absolute right-0 mt-1 w-44 bg-white border border-gray-100 rounded-xl shadow-lg z-10 py-1 text-sm">
                                    @if($m->status === 'active')
                                        <form method="POST" action="{{ route('tenant.users.deactivate', [$tenant->id, $u->id]) }}">
                                            @csrf
                                            <button type="submit" class="w-full text-left px-3.5 py-2 text-gray-600 hover:bg-gray-50 hover:text-red-600 transition-colors">
                                                Deactivate
                                            </button>
                                        </form>
                                    @endif
                                    <form method="POST" action="{{ route('tenant.users.remove', [$tenant->id, $u->id]) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                class="w-full text-left px-3.5 py-2 text-red-500 hover:bg-red-50 transition-colors"
                                                onclick="return confirm('Remove this user from the workspace?')">
                                            Remove from workspace
                                        </button>
                                    </form>
                                </div>
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

            {{-- Pending Invitations --}}
            <div class="card">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="font-semibold text-[#1E1B4B] text-sm">Pending Invitations</h3>
                    @if($pendingInvites->count() > 0)
                        <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[11px] font-semibold bg-amber-100 text-amber-700">
                            {{ $pendingInvites->count() }}
                        </span>
                    @endif
                </div>
                @forelse($pendingInvites as $invite)
                    @php
                        $isExpiringSoon  = $invite->expires_at->diffInHours(now()) <= 24;
                        $canManualRemind = ! $invite->last_manual_resend_at
                            || now()->gte($invite->last_manual_resend_at->addHours(24));
                        $autoReminders   = $invite->reminder_count ?? 0;
                    @endphp
                    <div class="py-3 border-b border-gray-50 last:border-0">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0 flex-1">
                                <p class="text-xs font-medium text-[#1E1B4B] truncate">{{ $invite->email }}</p>
                                <div class="flex flex-wrap items-center gap-1.5 mt-1">
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-semibold
                                        {{ match($invite->role) {
                                            'admin'   => 'bg-violet-100 text-violet-700',
                                            'manager' => 'bg-sky-100 text-sky-700',
                                            'member'  => 'bg-green-100 text-green-700',
                                            default   => 'bg-gray-100 text-gray-600',
                                        } }}">
                                        {{ ucfirst($invite->role) }}
                                    </span>
                                    @if($isExpiringSoon)
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-semibold bg-red-100 text-red-600">
                                            Expires soon
                                        </span>
                                    @else
                                        <span class="text-[10px] text-gray-400">
                                            expires {{ $invite->expires_at->diffForHumans() }}
                                        </span>
                                    @endif
                                    @if($autoReminders > 0)
                                        <span class="text-[10px] text-gray-400">
                                            · {{ $autoReminders }}/3 reminder{{ $autoReminders !== 1 ? 's' : '' }} sent
                                        </span>
                                    @endif
                                </div>
                            </div>

                            @if($isAdmin)
                                <div class="flex items-center gap-1 shrink-0" x-data="{ open: false }">
                                    <div class="relative">
                                        <button @click="open = !open"
                                                class="w-7 h-7 flex items-center justify-center rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors">
                                            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M12 5a1.5 1.5 0 110-3 1.5 1.5 0 010 3zm0 7a1.5 1.5 0 110-3 1.5 1.5 0 010 3zm0 7a1.5 1.5 0 110-3 1.5 1.5 0 010 3z"/>
                                            </svg>
                                        </button>
                                        <div x-show="open" @click.away="open = false"
                                             class="absolute right-0 mt-1 w-48 bg-white border border-gray-100 rounded-xl shadow-lg z-10 py-1 text-sm">

                                            {{-- Resend (extends expiry) --}}
                                            <form method="POST" action="{{ route('tenant.invitations.resend', [$tenant->id, $invite->id]) }}">
                                                @csrf
                                                <button type="submit"
                                                        class="w-full text-left px-3.5 py-2 text-gray-600 hover:bg-gray-50 transition-colors text-xs">
                                                    Resend &amp; extend 7 days
                                                </button>
                                            </form>

                                            {{-- Manual reminder (rate-limited) --}}
                                            <form method="POST" action="{{ route('tenant.invitations.remind-now', [$tenant->id, $invite->id]) }}">
                                                @csrf
                                                <button type="submit"
                                                        @if(! $canManualRemind) disabled title="Available again {{ optional($invite->last_manual_resend_at)->addHours(24)->diffForHumans() }}" @endif
                                                        class="w-full text-left px-3.5 py-2 transition-colors text-xs
                                                            {{ $canManualRemind ? 'text-violet-600 hover:bg-violet-50' : 'text-gray-300 cursor-not-allowed' }}">
                                                    Send reminder now
                                                </button>
                                            </form>

                                            <div class="border-t border-gray-50 my-1"></div>

                                            {{-- Revoke --}}
                                            <form method="POST" action="{{ route('tenant.invitations.revoke', [$tenant->id, $invite->id]) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                        onclick="return confirm('Revoke this invitation? The invitee will be notified.')"
                                                        class="w-full text-left px-3.5 py-2 text-red-500 hover:bg-red-50 transition-colors text-xs">
                                                    Revoke invitation
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>

                        {{-- R Bunny warning for near-expiry --}}
                        @if($isExpiringSoon && $isAdmin)
                            <div class="mt-2 flex items-center gap-2 bg-amber-50 rounded-lg px-2.5 py-2">
                                <img src="/images/mascots/r-bunny-warning-error.webp"
                                     alt="" class="w-6 h-6 object-contain shrink-0" loading="lazy">
                                <p class="text-[10px] text-amber-700 font-medium">
                                    This invitation expires {{ $invite->expires_at->diffForHumans() }}.
                                    Resend to extend the deadline.
                                </p>
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="flex flex-col items-center py-8 text-center">
                        <img src="/images/mascots/r-bunny-sleeping.webp"
                             alt="" class="w-12 h-12 object-contain mb-2" loading="lazy">
                        <p class="text-xs text-gray-400">No pending invitations</p>
                    </div>
                @endforelse
            </div>

        </div>
    </div>

</div>

{{-- Invite Modal --}}
@if($isAdmin)
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

        <form method="POST" action="{{ route('tenant.users.invite', $tenant->id) }}" class="p-5 space-y-4">
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
                        class="flex-1 bg-[#7c3aed] hover:bg-[#6d28d9] text-white rounded-xl py-2.5 text-sm font-semibold transition-colors flex items-center justify-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                    </svg>
                    Send Invitation
                </button>
            </div>
        </form>
    </div>
</div>
@endif

@endsection
