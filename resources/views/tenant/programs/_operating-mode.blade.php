@php
    $legacyProgramLabels = \App\Support\ProtectedTenants::isProtected($tenant->id);
    $modeValue = old('operating_mode', isset($modeProgram) ? $modeProgram->effectiveOperatingMode() : 'manual');
    $modeLocked = isset($modeProgram) && !$modeProgram->canChangeOperatingMode();
@endphp
<fieldset class="rounded-xl border border-purple-100 bg-purple-50 p-4 space-y-3">
    <legend class="text-sm font-semibold text-purple-800 px-1">{{ $legacyProgramLabels ? 'How referrals are recorded' : 'Referral program setup' }}</legend>
    <label class="flex items-start gap-3 text-sm">
        <input type="radio" name="operating_mode" value="manual" class="mt-1" @checked($modeValue === 'manual') @disabled($modeLocked) required>
        <span><strong class="block">{{ $legacyProgramLabels ? 'Manual' : 'Manual Referral Program' }}</strong><span class="text-gray-600">Your team adds deals and updates their progress in the portal.</span></span>
    </label>
    <label class="flex items-start gap-3 text-sm">
        <input type="radio" name="operating_mode" value="automated" class="mt-1" @checked($modeValue === 'automated') @disabled($modeLocked) required>
        <span><strong class="block">{{ $legacyProgramLabels ? 'Automated' : 'Subscription Referral Program' }}</strong><span class="text-gray-600">{{ $legacyProgramLabels ? 'A connected website sends referral purchases and refunds. New Deal is hidden. Selecting this mode does not connect your website.' : 'For subscription platforms. Your connected website records subscription payments and refunds, with rewards based on your program rules. Connect your platform after setup.' }}</span></span>
    </label>
    @if($modeLocked)
        <p class="text-xs text-gray-500">This mode is fixed after launch or integration setup. Create a new program to use a different mode.</p>
    @else
        <p class="text-xs text-gray-500">You can change this while the program is a draft, before connecting your website.</p>
    @endif
    @error('operating_mode')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
</fieldset>
