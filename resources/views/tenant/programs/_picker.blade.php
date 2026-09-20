@if(isset($tenant) && $tenant->id !== 'lgu-ids' && config('programs.enabled'))
@php
    $pickerActor = auth('web')->user() ?? auth('tenant')->user();
    $canPickProgram = $pickerActor && \Illuminate\Support\Facades\Gate::forUser($pickerActor)->allows('viewAny', \App\Models\Program::class);
@endphp
@if($canPickProgram)
@php
    $pickerPrograms = app(\App\Services\Programs\ProgramNavigation::class)->programs($tenant->id);
    $pickerCurrent = $pickerPrograms->firstWhere('id', request()->route('programId') ?? session('program_dashboard.'.$tenant->id)) ?? $pickerPrograms->first();
@endphp
<div class="relative" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.stop="open = false; $refs.trigger.focus()">
    <button type="button" x-ref="trigger" @click="open = !open" :aria-expanded="open.toString()" aria-controls="company-program-picker"
            style="display:flex;align-items:center;gap:6px;padding:8px 12px;border:1px solid #e9d5ff;border-radius:12px;background:#faf5ff;color:#7e22ce;font-size:12px;font-weight:600;max-width:220px">
        <span class="truncate">{{ $pickerCurrent?->name ?? 'Programs' }}</span>
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m6 9 6 6 6-6"/></svg>
    </button>
    <div id="company-program-picker" x-show="open" x-cloak
         style="position:absolute;right:0;top:calc(100% + 8px);width:280px;max-width:85vw;z-index:60;background:white;border:1px solid #ede9fe;border-radius:16px;box-shadow:0 12px 30px #1e1b4b20;padding:8px">
        <p class="px-3 py-2 text-xs font-semibold text-gray-500">Your programs</p>
        <div style="max-height:280px;overflow-y:auto">
        @forelse($pickerPrograms as $pickerProgram)
            <a href="{{ request()->routeIs('tenant.dashboard', 'tenant.sub.dashboard', 'tenant.sub.home') ? route('tenant.dashboard', ['tenantId' => $tenant->id, 'program_id' => $pickerProgram->id]) : route('tenant.programs.workspace', [$tenant->id, $pickerProgram->id]) }}"
               class="block px-3 py-2 rounded-lg hover:bg-purple-50 text-sm" style="color:#1e1b4b"
               @if($pickerCurrent?->id === $pickerProgram->id) aria-current="page" @endif>
                <span class="block truncate font-semibold">{{ $pickerProgram->name }}</span>
                <span class="text-xs text-gray-500">{{ ucfirst($pickerProgram->status) }}</span>
            </a>
        @empty
            <p class="px-3 py-2 text-sm text-gray-500">No programs yet</p>
        @endforelse
        </div>
        <div class="border-t border-purple-100 mt-2 pt-2">
            <a href="{{ route('tenant.programs.index', $tenant->id) }}" class="block px-3 py-2 text-sm text-purple-700 rounded-lg hover:bg-purple-50">View all programs</a>
            @if(\Illuminate\Support\Facades\Gate::forUser($pickerActor)->allows('create', \App\Models\Program::class))
            <a href="{{ route('tenant.programs.create', $tenant->id) }}" class="block px-3 py-2 text-sm font-semibold text-purple-700 rounded-lg hover:bg-purple-50">+ Add Program</a>
            @endif
        </div>
    </div>
</div>
@endif
@endif
