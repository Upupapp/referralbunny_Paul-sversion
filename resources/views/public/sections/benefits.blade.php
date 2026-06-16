<section class="bg-base">
    <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6 sm:py-20 lg:px-8">
        <div class="reveal mx-auto max-w-2xl text-center">
            <h2 class="text-3xl font-bold tracking-tight text-heading sm:text-4xl">Built for everyone in the program</h2>
            <p class="mt-4 text-lg text-body">Companies, referrers, and partners each get a view built for what they need to do.</p>
        </div>

        <div x-data="{ tab: 'companies' }" class="reveal mt-10">
            <div role="tablist" aria-label="Benefits by audience" class="mx-auto flex w-fit gap-1 rounded-full bg-white p-1 shadow-sm ring-1 ring-gray-100">
                <button type="button" role="tab" :aria-selected="(tab==='companies').toString()" @click="tab='companies'" :class="tab==='companies' ? 'bg-brand text-white shadow-sm' : 'text-muted hover:text-heading'" class="rounded-full px-4 py-2 text-sm font-semibold transition">For Companies</button>
                <button type="button" role="tab" :aria-selected="(tab==='referrers').toString()" @click="tab='referrers'" :class="tab==='referrers' ? 'bg-brand text-white shadow-sm' : 'text-muted hover:text-heading'" class="rounded-full px-4 py-2 text-sm font-semibold transition">For Referrers</button>
                <button type="button" role="tab" :aria-selected="(tab==='partners').toString()" @click="tab='partners'" :class="tab==='partners' ? 'bg-brand text-white shadow-sm' : 'text-muted hover:text-heading'" class="rounded-full px-4 py-2 text-sm font-semibold transition">For Partners</button>
            </div>

            <div x-show="tab==='companies'" class="mt-8 grid gap-6 sm:grid-cols-3">
                <div class="rb-card-hover card p-6">
                    <div class="kpi-icon bg-brand/10 text-brand">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M13 2L4 14h6l-1 8 9-12h-6l1-8z" /></svg>
                    </div>
                    <h3 class="mt-4 font-semibold text-heading">Launch faster</h3>
                    <p class="mt-2 text-sm text-body">The Setup Wizard gets your program live without engineering work.</p>
                </div>
                <div class="rb-card-hover card p-6">
                    <div class="kpi-icon bg-info/10 text-info">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3" /><path d="M2.5 12S6 5 12 5s9.5 7 9.5 7-3.5 7-9.5 7-9.5-7-9.5-7z" /></svg>
                    </div>
                    <h3 class="mt-4 font-semibold text-heading">Full visibility</h3>
                    <p class="mt-2 text-sm text-body">See every referral, deal, and commission in one dashboard.</p>
                </div>
                <div class="rb-card-hover card p-6">
                    <div class="kpi-icon bg-success/10 text-success">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3l7 3v5c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V6l7-3z" /><path d="M9 12l2 2 4-4" /></svg>
                    </div>
                    <h3 class="mt-4 font-semibold text-heading">Built-in access control</h3>
                    <p class="mt-2 text-sm text-body">Role-based permissions keep each tenant's data isolated and secure.</p>
                </div>
            </div>

            <div x-show="tab==='referrers'" class="mt-8 grid gap-6 sm:grid-cols-3">
                <div class="rb-card-hover card p-6">
                    <div class="kpi-icon bg-brand/10 text-brand">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="3.5" /><path d="M4.5 20c0-3.6 3.36-6 7.5-6s7.5 2.4 7.5 6" /></svg>
                    </div>
                    <h3 class="mt-4 font-semibold text-heading">Your own portal</h3>
                    <p class="mt-2 text-sm text-body">Track every referral you've sent and see exactly what stage it's in.</p>
                </div>
                <div class="rb-card-hover card p-6">
                    <div class="kpi-icon bg-success/10 text-success">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="12" cy="12" r="9" /><text x="12" y="16.5" text-anchor="middle" font-size="11" fill="currentColor" stroke="none" font-weight="700">$</text></svg>
                    </div>
                    <h3 class="mt-4 font-semibold text-heading">Clear commissions</h3>
                    <p class="mt-2 text-sm text-body">See exactly what you've earned, what's pending, and what's been paid.</p>
                </div>
                <div class="rb-card-hover card p-6">
                    <div class="kpi-icon bg-info/10 text-info">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M13.5 6.5l4 4L8 20l-4.5.5L4 16l9.5-9.5z" /><path d="M12 8l4 4" /></svg>
                    </div>
                    <h3 class="mt-4 font-semibold text-heading">Simple sharing</h3>
                    <p class="mt-2 text-sm text-body">Share your referral link or form and let the platform track the rest.</p>
                </div>
            </div>

            <div x-show="tab==='partners'" class="mt-8 grid gap-6 sm:grid-cols-3">
                <div class="rb-card-hover card p-6">
                    <div class="kpi-icon bg-brand/10 text-brand">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="5" height="16" rx="1.5" /><rect x="9.5" y="4" width="5" height="16" rx="1.5" /><rect x="16" y="4" width="5" height="16" rx="1.5" /></svg>
                    </div>
                    <h3 class="mt-4 font-semibold text-heading">Stay in the loop</h3>
                    <p class="mt-2 text-sm text-body">See every deal you're involved in and how it's progressing.</p>
                </div>
                <div class="rb-card-hover card p-6">
                    <div class="kpi-icon bg-info/10 text-info">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path d="M12 3v9l6.36 6.36" /><path d="M12 12L5.64 18.36" /></svg>
                    </div>
                    <h3 class="mt-4 font-semibold text-heading">Transparent splits</h3>
                    <p class="mt-2 text-sm text-body">View your share of the commission on every deal you're part of.</p>
                </div>
                <div class="rb-card-hover card p-6">
                    <div class="kpi-icon bg-success/10 text-success">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 5h16v10H9l-4 4V5z" /></svg>
                    </div>
                    <h3 class="mt-4 font-semibold text-heading">Direct messaging</h3>
                    <p class="mt-2 text-sm text-body">Talk with the team about a deal without leaving the platform.</p>
                </div>
            </div>
        </div>
    </div>
</section>
