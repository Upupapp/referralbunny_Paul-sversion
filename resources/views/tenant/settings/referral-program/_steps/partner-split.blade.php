<div class="space-y-4" x-data="{
        descriptions: @js(collect($options['partnerSplitTypes'])->map(fn($t) => $t['description'])->all()),
     }">

    <p class="text-sm text-gray-500 mb-2">
        Partners are a separate role from Referrers (see Step 4) — they can be added to a deal to share in its
        commission without gaining Referrer access to the rest of your workspace.
    </p>

    <label class="flex items-start gap-3 p-3 rounded-xl border cursor-pointer transition-colors"
           :class="data.allow_partners ? 'border-[#7B61FF] bg-purple-50' : 'border-gray-200 hover:border-purple-200'">
        <input type="checkbox" x-model="data.allow_partners" class="mt-0.5 accent-[#7B61FF]">
        <div>
            <p class="text-sm font-medium text-[#1E1B4B]">Allow Partners to split commission on deals</p>
            <p class="text-xs text-gray-400 mt-0.5">Turn this on if deals can involve a referral partner who shares in the Commission Pool.</p>
        </div>
    </label>

    <div class="space-y-4 pl-1" x-show="data.allow_partners" x-cloak>
        <label class="flex items-start gap-3 p-3 rounded-xl border border-gray-200 cursor-pointer">
            <input type="checkbox" x-model="data.require_approval" class="mt-0.5 accent-[#7B61FF]">
            <div>
                <p class="text-sm font-medium text-[#1E1B4B]">Require approval before a Partner is added to a deal</p>
                <p class="text-xs text-gray-400 mt-0.5">A Tenant Admin or Manager must approve the partner assignment before it takes effect.</p>
            </div>
        </label>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="form-label">Split Type</label>
                <select x-model="data.split_type" class="form-input">
                    @foreach($options['partnerSplitTypes'] as $key => $type)
                    <option value="{{ $key }}">{{ $type['label'] }}</option>
                    @endforeach
                </select>
                <p class="text-xs text-gray-400 mt-1" x-text="descriptions[data.split_type] || ''"></p>
            </div>
            <div>
                <label class="form-label" x-text="data.split_type === 'percentage' ? 'Default Partner Share (%)' : 'Default Partner Share (₱)'"></label>
                <input type="number" min="0" :max="data.split_type === 'percentage' ? 100 : null" step="0.01"
                       x-model.number="data.default_split_value" class="form-input">
                <p x-show="errors.default_split_value" x-cloak class="text-xs text-red-600 mt-1" x-text="errors.default_split_value?.[0]"></p>
            </div>
        </div>

        <div>
            <label class="form-label">Lock split once a deal reaches</label>
            <select :value="data.lock_after_stage ?? ''"
                    @change="data.lock_after_stage = $event.target.value === '' ? null : $event.target.value"
                    class="form-input">
                <option value="">Never (always editable)</option>
                @foreach($pipelineStages as $stage)
                <option value="{{ $stage['stage_key'] ?? '' }}">{{ $stage['name'] ?? 'Unnamed stage' }}</option>
                @endforeach
            </select>
            <p class="text-xs text-gray-400 mt-1">Once a deal reaches this stage, its partner split can no longer be changed.</p>
            <p x-show="errors.lock_after_stage" x-cloak class="text-xs text-red-600 mt-1" x-text="errors.lock_after_stage?.[0]"></p>
        </div>

        <label class="flex items-start gap-3 p-3 rounded-xl border border-gray-200 cursor-pointer">
            <input type="checkbox" x-model="data.notify_partner" class="mt-0.5 accent-[#7B61FF]">
            <div>
                <p class="text-sm font-medium text-[#1E1B4B]">Notify the Partner when they're added to a deal</p>
                <p class="text-xs text-gray-400 mt-0.5">Sends an in-app notification (and email, if enabled) to the assigned Partner.</p>
            </div>
        </label>
    </div>
</div>
