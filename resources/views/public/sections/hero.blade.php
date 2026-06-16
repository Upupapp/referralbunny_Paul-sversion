<section class="relative overflow-hidden">
    <div class="pointer-events-none absolute inset-0 -z-10 overflow-hidden" aria-hidden="true">
        <div class="absolute -right-32 -top-32 h-96 w-96 rounded-full bg-gradient-to-br from-grad-from via-grad-via to-grad-to opacity-20 blur-3xl"></div>
        <div class="absolute -bottom-24 -left-24 h-80 w-80 rounded-full bg-brand/10 blur-3xl"></div>
    </div>

    <div class="mx-auto max-w-7xl px-4 pb-16 pt-12 sm:px-6 sm:pb-24 sm:pt-16 lg:px-8 lg:pt-20">
        <div class="grid items-center gap-12 lg:grid-cols-2">
            <div>
                <span class="inline-flex items-center gap-2 rounded-full bg-white/70 px-4 py-1.5 text-xs font-semibold text-heading shadow-sm ring-1 ring-gray-100">
                    <span class="h-2 w-2 rounded-full bg-success"></span>
                    Built for multi-tenant referral programs
                </span>

                <h1 class="mt-5 text-4xl font-bold tracking-tight text-heading sm:text-5xl lg:text-6xl">
                    Launch Your Referral Program in Minutes
                </h1>

                <p class="mt-5 max-w-xl text-lg text-body">
                    ReferralBunny.ai gives you everything you need to launch, manage, and grow a referral program — onboard referrers and partners, track every deal, and automate commissions, all from one dashboard.
                </p>

                <div class="mt-8 flex flex-wrap items-center gap-3">
                    <x-public.cta-button
                        route="signup.build"
                        fallback="/login"
                        variant="primary"
                        size="lg"
                        loadingText="Opening your setup…"
                        event="hero_build_clicked"
                    >
                        Build a Referral Program
                    </x-public.cta-button>

                    <x-public.cta-button
                        route="join"
                        fallback="/login"
                        variant="secondary"
                        size="lg"
                        loadingText="Finding your join path…"
                        event="hero_join_clicked"
                    >
                        Join a Referral Program
                    </x-public.cta-button>

                    <x-public.cta-button
                        href="#how-it-works"
                        variant="ghost"
                        size="lg"
                        event="hero_how_it_works_clicked"
                    >
                        See How It Works
                    </x-public.cta-button>
                </div>

                <p class="mt-4 text-sm text-muted">
                    No credit card required to get started. Already invited to a program? Use Join to find your way in.
                </p>

                <ul class="mt-8 flex flex-wrap gap-2 text-xs font-medium text-heading" aria-label="Platform highlights">
                    <li class="rounded-full bg-white/70 px-3 py-1.5 ring-1 ring-gray-100">Multi-tenant by design</li>
                    <li class="rounded-full bg-white/70 px-3 py-1.5 ring-1 ring-gray-100">Referrer &amp; Partner portals</li>
                    <li class="rounded-full bg-white/70 px-3 py-1.5 ring-1 ring-gray-100">Built-in commission tracking</li>
                    <li class="rounded-full bg-white/70 px-3 py-1.5 ring-1 ring-gray-100">Deal pipeline &amp; automations</li>
                    <li class="rounded-full bg-white/70 px-3 py-1.5 ring-1 ring-gray-100">R Bunny setup assistant</li>
                </ul>
            </div>

            <div class="relative mx-auto aspect-square w-full max-w-md lg:max-w-none" aria-hidden="true">
                <x-r-bunny
                    variant="hero"
                    size="xl"
                    :decorative="true"
                    data-rb-critical
                    class="rb-entrance rb-float mx-auto"
                />

                <div class="reveal rb-glass rb-card-hover absolute left-0 top-4 w-48 rounded-2xl p-4 sm:-left-2">
                    <p class="text-xs font-semibold text-muted">New Referral</p>
                    <p class="mt-1 text-sm font-semibold text-heading">Acme Corp → Beta LLC</p>
                    <span class="badge badge-green mt-2 inline-block">New</span>
                </div>

                <div class="reveal rb-glass rb-card-hover absolute right-0 top-1/3 w-44 rounded-2xl p-4">
                    <p class="text-xs font-semibold text-muted">Commission Earned</p>
                    <p class="mt-1 text-lg font-bold text-heading">$1,240</p>
                    <div class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-gray-100">
                        <div class="rb-meter-fill h-full w-4/5 rounded-full bg-success"></div>
                    </div>
                </div>

                <div class="reveal rb-glass rb-card-hover absolute bottom-2 left-1/4 w-52 rounded-2xl p-4">
                    <p class="text-xs font-semibold text-muted">Pipeline</p>
                    <div class="mt-2 flex items-center gap-2 text-[11px] font-semibold">
                        <span class="rounded-full bg-gray-100 px-2 py-1 text-heading">Lead</span>
                        <span class="rb-glow-line h-px w-4 bg-grad-via"></span>
                        <span class="rounded-full bg-gray-100 px-2 py-1 text-heading">Qualified</span>
                        <span class="rb-glow-line h-px w-4 bg-grad-via"></span>
                        <span class="rounded-full bg-brand px-2 py-1 text-white">Won</span>
                    </div>
                </div>

                <span class="rb-dot absolute left-1/3 top-1/2 h-2.5 w-2.5 rounded-full bg-brand" style="--rb-travel: 160px;"></span>
            </div>
        </div>
    </div>
</section>
