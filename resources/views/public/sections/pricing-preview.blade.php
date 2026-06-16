<section id="pricing" class="bg-white">
    <div class="mx-auto max-w-3xl px-4 py-16 text-center sm:px-6 sm:py-20 lg:px-8">
        <div class="reveal">
            <h2 class="text-3xl font-bold tracking-tight text-heading sm:text-4xl">Pricing built around your program</h2>
            <p class="mt-4 text-lg text-body">ReferralBunny.ai is one platform — the Setup Wizard, referrer and partner portals, deal pipeline, and commission automation. Pricing details are confirmed as part of setup, based on your program's needs.</p>
        </div>

        <div class="reveal mt-8 grid gap-4 sm:grid-cols-3">
            <div class="rb-card-hover card p-5">
                <p class="text-sm font-semibold text-heading">Guided setup</p>
                <p class="mt-1 text-sm text-body">The wizard walks you through every decision.</p>
            </div>
            <div class="rb-card-hover card p-5">
                <p class="text-sm font-semibold text-heading">Tailored to your program</p>
                <p class="mt-1 text-sm text-body">Pricing reflects your program's size and needs.</p>
            </div>
            <div class="rb-card-hover card p-5">
                <p class="text-sm font-semibold text-heading">Confirmed before launch</p>
                <p class="mt-1 text-sm text-body">You'll see full details before your program goes live.</p>
            </div>
        </div>

        <div class="reveal mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
            <x-public.cta-button
                route="signup.build"
                variant="primary"
                size="lg"
                loading-text="Opening your setup…"
                event="pricing_build_clicked"
            >
                Build a Referral Program
            </x-public.cta-button>
            <x-public.cta-button
                route="join"
                variant="outline-white"
                size="lg"
                loading-text="Finding your join path…"
                event="pricing_join_clicked"
            >
                Join a Referral Program
            </x-public.cta-button>
        </div>
    </div>
</section>
