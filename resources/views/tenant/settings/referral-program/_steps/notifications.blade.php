<div class="space-y-4">

    <p class="text-sm text-gray-500 mb-2">
        Choose which events send a notification to the people involved. Each person's own channels (email, SMS, in-app)
        are managed in their account settings — these toggles control whether the event fires a notification at all.
    </p>

    @foreach($options['notificationEvents'] as $key => $event)
    <label class="flex items-start gap-3 p-3 rounded-xl border cursor-pointer transition-colors"
           :class="data.{{ $key }} ? 'border-[#7B61FF] bg-purple-50' : 'border-gray-200 hover:border-purple-200'">
        <input type="checkbox" x-model="data.{{ $key }}" class="mt-0.5 accent-[#7B61FF]">
        <div>
            <p class="text-sm font-medium text-[#1E1B4B]">{{ $event['label'] }}</p>
            <p class="text-xs text-gray-400 mt-0.5">{{ $event['description'] }}</p>
        </div>
    </label>
    @endforeach

    <label class="flex items-start gap-3 p-3 rounded-xl border cursor-pointer transition-colors"
           :class="data.email_notifications_enabled ? 'border-[#7B61FF] bg-purple-50' : 'border-gray-200 hover:border-purple-200'">
        <input type="checkbox" x-model="data.email_notifications_enabled" class="mt-0.5 accent-[#7B61FF]">
        <div>
            <p class="text-sm font-medium text-[#1E1B4B]">Send these as emails too</p>
            <p class="text-xs text-gray-400 mt-0.5">When off, the events above only appear as in-app notifications, regardless of a person's individual email preference.</p>
        </div>
    </label>

    <div>
        <label class="form-label">Admin summary frequency</label>
        <select x-model="data.digest_frequency" class="form-input max-w-xs">
            @foreach($options['digestFrequencies'] as $key => $label)
            <option value="{{ $key }}">{{ $label }}</option>
            @endforeach
        </select>
        <p class="text-xs text-gray-400 mt-1">How often Tenant Admins get a roll-up of activity across this program.</p>
        <p x-show="errors.digest_frequency" x-cloak class="text-xs text-red-600 mt-1" x-text="errors.digest_frequency?.[0]"></p>
    </div>
</div>
