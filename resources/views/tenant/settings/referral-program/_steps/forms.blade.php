<div class="space-y-4"
     x-data="{
        get previewUrl() {
            const slug = (data.referral_link_slug || '').trim();
            return 'referralbunny.ai/r/' + (slug || 'your-program');
        },
     }">

    <p class="text-sm text-gray-500 mb-2">
        Give people outside your team a way to submit referrals without an account, using a shareable link, QR code,
        or public form.
    </p>

    <label class="flex items-start gap-3 p-3 rounded-xl border cursor-pointer transition-colors"
           :class="data.enable_public_referral_form ? 'border-[#7B61FF] bg-purple-50' : 'border-gray-200 hover:border-purple-200'">
        <input type="checkbox" x-model="data.enable_public_referral_form" class="mt-0.5 accent-[#7B61FF]">
        <div>
            <p class="text-sm font-medium text-[#1E1B4B]">Enable a public referral link</p>
            <p class="text-xs text-gray-400 mt-0.5">Anyone with the link can submit a referral, even without a ReferralBunny account. You'll be asked to confirm this again before publishing.</p>
        </div>
    </label>

    <div class="space-y-4 pl-1" x-show="data.enable_public_referral_form" x-cloak>
        <div>
            <label class="form-label">Link slug</label>
            <div class="flex items-center gap-2">
                <span class="text-xs text-gray-400 shrink-0">referralbunny.ai/r/</span>
                <input type="text" x-model="data.referral_link_slug" maxlength="50" placeholder="your-program"
                       class="form-input">
            </div>
            <p class="text-xs text-gray-400 mt-1">Lowercase letters, numbers, and hyphens only.</p>
            <p x-show="errors.referral_link_slug" x-cloak class="text-xs text-red-600 mt-1" x-text="errors.referral_link_slug?.[0]"></p>
        </div>

        <label class="flex items-start gap-3 p-3 rounded-xl border border-gray-200 cursor-pointer">
            <input type="checkbox" x-model="data.show_referrer_name_on_public_form" class="mt-0.5 accent-[#7B61FF]">
            <div>
                <p class="text-sm font-medium text-[#1E1B4B]">Show the Referrer's name on the public form</p>
                <p class="text-xs text-gray-400 mt-0.5">Helps the person submitting know who referred them.</p>
            </div>
        </label>

        <label class="flex items-start gap-3 p-3 rounded-xl border border-gray-200 cursor-pointer">
            <input type="checkbox" x-model="data.generate_qr_code" class="mt-0.5 accent-[#7B61FF]">
            <div>
                <p class="text-sm font-medium text-[#1E1B4B]">Generate a QR code for this link</p>
                <p class="text-xs text-gray-400 mt-0.5">A scannable QR code for the link below will be generated once this program is published.</p>
            </div>
        </label>

        <div>
            <label class="form-label">Redirect after submission (optional)</label>
            <input type="url" x-model="data.redirect_url_after_submit" maxlength="255" placeholder="https://example.com/thank-you" class="form-input">
            <p class="text-xs text-gray-400 mt-1">Leave blank to show the default "Thanks!" confirmation screen.</p>
            <p x-show="errors.redirect_url_after_submit" x-cloak class="text-xs text-red-600 mt-1" x-text="errors.redirect_url_after_submit?.[0]"></p>
        </div>

        {{-- Preview --}}
        <div class="card bg-gray-50/50">
            <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Preview</h4>
            <div class="flex items-center gap-3">
                <div class="w-16 h-16 rounded-lg border-2 border-dashed border-gray-300 flex items-center justify-center shrink-0" x-show="data.generate_qr_code">
                    <svg class="w-8 h-8 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.5A.75.75 0 014.5 3.75h4.5a.75.75 0 01.75.75v4.5a.75.75 0 01-.75.75h-4.5a.75.75 0 01-.75-.75v-4.5zm0 10.5a.75.75 0 01.75-.75h4.5a.75.75 0 01.75.75v4.5a.75.75 0 01-.75.75h-4.5a.75.75 0 01-.75-.75v-4.5zm10.5-10.5a.75.75 0 01.75-.75h4.5a.75.75 0 01.75.75v4.5a.75.75 0 01-.75.75h-4.5a.75.75 0 01-.75-.75v-4.5z" />
                    </svg>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-xs text-gray-400 mb-0.5">Public referral link</p>
                    <p class="text-sm font-mono text-[#1E1B4B] break-all" x-text="previewUrl"></p>
                </div>
            </div>
            <p class="text-xs text-gray-400 mt-3" x-show="data.generate_qr_code" x-cloak>The QR code will appear here once your program is published.</p>
        </div>
    </div>
</div>
