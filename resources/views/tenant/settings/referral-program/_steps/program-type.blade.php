<div class="space-y-3">
    @if($recommendation['program_type'] ?? null)
    <div class="flex items-center gap-2 text-xs text-purple-700 bg-purple-50 border border-purple-100 rounded-lg px-3 py-2">
        <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.539 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.539-1.118l1.518-4.674a1 1 0 00-.363-1.118L2.075 10.1c-.783-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
        Recommended for your industry: <strong>{{ $options['programTypes'][$recommendation['program_type']]['label'] ?? '' }}</strong>
    </div>
    @endif

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        @foreach($options['programTypes'] as $key => $type)
        <label class="relative flex flex-col p-4 rounded-xl border cursor-pointer transition-colors focus-within:ring-2 focus-within:ring-[#7B61FF] focus-within:ring-offset-1"
               :class="data.program_type === '{{ $key }}' ? 'border-[#7B61FF] bg-purple-50 ring-1 ring-[#7B61FF]' : 'border-gray-200 hover:border-purple-200'">
            <input type="radio" name="program_type" value="{{ $key }}" x-model="data.program_type" class="sr-only">
            @if(($recommendation['program_type'] ?? null) === $key)
            <span class="absolute -top-2 -right-2 badge badge-purple text-[10px] py-0.5 px-2 shadow-sm">Recommended</span>
            @endif
            <div class="flex items-center justify-between gap-2 mb-1">
                <p class="text-sm font-semibold text-[#1E1B4B]">{{ $type['label'] }}</p>
                <span class="badge badge-gray text-[10px] py-0.5 px-1.5 shrink-0">{{ $type['complexity'] }}</span>
            </div>
            <p class="text-xs text-gray-500 mb-2 leading-relaxed">{{ $type['description'] }}</p>
            <div class="text-[11px] text-gray-400 space-y-0.5 mt-auto">
                <p><span class="font-medium text-gray-500">Best for:</span> {{ $type['best_for'] }}</p>
                <p><span class="font-medium text-gray-500">Participants:</span> {{ $type['participants'] }}</p>
                <p><span class="font-medium text-gray-500">Rewards:</span> {{ $type['reward_style'] }}</p>
            </div>
        </label>
        @endforeach
    </div>
    <p x-show="errors.program_type" x-cloak class="text-xs text-red-600 mt-1" x-text="errors.program_type?.[0]"></p>
</div>
