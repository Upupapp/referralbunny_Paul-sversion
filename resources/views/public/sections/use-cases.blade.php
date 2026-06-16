@php
    $useCases = [
        ['name' => 'SaaS & Software', 'text' => 'Turn happy customers and partners into a steady pipeline of qualified leads.', 'accent' => 'bg-brand/10 text-brand'],
        ['name' => 'Real Estate', 'text' => 'Reward agents and clients for referring buyers, sellers, and renters.', 'accent' => 'bg-info/10 text-info'],
        ['name' => 'Insurance', 'text' => 'Track referrals from agents and policyholders through to new policies.', 'accent' => 'bg-success/10 text-success'],
        ['name' => 'Financial Services', 'text' => "Manage advisor and client referrals with a clear commission trail.", 'accent' => 'bg-warning/10 text-warning'],
        ['name' => 'Healthcare & Wellness', 'text' => 'Coordinate referral relationships between providers and partners.', 'accent' => 'bg-danger/10 text-danger'],
        ['name' => 'Home Services', 'text' => 'Reward contractors, vendors, and customers for new job referrals.', 'accent' => 'bg-grad-via/10 text-grad-via'],
        ['name' => 'Education & Training', 'text' => 'Track enrollments referred by alumni, partners, and affiliates.', 'accent' => 'bg-brand/10 text-brand'],
        ['name' => 'Marketing Agencies', 'text' => 'Manage sub-contractor and partner referrals across client accounts.', 'accent' => 'bg-info/10 text-info'],
        ['name' => 'Legal Services', 'text' => 'Track case referrals between firms and referral partners.', 'accent' => 'bg-success/10 text-success'],
        ['name' => 'Logistics & Delivery', 'text' => 'Reward partners and drivers for referring new accounts.', 'accent' => 'bg-warning/10 text-warning'],
        ['name' => 'Hospitality & Travel', 'text' => 'Turn guests and partners into a network of booking referrals.', 'accent' => 'bg-danger/10 text-danger'],
        ['name' => 'Nonprofits & Associations', 'text' => 'Coordinate member and chapter referral programs at scale.', 'accent' => 'bg-grad-via/10 text-grad-via'],
    ];
@endphp

<section id="use-cases" class="bg-white" data-rb-view-event="use_cases_viewed">
    <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6 sm:py-20 lg:px-8">
        <div class="reveal mx-auto max-w-2xl text-center">
            <h2 class="text-3xl font-bold tracking-tight text-heading sm:text-4xl">Built for referral programs across industries</h2>
            <p class="mt-4 text-lg text-body">Wherever referrals drive your business, ReferralBunny.ai can run the program behind them.</p>
        </div>

        <div class="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($useCases as $case)
                <div class="reveal rb-card-hover card p-5">
                    <div class="kpi-icon h-10 w-10 {{ $case['accent'] }}">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <rect x="3" y="7" width="18" height="13" rx="2" />
                            <path d="M8 7V5a2 2 0 012-2h4a2 2 0 012 2v2" />
                        </svg>
                    </div>
                    <h3 class="mt-3 font-semibold text-heading">{{ $case['name'] }}</h3>
                    <p class="mt-1 text-sm text-body">{{ $case['text'] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>
