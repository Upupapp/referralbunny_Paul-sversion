@extends('layouts.app')
@section('title', $tenant->name)
@section('nav') @include('platform._nav') @endsection

@section('topbar-actions')
    <a href="{{ route('tenant.dashboard', $tenant->id) }}" class="btn-primary">
        Access Tenant →
    </a>
@endsection

@section('content')
<div class="space-y-6">

    {{-- Back --}}
    <a href="{{ route('platform.tenants') }}" class="inline-flex items-center gap-2 text-sm text-gray-500 hover:text-gray-700">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        Back to Tenants
    </a>

    {{-- Header --}}
    <div class="card">
        <div class="flex flex-col sm:flex-row sm:items-center gap-4">
            <div class="w-16 h-16 rounded-2xl flex items-center justify-center text-white text-xl font-bold shrink-0"
                 style="background-color: {{ $tenant->accent_color ?? '#7B61FF' }}">
                {{ strtoupper(substr($tenant->name, 0, 2)) }}
            </div>
            <div class="flex-1 min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="text-xl font-bold text-[#1E1B4B]">{{ $tenant->name }}</h2>
                    <span @class(['badge', 'badge-green' => $tenant->status === 'active', 'badge-blue' => $tenant->status === 'trial', 'badge-gray' => $tenant->status === 'inactive'])>
                        {{ ucfirst($tenant->status) }}
                    </span>
                </div>
                <p class="text-gray-500 text-sm mt-0.5">{{ $tenant->program_name }} · {{ $tenant->industry ?? 'No industry' }}</p>
                <p class="text-gray-400 text-xs mt-0.5">ID: {{ $tenant->id }}</p>
            </div>
            <div class="flex flex-wrap gap-2 shrink-0">
                <button x-data @click="$dispatch('open-extend-access')" class="btn-secondary">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Extend Access
                </button>
                <a href="{{ route('tenant.dashboard', $tenant->id) }}" class="btn-primary">Open Tenant</a>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Left: Details --}}
        <div class="lg:col-span-2 space-y-6">

            {{-- Info cards --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="card space-y-3">
                    <h3 class="font-semibold text-[#1E1B4B] text-sm">Tenant Info</h3>
                    @foreach([
                        ['Business', $tenant->business_name],
                        ['Contact', $tenant->contact_person],
                        ['Email', $tenant->contact_email],
                        ['Phone', $tenant->contact_phone],
                    ] as [$label, $value])
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-400">{{ $label }}</span>
                        <span class="text-gray-700 font-medium truncate ml-2">{{ $value ?: '—' }}</span>
                    </div>
                    @endforeach
                </div>
                <div class="card space-y-3">
                    <h3 class="font-semibold text-[#1E1B4B] text-sm">Admin Account</h3>
                    @foreach([
                        ['Name', $tenant->admin_name],
                        ['Email', $tenant->admin_email],
                        ['Created', $tenant->created_at->format('M d, Y')],
                    ] as [$label, $value])
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-400">{{ $label }}</span>
                        <span class="text-gray-700 font-medium truncate ml-2">{{ $value ?: '—' }}</span>
                    </div>
                    @endforeach
                    @if($metric)
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-400">Health Score</span>
                        <span class="font-semibold {{ $metric->health_score >= 80 ? 'text-emerald-600' : ($metric->health_score >= 50 ? 'text-orange-500' : 'text-red-500') }}">
                            {{ $metric->health_score }}/100
                        </span>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Recent Leads --}}
            <div class="card p-0 overflow-hidden">
                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                    <h3 class="font-semibold text-[#1E1B4B]">Recent Leads</h3>
                    <a href="{{ route('tenant.leads', $tenant->id) }}" class="text-sm text-purple-600 hover:text-purple-700">View all</a>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead><tr class="table-head">
                            <th>Name</th><th>Stage</th><th>Value</th><th>Status</th>
                        </tr></thead>
                        <tbody>
                        @forelse($leads->take(6) as $lead)
                            <tr class="table-row">
                                <td class="font-medium text-[#1E1B4B]">{{ $lead->name }}</td>
                                <td class="capitalize text-gray-500">{{ str_replace('_',' ',$lead->stage) }}</td>
                                <td class="font-semibold">₱{{ number_format($lead->deal_value) }}</td>
                                <td><span @class(['badge','badge-green'=>$lead->status==='active','badge-orange'=>$lead->status==='expiring','badge-red'=>$lead->status==='expired','badge-gray'=>true])>{{ $lead->status }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-8 text-center text-gray-400 text-sm">No leads yet</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Right: Metric + Notifications --}}
        <div class="space-y-4">

            @if($metric)
            <div class="card">
                <h3 class="font-semibold text-[#1E1B4B] mb-4">Health Score</h3>
                <div class="flex items-center justify-center mb-4">
                    <div class="relative w-24 h-24">
                        <svg class="w-24 h-24 -rotate-90" viewBox="0 0 100 100">
                            <circle cx="50" cy="50" r="40" fill="none" stroke="#EDE9FE" stroke-width="10"/>
                            <circle cx="50" cy="50" r="40" fill="none"
                                    stroke="{{ $metric->health_score >= 80 ? '#10B981' : ($metric->health_score >= 50 ? '#F59E0B' : '#EF4444') }}"
                                    stroke-width="10"
                                    stroke-dasharray="{{ round($metric->health_score * 2.51327) }} 251.327"
                                    stroke-linecap="round"/>
                        </svg>
                        <div class="absolute inset-0 flex items-center justify-center">
                            <span class="text-xl font-bold text-[#1E1B4B]">{{ $metric->health_score }}</span>
                        </div>
                    </div>
                </div>
                <div class="space-y-2">
                    @foreach([
                        ['Subscription', ucfirst($metric->subscription_status)],
                        ['Leads', $metric->leads_count],
                        ['Setup', $metric->setup_completion_percentage . '%'],
                    ] as [$label, $value])
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-400">{{ $label }}</span>
                        <span class="font-medium text-gray-700">{{ $value }}</span>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            <div class="card">
                <h3 class="font-semibold text-[#1E1B4B] mb-3">Recent Alerts</h3>
                <div class="space-y-2">
                    @forelse($notifications as $notif)
                        <div class="flex items-start gap-2 p-2.5 rounded-xl bg-[#F0EFFA]">
                            <span @class(['w-2 h-2 rounded-full mt-1.5 shrink-0','bg-red-500'=>$notif->priority==='critical','bg-orange-500'=>$notif->priority==='high','bg-blue-400'=>$notif->priority==='medium','bg-gray-400'=>$notif->priority==='low'])></span>
                            <p class="text-xs text-gray-600 flex-1">{{ Str::limit($notif->message, 70) }}</p>
                        </div>
                    @empty
                        <p class="text-gray-400 text-sm text-center py-3">No alerts</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ═══════════════════════════════════════════
     EXTEND ACCESS MODAL
     ═══════════════════════════════════════════ --}}
<div x-data="extendAccessModal('{{ $tenant->id }}', '{{ $tenant->status }}')"
     @open-extend-access.window="open()"
     x-show="show" x-cloak
     class="fixed inset-0 bg-black/50 z-50 flex items-end sm:items-center justify-center p-4"
     @keydown.escape.window="show = false">

    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md" @click.stop>
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-purple-50 flex items-center justify-center">
                    <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <h3 class="font-semibold text-[#1E1B4B]">Extend Access</h3>
                    <p class="text-xs text-gray-400">{{ $tenant->name }}</p>
                </div>
            </div>
            <button @click="show = false" class="text-gray-400 hover:text-gray-600 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <div class="p-6 space-y-5">

            {{-- Quick presets --}}
            <div>
                <label class="form-label">Extend by</label>
                <div class="grid grid-cols-4 gap-2 mb-3">
                    <template x-for="preset in [30, 60, 90, 180]" :key="preset">
                        <button type="button"
                                @click="days = preset; custom = false"
                                :class="days === preset && !custom
                                    ? 'bg-[#7B61FF] text-white border-[#7B61FF]'
                                    : 'bg-white text-gray-600 border-gray-200 hover:border-purple-300 hover:text-purple-600'"
                                class="py-2.5 rounded-xl border text-sm font-semibold transition-colors">
                            <span x-text="preset"></span>d
                        </button>
                    </template>
                </div>
                <button type="button" @click="custom = !custom; if(custom) days = customDays"
                        :class="custom ? 'text-purple-600' : 'text-gray-400'"
                        class="text-xs font-medium hover:text-purple-600 transition-colors">
                    + Custom number of days
                </button>
                <div x-show="custom" class="mt-2">
                    <div class="flex items-center gap-2">
                        <input type="number" x-model.number="customDays" @input="days = customDays"
                               min="1" max="365" class="form-input w-28 text-center" placeholder="0">
                        <span class="text-sm text-gray-500">days</span>
                    </div>
                </div>
            </div>

            {{-- Current status info --}}
            <div class="p-3 rounded-xl bg-gray-50 text-sm space-y-1.5">
                <div class="flex justify-between">
                    <span class="text-gray-500">Current Status</span>
                    <span class="font-semibold capitalize"
                          :class="{
                              'text-emerald-600': tenantStatus === 'active',
                              'text-blue-600':    tenantStatus === 'trial',
                              'text-gray-500':    tenantStatus === 'inactive',
                              'text-red-500':     tenantStatus === 'suspended',
                          }" x-text="tenantStatus"></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Access extended by</span>
                    <span class="font-semibold text-purple-600" x-text="days + ' day' + (days !== 1 ? 's' : '')"></span>
                </div>
            </div>

            {{-- Reactivate toggle (shown only when inactive/suspended) --}}
            <div x-show="tenantStatus === 'inactive' || tenantStatus === 'suspended'"
                 class="flex items-center justify-between p-3 rounded-xl bg-amber-50 border border-amber-100">
                <div>
                    <p class="text-sm font-medium text-amber-800">Reactivate tenant</p>
                    <p class="text-xs text-amber-600 mt-0.5">Set status back to Trial after extending</p>
                </div>
                <button type="button" @click="reactivate = !reactivate"
                        :class="reactivate ? 'bg-amber-500' : 'bg-gray-300'"
                        class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none shrink-0 ml-3">
                    <span :class="reactivate ? 'translate-x-6' : 'translate-x-1'"
                          class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform shadow"></span>
                </button>
            </div>

            {{-- Note --}}
            <div>
                <label class="form-label">Internal note <span class="text-gray-400 font-normal">(optional)</span></label>
                <textarea x-model="note" class="form-input" rows="2"
                          placeholder="Reason for extension, e.g. client requested trial extension…"></textarea>
            </div>

            <p x-show="error" class="text-xs text-red-600 font-medium" x-text="error"></p>

            <div class="flex justify-end gap-3 pt-1">
                <button @click="show = false" class="btn-secondary">Cancel</button>
                <button @click="submit()" :disabled="saving || days < 1" class="btn-primary"
                        x-text="saving ? 'Extending…' : 'Extend Access'"></button>
            </div>
        </div>
    </div>
</div>

<script>
function extendAccessModal(tenantId, tenantStatus) {
    return {
        show:        false,
        saving:      false,
        error:       '',
        days:        30,
        custom:      false,
        customDays:  30,
        reactivate:  tenantStatus === 'inactive' || tenantStatus === 'suspended',
        note:        '',
        tenantStatus,

        open() { this.show = true; this.error = ''; },

        async submit() {
            if (this.days < 1) { this.error = 'Please select at least 1 day.'; return; }
            this.saving = true; this.error = '';
            try {
                const res = await fetch(`/api/billing/tenants/${tenantId}/extend-access`, {
                    method:  'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    },
                    body: JSON.stringify({
                        days:       this.days,
                        note:       this.note || null,
                        reactivate: this.reactivate,
                    }),
                });
                const data = await res.json();
                if (res.ok) {
                    this.show = false;
                    this.$dispatch('show-toast', { type: 'success', message: data.message });
                    // Update displayed tenant status if reactivated
                    if (data.tenant_status) this.tenantStatus = data.tenant_status;
                    setTimeout(() => window.location.reload(), 1200);
                } else {
                    this.error = data.message || 'Failed to extend access.';
                }
            } catch(e) {
                this.error = 'Network error. Please try again.';
            } finally {
                this.saving = false;
            }
        },
    };
}
</script>

{{-- ═══════════════════════════════════════════
     CONGRATULATION POPUP — tenant just created
     ═══════════════════════════════════════════ --}}
@if(session('tenant_just_created'))
<style>
@keyframes confetti-fall {
    0%   { transform: translateY(0) rotateZ(var(--r,0deg)); opacity:1; }
    100% { transform: translateY(900px) rotateZ(calc(var(--r,0deg) + 720deg)); opacity:0; }
}
@keyframes draw-ring {
    to { stroke-dashoffset: 0; }
}
@keyframes pop-in {
    0%   { opacity:0; transform:scale(0.82) translateY(28px); }
    65%  { transform:scale(1.03) translateY(-3px); }
    100% { opacity:1; transform:scale(1) translateY(0); }
}
@keyframes fade-up {
    from { opacity:0; transform:translateY(12px); }
    to   { opacity:1; transform:translateY(0); }
}
@keyframes pulse-ring {
    0%,100% { transform:scale(1); opacity:.4; }
    50%      { transform:scale(1.15); opacity:.15; }
}
.congrats-modal  { animation: pop-in .55s cubic-bezier(.34,1.56,.64,1) forwards; }
.fade-up-1 { animation: fade-up .45s ease-out .25s both; }
.fade-up-2 { animation: fade-up .45s ease-out .4s  both; }
.fade-up-3 { animation: fade-up .45s ease-out .55s both; }
.fade-up-4 { animation: fade-up .45s ease-out .7s  both; }
.draw-ring { animation: draw-ring .9s cubic-bezier(.4,0,.2,1) .15s both; }
.pulse-ring { animation: pulse-ring 2s ease-in-out infinite; }
</style>

<div x-data="{ show: true }"
     x-init="$nextTick(() => { if(show) setTimeout(launchConfetti, 80); })"
     x-show="show" x-cloak
     class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6">

    {{-- Blurred backdrop --}}
    <div class="absolute inset-0 bg-[#0D0B26]/75 backdrop-blur-md" @click="show = false"></div>

    {{-- Confetti layer --}}
    <div id="confetti-container" class="absolute inset-0 overflow-hidden pointer-events-none z-10"></div>

    {{-- Modal --}}
    <div class="relative z-20 w-full max-w-md congrats-modal" @click.stop>
        <div class="bg-white rounded-[28px] shadow-[0_32px_80px_rgba(123,97,255,0.25)] overflow-hidden">

            {{-- ── Gradient header ── --}}
            <div class="relative px-7 pt-10 pb-8 text-center overflow-hidden"
                 style="background:linear-gradient(145deg,#1E1B4B 0%,#3B0764 35%,#7B61FF 68%,#FF6CAB 100%)">

                {{-- Background orbs --}}
                <div class="absolute -top-10 -right-10 w-48 h-48 rounded-full" style="background:rgba(255,255,255,0.06)"></div>
                <div class="absolute bottom-0 -left-8 w-36 h-36 rounded-full" style="background:rgba(255,255,255,0.05)"></div>
                <div class="absolute top-6 left-12 w-16 h-16 rounded-full" style="background:rgba(255,255,255,0.04)"></div>

                {{-- Animated success ring --}}
                <div class="relative w-28 h-28 mx-auto mb-6">
                    {{-- Pulse ring --}}
                    <div class="absolute inset-0 rounded-full pulse-ring" style="background:rgba(255,255,255,0.15)"></div>
                    {{-- SVG ring --}}
                    <svg class="w-28 h-28 -rotate-90 absolute inset-0" viewBox="0 0 110 110">
                        <circle cx="55" cy="55" r="48" fill="none" stroke="rgba(255,255,255,0.12)" stroke-width="4"/>
                        <circle cx="55" cy="55" r="48" fill="none" stroke="white" stroke-width="4.5"
                                stroke-dasharray="302" stroke-dashoffset="302"
                                stroke-linecap="round" class="draw-ring"/>
                    </svg>
                    {{-- Inner circle --}}
                    <div class="absolute inset-3 rounded-full flex items-center justify-center fade-up-1"
                         style="background:rgba(255,255,255,0.15);backdrop-filter:blur(8px)">
                        <svg class="w-12 h-12 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                </div>

                {{-- Headline --}}
                <div class="fade-up-1">
                    <h2 class="text-[26px] font-bold text-white tracking-tight leading-tight">Tenant Created!</h2>
                    <p class="text-white/65 text-sm mt-1.5">Your new workspace is live and ready to go.</p>
                </div>

                {{-- Tenant pill --}}
                <div class="fade-up-2 mt-5 inline-flex items-center gap-3 px-4 py-2.5 rounded-2xl border"
                     style="background:rgba(255,255,255,0.1);border-color:rgba(255,255,255,0.18);backdrop-filter:blur(6px)">
                    <div class="w-9 h-9 rounded-xl flex items-center justify-center text-white text-xs font-bold shrink-0"
                         style="background-color:{{ $tenant->accent_color ?? '#7B61FF' }}">
                        {{ strtoupper(substr($tenant->name, 0, 2)) }}
                    </div>
                    <div class="text-left min-w-0">
                        <p class="text-white font-semibold text-sm truncate">{{ $tenant->name }}</p>
                        <p class="text-white/55 text-xs">{{ $tenant->industry ?? 'General' }}</p>
                    </div>
                    <span class="ml-1 shrink-0 text-[10px] font-semibold px-2 py-0.5 rounded-full"
                          style="background:rgba(96,165,250,0.25);color:#bfdbfe;border:1px solid rgba(147,197,253,0.3)">TRIAL</span>
                </div>
            </div>

            {{-- ── Body ── --}}
            <div class="px-6 py-6 space-y-4">

                <div class="fade-up-2">
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Get started in 3 steps</p>
                </div>

                <div class="fade-up-3 space-y-2.5">

                    {{-- Step 1: Open Portal --}}
                    <a href="{{ route('tenant.dashboard', $tenant->id) }}"
                       class="flex items-center gap-4 p-4 rounded-2xl border border-purple-100 transition-all group"
                       style="background:linear-gradient(135deg,#F5F3FF,#FDF2F8)">
                        <div class="w-11 h-11 rounded-xl flex items-center justify-center shrink-0 shadow-sm"
                             style="background:linear-gradient(135deg,#7B61FF,#FF6CAB)">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-[#1E1B4B]">Open Tenant Portal</p>
                            <p class="text-xs text-gray-500">Dashboard, records, settings, reports</p>
                        </div>
                        <svg class="w-4 h-4 text-purple-300 group-hover:text-purple-500 group-hover:translate-x-0.5 transition-all shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </a>

                    {{-- Step 2: Invite team --}}
                    <a href="{{ route('tenant.users', $tenant->id) }}"
                       class="flex items-center gap-4 p-4 rounded-2xl bg-gray-50 hover:bg-gray-100 border border-transparent hover:border-gray-200 transition-all group">
                        <div class="w-11 h-11 rounded-xl bg-emerald-100 flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0"/>
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-[#1E1B4B]">Invite Your Team</p>
                            <p class="text-xs text-gray-500">Add users, assign roles and permissions</p>
                        </div>
                        <svg class="w-4 h-4 text-gray-300 group-hover:text-gray-500 group-hover:translate-x-0.5 transition-all shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </a>

                    {{-- Step 3: Import data --}}
                    <a href="{{ route('platform.import') }}"
                       class="flex items-center gap-4 p-4 rounded-2xl bg-gray-50 hover:bg-gray-100 border border-transparent hover:border-gray-200 transition-all group">
                        <div class="w-11 h-11 rounded-xl bg-blue-100 flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"/>
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-[#1E1B4B]">Import Existing Data</p>
                            <p class="text-xs text-gray-500">Bulk upload records, contacts, referrers</p>
                        </div>
                        <svg class="w-4 h-4 text-gray-300 group-hover:text-gray-500 group-hover:translate-x-0.5 transition-all shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </a>
                </div>

                {{-- CTAs --}}
                <div class="fade-up-4 flex gap-2.5 pt-1">
                    <button @click="show = false"
                            class="flex-1 py-2.5 rounded-xl text-sm text-gray-500 hover:text-[#1E1B4B] hover:bg-gray-50 transition-all font-medium">
                        View details
                    </button>
                    <a href="{{ route('tenant.dashboard', $tenant->id) }}"
                       class="flex-[2] btn-primary text-sm justify-center">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                        </svg>
                        Open Portal
                    </a>
                </div>

                {{-- Program name sub-text --}}
                <p class="fade-up-4 text-center text-[11px] text-gray-400">
                    {{ $tenant->program_name }} · ID: <span class="font-mono">{{ substr($tenant->id, 0, 8) }}…</span>
                </p>
            </div>
        </div>

        {{-- Close button --}}
        <button @click="show = false"
                class="absolute -top-3 -right-3 w-9 h-9 rounded-full bg-white shadow-lg flex items-center justify-center text-gray-400 hover:text-gray-700 transition-colors border border-gray-100">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>
</div>

<script>
function launchConfetti() {
    const container = document.getElementById('confetti-container');
    if (!container) return;
    const colors = ['#7B61FF','#FF6CAB','#10B981','#F59E0B','#3B82F6','#EC4899','#8B5CF6','#14B8A6'];
    for (let i = 0; i < 110; i++) {
        const el = document.createElement('div');
        const color = colors[Math.floor(Math.random() * colors.length)];
        const isCircle = Math.random() > 0.55;
        const w = Math.random() * 10 + 5;
        const h = isCircle ? w : w * 0.45;
        const x = Math.random() * 100;
        const rotation = Math.random() * 360;
        const dur = (Math.random() * 2 + 1.8).toFixed(2);
        const delay = (Math.random() * 0.8).toFixed(2);
        el.style.cssText = `
            position:absolute;left:${x}%;top:-${h * 2}px;
            width:${w}px;height:${h}px;
            background:${color};opacity:.9;
            border-radius:${isCircle ? '50%' : '3px'};
            --r:${rotation}deg;
            animation:confetti-fall ${dur}s ease-in ${delay}s forwards;
        `;
        container.appendChild(el);
        setTimeout(() => el.remove(), (+dur + +delay) * 1000 + 300);
    }
}
</script>
@endif
@endsection
