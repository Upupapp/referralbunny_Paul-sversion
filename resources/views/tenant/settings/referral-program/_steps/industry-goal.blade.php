<div class="space-y-5">
    <div>
        <label class="form-label">Industry <span class="text-red-500">*</span></label>
        <select x-model="data.industry" class="form-input">
            <option value="">Select an industry…</option>
            @foreach($options['industries'] as $key => $label)
            <option value="{{ $key }}">{{ $label }}</option>
            @endforeach
        </select>
        <p x-show="errors.industry" x-cloak class="text-xs text-red-600 mt-1" x-text="errors.industry?.[0]"></p>
    </div>

    <div>
        <label class="form-label">Sub-industries (optional)</label>
        <input type="text" x-model="subIndustriesText"
               @input="data.sub_industries = subIndustriesText.split(',').map(s => s.trim()).filter(Boolean).slice(0, 3)"
               class="form-input" placeholder="Up to 3, comma-separated (e.g. Mobile apps, Cloud hosting)">
        <p x-show="errors.sub_industries" x-cloak class="text-xs text-red-600 mt-1" x-text="errors.sub_industries?.[0]"></p>
    </div>

    <div>
        <label class="form-label">What's your main referral goal? <span class="text-red-500">*</span></label>
        <div class="space-y-2 mt-2">
            @foreach($options['goals'] as $key => $label)
            <label class="flex items-center gap-3 p-3 rounded-xl border cursor-pointer transition-colors"
                   :class="data.referral_goal === '{{ $key }}' ? 'border-[#7B61FF] bg-purple-50' : 'border-gray-200 hover:border-purple-200'">
                <input type="radio" name="referral_goal" value="{{ $key }}" x-model="data.referral_goal" class="accent-[#7B61FF]">
                <span class="text-sm text-[#1E1B4B]">{{ $label }}</span>
            </label>
            @endforeach
        </div>
        <p x-show="errors.referral_goal" x-cloak class="text-xs text-red-600 mt-1" x-text="errors.referral_goal?.[0]"></p>
    </div>
</div>
