<div class="space-y-4">

    @if($protected)
    <div class="flex gap-3 p-4 rounded-xl bg-orange-50 border border-orange-100">
        <x-r-bunny variant="warning" size="sm" :decorative="true" class="shrink-0 mt-0.5" />
        <div class="flex-1 min-w-0">
            <p class="text-sm font-semibold text-[#1E1B4B] mb-0.5">Protected Workspace</p>
            <p class="text-xs text-gray-500 leading-relaxed">
                Commission rates and reassignment rules for this workspace are managed separately by your administrator
                and can't be changed from this wizard.
            </p>
        </div>
    </div>

    <div class="card">
        <h3 class="font-semibold text-[#1E1B4B] text-sm mb-2">How commission is calculated</h3>
        <p class="text-xs text-gray-500 leading-relaxed mb-3">This formula applies platform-wide and can't be changed:</p>
        <div class="space-y-2 text-xs">
            <div class="flex items-center justify-between p-2 rounded-lg bg-gray-50">
                <span class="text-gray-500">Added Amount</span>
                <span class="font-mono text-[#1E1B4B]">Deal Value − Base Cost</span>
            </div>
            <div class="flex items-center justify-between p-2 rounded-lg bg-gray-50">
                <span class="text-gray-500">Company Share</span>
                <span class="font-mono text-[#1E1B4B]">30% of Added Amount</span>
            </div>
            <div class="flex items-center justify-between p-2 rounded-lg bg-purple-50">
                <span class="text-gray-500">Commission Pool</span>
                <span class="font-mono text-[#1E1B4B]">70% of Added Amount</span>
            </div>
        </div>
        <p class="text-xs text-gray-400 mt-3 leading-relaxed">The Commission Pool is the amount available to be shared with Referrers and Partners.</p>
    </div>
    @else

    <p class="text-sm text-gray-500 mb-2">
        Choose how commissions are calculated and how the Commission Pool is split. The pool itself is always 70% of the
        Added Amount (Deal Value − Base Cost) — that part of the formula is fixed platform-wide.
    </p>

    <div x-data="{
        descriptions: {
            commission: @js(collect($options['commissionTypes'])->map(fn($t) => $t['description'])->all()),
            reassignment: @js(collect($options['reassignmentModes'])->map(fn($t) => $t['description'])->all()),
        },
        sampleDealValue: 100000,
        sampleBaseCost: 70000,
        fmt(n) { return '₱' + Math.round(n).toLocaleString('en-US'); },
        get addedAmount() { return Math.max(0, this.sampleDealValue - this.sampleBaseCost); },
        get companyShare() { return this.addedAmount * 0.30; },
        get commissionPool() { return this.addedAmount * 0.70; },
        get referrerAmount() { return this.commissionPool * ((data.referrer_share_pct || 0) / 100); },
        get companyPoolAmount() { return this.commissionPool * ((data.company_share_pct || 0) / 100); },
    }" class="space-y-4">

        <div>
            <label class="form-label">Commission Type</label>
            <select x-model="data.commission_type" class="form-input">
                @foreach($options['commissionTypes'] as $key => $type)
                <option value="{{ $key }}">{{ $type['label'] }}</option>
                @endforeach
            </select>
            <p class="text-xs text-gray-400 mt-1" x-text="descriptions.commission[data.commission_type] || ''"></p>
            <p x-show="errors.commission_type" x-cloak class="text-xs text-red-600 mt-1" x-text="errors.commission_type?.[0]"></p>
        </div>

        <div>
            <label class="form-label">Commission Pool Split</label>
            <p class="text-xs text-gray-400 mb-2">How the 70% Commission Pool is divided between your company and Referrers. Must add up to 100%.</p>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="text-xs text-gray-500 mb-1 block">Company keeps (%)</label>
                    <input type="number" min="0" max="100" step="1" x-model.number="data.company_share_pct"
                           @input="data.referrer_share_pct = Math.max(0, Math.min(100, 100 - (data.company_share_pct || 0)))"
                           class="form-input">
                </div>
                <div>
                    <label class="text-xs text-gray-500 mb-1 block">Referrer gets (%)</label>
                    <input type="number" min="0" max="100" step="1" x-model.number="data.referrer_share_pct"
                           @input="data.company_share_pct = Math.max(0, Math.min(100, 100 - (data.referrer_share_pct || 0)))"
                           class="form-input">
                </div>
            </div>
            <p x-show="errors.company_share_pct" x-cloak class="text-xs text-red-600 mt-1" x-text="errors.company_share_pct?.[0]"></p>
            <p x-show="errors.referrer_share_pct" x-cloak class="text-xs text-red-600 mt-1" x-text="errors.referrer_share_pct?.[0]"></p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="form-label">Referral Expiry (days)</label>
                <input type="number" min="1" max="365" x-model.number="data.default_expiry_days" class="form-input">
                <p class="text-xs text-gray-400 mt-1">How long a referral stays valid before it expires if no deal is created.</p>
                <p x-show="errors.default_expiry_days" x-cloak class="text-xs text-red-600 mt-1" x-text="errors.default_expiry_days?.[0]"></p>
            </div>
            <div>
                <label class="form-label">Reassignment Mode</label>
                <select x-model="data.reassignment_mode" class="form-input">
                    @foreach($options['reassignmentModes'] as $key => $mode)
                    <option value="{{ $key }}">{{ $mode['label'] }}</option>
                    @endforeach
                </select>
                <p class="text-xs text-gray-400 mt-1" x-text="descriptions.reassignment[data.reassignment_mode] || ''"></p>
                <p x-show="errors.reassignment_mode" x-cloak class="text-xs text-red-600 mt-1" x-text="errors.reassignment_mode?.[0]"></p>
            </div>
        </div>

        {{-- Sample calculator --}}
        <div class="card bg-gray-50/50">
            <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Try it — Sample Calculation</h4>
            <div class="grid grid-cols-2 gap-3 mb-3">
                <div>
                    <label class="text-xs text-gray-500 mb-1 block">Sample Deal Value</label>
                    <input type="number" min="0" step="100" x-model.number="sampleDealValue" class="form-input">
                </div>
                <div>
                    <label class="text-xs text-gray-500 mb-1 block">Sample Base Cost</label>
                    <input type="number" min="0" step="100" x-model.number="sampleBaseCost" class="form-input">
                </div>
            </div>
            <div class="space-y-1.5 text-xs">
                <div class="flex items-center justify-between">
                    <span class="text-gray-500">Added Amount</span>
                    <span class="font-mono text-[#1E1B4B]" x-text="fmt(addedAmount)"></span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-gray-500">Company Share (30% — locked)</span>
                    <span class="font-mono text-[#1E1B4B]" x-text="fmt(companyShare)"></span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-gray-500">Commission Pool (70% — locked)</span>
                    <span class="font-mono text-[#1E1B4B]" x-text="fmt(commissionPool)"></span>
                </div>
                <div class="h-px bg-gray-200 my-1"></div>
                <div class="flex items-center justify-between">
                    <span class="text-gray-500">→ Company's share of the pool</span>
                    <span class="font-mono text-[#1E1B4B]" x-text="fmt(companyPoolAmount)"></span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-gray-500">→ Referrer's share of the pool</span>
                    <span class="font-mono text-[#7B61FF] font-semibold" x-text="fmt(referrerAmount)"></span>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
