@extends('layouts.public')

@section('title', 'Privacy Policy — ReferralBunny.ai')
@section('meta_description', 'Privacy Policy for ReferralBunny.ai, the platform for building and managing referral programs.')
@section('og_title', 'Privacy Policy — ReferralBunny.ai')
@section('og_description', 'Privacy Policy for ReferralBunny.ai, the platform for building and managing referral programs.')
@section('canonical', 'https://referralbunny.ai/privacy')

@section('content')
    @include('public.sections.header')

    <main id="main-content" class="bg-white">
        <div class="mx-auto max-w-3xl px-4 py-16 sm:px-6 lg:px-8">
            <h1 class="text-3xl font-bold tracking-tight text-heading sm:text-4xl">Privacy Policy</h1>
            <p class="mt-2 text-sm text-muted">Effective date: June 14, 2026</p>

            <div class="mt-8 space-y-8 text-body">
                <section>
                    <h2 class="text-lg font-semibold text-heading">1. Overview</h2>
                    <p class="mt-2 leading-relaxed">This Privacy Policy explains what information ReferralBunny.ai collects, how it's used, and the choices you have.</p>
                </section>

                <section>
                    <h2 class="text-lg font-semibold text-heading">2. Information We Collect</h2>
                    <ul class="mt-2 list-disc space-y-1 pl-6 leading-relaxed">
                        <li>Account information you provide, such as your name, email address, and organization details.</li>
                        <li>Referral, deal, and commission data entered into your Program by you, your referrers, or your partners.</li>
                        <li>Usage data — such as pages visited and actions taken — collected via analytics to help us improve the Service.</li>
                    </ul>
                </section>

                <section>
                    <h2 class="text-lg font-semibold text-heading">3. How We Use Information</h2>
                    <p class="mt-2 leading-relaxed">We use this information to operate and improve the Service, to provide the dashboards, portals, and notifications described on our site, to communicate with you about your account, and to maintain the security of the Service.</p>
                </section>

                <section>
                    <h2 class="text-lg font-semibold text-heading">4. Data Sharing Within Your Program</h2>
                    <p class="mt-2 leading-relaxed">Information you, your referrers, and your partners enter is visible to the people your Program's roles and permissions grant access to — for example, a referrer sees their own referrals, while a Program Owner sees their Program's data. We do not sell your data to third parties.</p>
                </section>

                <section>
                    <h2 class="text-lg font-semibold text-heading">5. Data Security</h2>
                    <p class="mt-2 leading-relaxed">Each Program's data is scoped to that Program. Access is controlled through role-based permissions for company admins, referrers, and partners, and key actions are logged for accountability.</p>
                </section>

                <section>
                    <h2 class="text-lg font-semibold text-heading">6. Data Retention</h2>
                    <p class="mt-2 leading-relaxed">We retain Program data for as long as your account is active, or as needed to comply with our legal obligations.</p>
                </section>

                <section>
                    <h2 class="text-lg font-semibold text-heading">7. Your Choices</h2>
                    <p class="mt-2 leading-relaxed">You can access and update your account information at any time from your dashboard. To request deletion of your account or data, contact us using the details below.</p>
                </section>

                <section>
                    <h2 class="text-lg font-semibold text-heading">8. Cookies &amp; Analytics</h2>
                    <p class="mt-2 leading-relaxed">We use analytics tools, such as Google Analytics, to understand how the Service is used. These tools may use cookies; you can control cookie preferences through your browser settings.</p>
                </section>

                <section>
                    <h2 class="text-lg font-semibold text-heading">9. Children's Privacy</h2>
                    <p class="mt-2 leading-relaxed">The Service is intended for business use and is not directed at children.</p>
                </section>

                <section>
                    <h2 class="text-lg font-semibold text-heading">10. Changes to This Policy</h2>
                    <p class="mt-2 leading-relaxed">We may update this Privacy Policy from time to time. Material changes will be reflected by updating the effective date above.</p>
                </section>

                <section>
                    <h2 class="text-lg font-semibold text-heading">11. Contact</h2>
                    <p class="mt-2 leading-relaxed">Questions about this Privacy Policy can be sent to <a href="mailto:privacy@referralbunny.ai" class="text-brand hover:underline">privacy@referralbunny.ai</a>.</p>
                </section>
            </div>
        </div>
    </main>

    @include('public.sections.footer')
@endsection
