@extends('layouts.public')

@section('title', 'Terms of Service — ReferralBunny.ai')
@section('meta_description', 'Terms of Service for ReferralBunny.ai, the platform for building and managing referral programs.')
@section('og_title', 'Terms of Service — ReferralBunny.ai')
@section('og_description', 'Terms of Service for ReferralBunny.ai, the platform for building and managing referral programs.')
@section('canonical', 'https://referralbunny.ai/terms')

@section('content')
    @include('public.sections.header')

    <main id="main-content" class="bg-white">
        <div class="mx-auto max-w-3xl px-4 py-16 sm:px-6 lg:px-8">
            <h1 class="text-3xl font-bold tracking-tight text-heading sm:text-4xl">Terms of Service</h1>
            <p class="mt-2 text-sm text-muted">Effective date: June 14, 2026</p>

            <div class="mt-8 space-y-8 text-body">
                <section>
                    <h2 class="text-lg font-semibold text-heading">1. Acceptance of Terms</h2>
                    <p class="mt-2 leading-relaxed">By accessing or using ReferralBunny.ai (the "Service"), you agree to these Terms of Service. If you are using the Service on behalf of an organization, you represent that you have the authority to bind that organization to these Terms.</p>
                </section>

                <section>
                    <h2 class="text-lg font-semibold text-heading">2. The Service</h2>
                    <p class="mt-2 leading-relaxed">ReferralBunny.ai provides a platform for companies ("Programs") to build and manage referral programs, including a guided setup wizard, dedicated portals for referrers and partners, a deal pipeline, and commission tracking and automation.</p>
                </section>

                <section>
                    <h2 class="text-lg font-semibold text-heading">3. Accounts</h2>
                    <p class="mt-2 leading-relaxed">You must provide accurate information when creating an account. You are responsible for safeguarding your login credentials and for all activity that occurs under your account.</p>
                </section>

                <section>
                    <h2 class="text-lg font-semibold text-heading">4. Programs, Referrers, and Partners</h2>
                    <p class="mt-2 leading-relaxed">A company that builds a Program ("Program Owner") is responsible for the commission rules, pipeline stages, and terms it sets for its own Program. Referrers and partners who join a Program do so under the terms set by that Program Owner. ReferralBunny.ai provides the platform that records and automates these arrangements, but is not a party to the agreement between a Program Owner and its referrers or partners.</p>
                </section>

                <section>
                    <h2 class="text-lg font-semibold text-heading">5. Acceptable Use</h2>
                    <p class="mt-2 leading-relaxed">You agree not to misuse the Service — including attempting to access another tenant's data, interfering with the Service's operation, or using the Service for unlawful purposes.</p>
                </section>

                <section>
                    <h2 class="text-lg font-semibold text-heading">6. Your Data</h2>
                    <p class="mt-2 leading-relaxed">You retain ownership of the referral, deal, and commission data you enter into your Program. You are responsible for ensuring you have the right to submit any data — including contact information for referrals — that you add to the Service.</p>
                </section>

                <section>
                    <h2 class="text-lg font-semibold text-heading">7. Fees</h2>
                    <p class="mt-2 leading-relaxed">Where applicable, fees for your Program are confirmed as part of setup. Continued use of the Service after setup constitutes agreement to the fees presented to you at that time.</p>
                </section>

                <section>
                    <h2 class="text-lg font-semibold text-heading">8. Termination</h2>
                    <p class="mt-2 leading-relaxed">You may stop using the Service at any time. We may suspend or terminate access for accounts that violate these Terms.</p>
                </section>

                <section>
                    <h2 class="text-lg font-semibold text-heading">9. Disclaimers</h2>
                    <p class="mt-2 leading-relaxed">The Service is provided "as is." We work to keep it reliable and secure, but we do not guarantee uninterrupted or error-free operation.</p>
                </section>

                <section>
                    <h2 class="text-lg font-semibold text-heading">10. Limitation of Liability</h2>
                    <p class="mt-2 leading-relaxed">To the maximum extent permitted by law, ReferralBunny.ai is not liable for indirect, incidental, or consequential damages arising from your use of the Service.</p>
                </section>

                <section>
                    <h2 class="text-lg font-semibold text-heading">11. Changes to These Terms</h2>
                    <p class="mt-2 leading-relaxed">We may update these Terms from time to time. Continued use of the Service after changes take effect constitutes acceptance of the revised Terms.</p>
                </section>

                <section>
                    <h2 class="text-lg font-semibold text-heading">12. Contact</h2>
                    <p class="mt-2 leading-relaxed">Questions about these Terms can be sent to <a href="mailto:legal@referralbunny.ai" class="text-brand hover:underline">legal@referralbunny.ai</a>.</p>
                </section>
            </div>
        </div>
    </main>

    @include('public.sections.footer')
@endsection
