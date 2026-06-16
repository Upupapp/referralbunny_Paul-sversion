<section class="overflow-hidden bg-white">
    <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6 sm:py-20 lg:px-8">
        <div class="grid items-center gap-12 lg:grid-cols-2">
            <div class="reveal">
                <h2 class="text-3xl font-bold tracking-tight text-heading sm:text-4xl">From zero to live program in minutes</h2>
                <p class="mt-4 text-lg text-body">The Setup Wizard walks you through every decision your program needs — no spreadsheets, no engineering tickets.</p>

                <ol class="mt-6 space-y-4">
                    <li class="flex gap-3">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-brand text-sm font-bold text-white">1</span>
                        <span class="text-sm text-body"><strong class="text-heading">Program basics</strong> — name, branding, and domain settings.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-brand text-sm font-bold text-white">2</span>
                        <span class="text-sm text-body"><strong class="text-heading">Pipeline stages</strong> — define how a referral moves from Lead to Won.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-brand text-sm font-bold text-white">3</span>
                        <span class="text-sm text-body"><strong class="text-heading">Commission rules</strong> — set rates, splits, and payout rules.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-brand text-sm font-bold text-white">4</span>
                        <span class="text-sm text-body"><strong class="text-heading">Referrer &amp; partner settings</strong> — invites and portal access.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-brand text-sm font-bold text-white">5</span>
                        <span class="text-sm text-body"><strong class="text-heading">Review &amp; launch</strong> — preview everything before going live.</span>
                    </li>
                </ol>

                <div class="mt-8">
                    <x-public.cta-button
                        route="signup.build"
                        variant="primary"
                        size="md"
                        loading-text="Opening your setup…"
                        event="setup_wizard_cta_clicked"
                    >
                        Build a Referral Program
                    </x-public.cta-button>
                </div>
            </div>

            <div class="reveal rb-tilt mx-auto w-full max-w-md" aria-hidden="true">
                <div class="overflow-hidden rounded-2xl border border-gray-100 bg-card shadow-xl">
                    <div class="flex items-center justify-between border-b border-gray-100 bg-base px-4 py-3">
                        <span class="text-xs font-semibold text-muted">Setup Wizard</span>
                        <span class="text-xs text-muted">Step 3 of 5</span>
                    </div>
                    <div class="p-5">
                        <div class="h-2 w-full overflow-hidden rounded-full bg-base">
                            <div class="rb-meter-fill h-full w-3/5 rounded-full bg-gradient-to-r from-grad-from via-grad-via to-grad-to"></div>
                        </div>
                        <p class="mt-4 text-sm font-semibold text-heading">Commission rules</p>
                        <p class="mt-1 text-xs text-body">Set a default commission rate for new referrals.</p>
                        <div class="mt-4 space-y-3">
                            <div class="card p-3">
                                <p class="text-xs font-semibold text-muted">Default rate</p>
                                <p class="mt-1 text-lg font-bold text-heading">10%</p>
                            </div>
                            <div class="card p-3">
                                <p class="text-xs font-semibold text-muted">Payout schedule</p>
                                <p class="mt-1 text-sm font-semibold text-heading">On deal Won</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
