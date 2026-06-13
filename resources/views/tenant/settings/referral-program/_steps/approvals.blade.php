<div class="space-y-4">

    <p class="text-sm text-gray-500 mb-2">
        Choose which actions need a Tenant Admin's sign-off before they take effect. Partner-assignment approval is
        set separately on the Partner Split step (Step 8).
    </p>

    <label class="flex items-start gap-3 p-3 rounded-xl border cursor-pointer transition-colors"
           :class="data.new_referral_review ? 'border-[#7B61FF] bg-purple-50' : 'border-gray-200 hover:border-purple-200'">
        <input type="checkbox" x-model="data.new_referral_review" class="mt-0.5 accent-[#7B61FF]">
        <div>
            <p class="text-sm font-medium text-[#1E1B4B]">Review new referrals before they enter the pipeline</p>
            <p class="text-xs text-gray-400 mt-0.5">New deals created by Referrers wait for approval before moving into the first pipeline stage.</p>
        </div>
    </label>

    <label class="flex items-start gap-3 p-3 rounded-xl border cursor-pointer transition-colors"
           :class="data.deal_extension_approval ? 'border-[#7B61FF] bg-purple-50' : 'border-gray-200 hover:border-purple-200'">
        <input type="checkbox" x-model="data.deal_extension_approval" class="mt-0.5 accent-[#7B61FF]">
        <div>
            <p class="text-sm font-medium text-[#1E1B4B]">Require approval for deal expiry extensions</p>
            <p class="text-xs text-gray-400 mt-0.5">Requests to extend a deal's expiry date go to your team's extension request queue instead of applying immediately.</p>
        </div>
    </label>

    <label class="flex items-start gap-3 p-3 rounded-xl border cursor-pointer transition-colors"
           :class="data.import_approval ? 'border-[#7B61FF] bg-purple-50' : 'border-gray-200 hover:border-purple-200'">
        <input type="checkbox" x-model="data.import_approval" class="mt-0.5 accent-[#7B61FF]">
        <div>
            <p class="text-sm font-medium text-[#1E1B4B]">Require approval before bulk imports are processed</p>
            <p class="text-xs text-gray-400 mt-0.5">Uploaded import files wait for sign-off before any deals or referrers are created.</p>
        </div>
    </label>

    <div>
        <label class="form-label">Who can approve these</label>
        <select x-model="data.approver_role" class="form-input max-w-xs">
            @foreach($options['approverRoles'] as $key => $label)
            <option value="{{ $key }}">{{ $label }}</option>
            @endforeach
        </select>
        <p x-show="errors.approver_role" x-cloak class="text-xs text-red-600 mt-1" x-text="errors.approver_role?.[0]"></p>
    </div>
</div>
