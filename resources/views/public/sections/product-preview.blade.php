<section class="bg-white">
    <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6 sm:py-20 lg:px-8">
        <div class="reveal mx-auto max-w-2xl text-center">
            <h2 class="text-3xl font-bold tracking-tight text-heading sm:text-4xl">See your program at a glance</h2>
            <p class="mt-4 text-lg text-body">One dashboard for referrals, partners, commissions, and reporting — built on the same design as the rest of ReferralBunny.ai.</p>
        </div>

        <div class="reveal rb-tilt mt-12 overflow-hidden rounded-2xl border border-gray-100 bg-card shadow-xl">
            <div class="flex items-center gap-2 border-b border-gray-100 bg-base px-4 py-3" aria-hidden="true">
                <span class="h-3 w-3 rounded-full bg-danger/60"></span>
                <span class="h-3 w-3 rounded-full bg-warning/60"></span>
                <span class="h-3 w-3 rounded-full bg-success/60"></span>
                <span class="ml-3 text-xs text-muted">app.referralbunny.ai/tenant/dashboard</span>
            </div>

            <div class="grid lg:grid-cols-[200px_1fr]">
                <div class="hidden bg-sidebar p-4 lg:block" aria-hidden="true">
                    <div class="flex items-center gap-2 text-white">
                        <x-rb-logo variant="icon" size="xs" :decorative="true" />
                        <span class="text-sm font-semibold">ReferralBunny</span>
                    </div>
                    <nav class="mt-6 space-y-1 text-sm text-white/70">
                        <span class="block rounded-lg bg-sidebar-active px-3 py-2 font-semibold text-white">Dashboard</span>
                        <span class="block rounded-lg px-3 py-2">Referrals</span>
                        <span class="block rounded-lg px-3 py-2">Partners</span>
                        <span class="block rounded-lg px-3 py-2">Commissions</span>
                        <span class="block rounded-lg px-3 py-2">Reports</span>
                    </nav>
                </div>

                <div class="p-6">
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div class="kpi-card">
                            <div>
                                <p class="text-xs font-semibold text-muted">Active Referrals</p>
                                <p class="mt-1 text-2xl font-bold text-heading">128</p>
                            </div>
                            <span class="kpi-icon bg-info/10 text-info">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M3 17l5-5 4 4 7-8" /><path d="M14 8h5v5" />
                                </svg>
                            </span>
                        </div>
                        <div class="kpi-card">
                            <div>
                                <p class="text-xs font-semibold text-muted">Open Deals</p>
                                <p class="mt-1 text-2xl font-bold text-heading">42</p>
                            </div>
                            <span class="kpi-icon bg-brand/10 text-brand">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <rect x="3" y="4" width="5" height="16" rx="1.5" />
                                    <rect x="9.5" y="4" width="5" height="16" rx="1.5" />
                                    <rect x="16" y="4" width="5" height="16" rx="1.5" />
                                </svg>
                            </span>
                        </div>
                        <div class="kpi-card">
                            <div>
                                <p class="text-xs font-semibold text-muted">Commission Paid</p>
                                <p class="mt-1 text-2xl font-bold text-heading">$18.4k</p>
                            </div>
                            <span class="kpi-icon bg-success/10 text-success">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                    <circle cx="12" cy="12" r="9" />
                                    <text x="12" y="16.5" text-anchor="middle" font-size="11" fill="currentColor" stroke="none" font-weight="700">$</text>
                                </svg>
                            </span>
                        </div>
                    </div>

                    <div class="reveal mt-6 card p-4">
                        <p class="text-sm font-semibold text-heading">Referral Activity</p>
                        <div class="mt-4 flex h-32 items-end gap-2" aria-hidden="true">
                            <div class="w-full rounded-t bg-brand/15" style="height: 32%"></div>
                            <div class="w-full rounded-t bg-brand/15" style="height: 50%"></div>
                            <div class="w-full rounded-t bg-brand/15" style="height: 38%"></div>
                            <div class="w-full rounded-t bg-brand/15" style="height: 64%"></div>
                            <div class="w-full rounded-t bg-brand/15" style="height: 75%"></div>
                            <div class="w-full rounded-t bg-brand/15" style="height: 88%"></div>
                            <div class="w-full rounded-t bg-gradient-to-t from-grad-from via-grad-via to-grad-to" style="height: 100%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <p class="reveal mt-4 text-center text-xs text-muted">Illustrative preview — your dashboard reflects your program's real data.</p>
    </div>
</section>
