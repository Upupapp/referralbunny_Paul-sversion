<section class="relative overflow-hidden bg-gradient-to-br from-grad-from via-grad-via to-grad-to">
    <div class="rb-carrot pointer-events-none absolute -left-12 -top-12 h-40 w-40 bg-white/10" aria-hidden="true"></div>
    <div class="rb-carrot pointer-events-none absolute -bottom-16 -right-10 h-56 w-56 bg-white/10" aria-hidden="true"></div>

    <div class="relative mx-auto max-w-4xl px-4 py-16 text-center sm:px-6 sm:py-24 lg:px-8">
        <div class="reveal">
            <x-r-bunny variant="celebration" size="lg" :decorative="true" class="rb-float mx-auto" />
            <h2 class="mt-6 text-3xl font-bold text-white sm:text-4xl">Ready to launch your referral program?</h2>
            <p class="mt-4 text-lg text-white/90">Set it up in minutes, or join a program you've been invited to.</p>

            <div class="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
                <x-public.cta-button
                    route="signup.build"
                    variant="white"
                    size="lg"
                    loading-text="Opening your setup…"
                    event="final_cta_build_clicked"
                >
                    Build a Referral Program
                </x-public.cta-button>
                <x-public.cta-button
                    route="join"
                    variant="outline-white"
                    size="lg"
                    loading-text="Finding your join path…"
                    event="final_cta_join_clicked"
                >
                    Join a Referral Program
                </x-public.cta-button>
            </div>
        </div>
    </div>
</section>
