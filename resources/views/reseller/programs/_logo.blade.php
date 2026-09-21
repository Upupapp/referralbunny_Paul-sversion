@php
    // Resolve only bundled, tenant-scoped logos; no remote image requests.
    $logoPath = null;
    if ($p->tenant_id !== 'lgu-ids'
        && preg_match('/^[a-zA-Z0-9-]+$/', (string) $p->tenant_id)
        && \Illuminate\Support\Str::isUuid((string) $p->id)) {
        $candidate = 'images/programs/'.$p->tenant_id.'/'.$p->id.'.png';
        if (is_file(public_path($candidate))) $logoPath = asset($candidate);
    }
    $words = preg_split('/\s+/u', trim($p->name), -1, PREG_SPLIT_NO_EMPTY);
    $initials = mb_strtoupper(implode('', array_map(fn($word) => mb_substr($word, 0, 1), array_slice($words, 0, 2))));
@endphp
<div class="program-avatar" @if($logoPath) x-data="{ failed: false }" style="background:white;border:1px solid #dceee6;padding:9px" @endif>
    @if($logoPath)
        <img src="{{ $logoPath }}" alt="{{ $p->name }} logo" width="74" height="74" x-show="!failed" x-on:error="failed = true" style="width:100%;height:100%;object-fit:contain">
        <span x-show="failed" x-cloak style="color:#0f766e">{{ $initials ?: '?' }}</span>
    @else
        <span aria-hidden="true">{{ $initials ?: '?' }}</span>
    @endif
</div>
