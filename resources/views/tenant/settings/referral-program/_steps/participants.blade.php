<div class="space-y-3">
    <p class="text-sm text-gray-500 mb-2">Choose who will take part in this referral program. You can change this anytime.</p>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        @foreach($options['roles'] as $key => $role)
        <label class="flex items-start gap-3 p-3 rounded-xl border cursor-pointer transition-colors"
               :class="data.roles.includes('{{ $key }}') ? 'border-[#7B61FF] bg-purple-50' : 'border-gray-200 hover:border-purple-200'">
            <input type="checkbox" value="{{ $key }}" x-model="data.roles" class="mt-0.5 accent-[#7B61FF]">
            <div>
                <p class="text-sm font-medium text-[#1E1B4B]">{{ $role['label'] }}</p>
                <p class="text-xs text-gray-400 mt-0.5">{{ $role['description'] }}</p>
            </div>
        </label>
        @endforeach
    </div>
    <p x-show="errors.roles" x-cloak class="text-xs text-red-600 mt-1" x-text="errors.roles?.[0]"></p>
</div>
