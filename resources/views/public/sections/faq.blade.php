@php
    $faqs = [
        ['q' => 'What is ReferralBunny.ai?', 'a' => 'A platform for building and managing referral programs — a guided setup wizard, dedicated portals for referrers and partners, a deal pipeline, and commission automation.'],
        ['q' => 'Who is ReferralBunny.ai for?', 'a' => "Companies that want to run a structured referral program, plus the referrers and partners who take part in it."],
        ['q' => "What's the difference between a referrer and a partner?", 'a' => "A referrer sends you new business and tracks their referrals in their own portal. A partner collaborates on deals and may share in the commission for the deals they're involved in."],
        ['q' => 'How long does setup take?', 'a' => 'The Setup Wizard walks you through program basics, pipeline stages, commission rules, and portal access — most programs can be configured in one sitting.'],
        ['q' => 'Can I customize my pipeline stages?', 'a' => 'Yes. You define the stages a referral moves through, from new lead to won, during setup.'],
        ['q' => 'How are commissions calculated?', 'a' => 'Based on the rules you configure during setup — commissions calculate automatically as deals move through your pipeline.'],
        ['q' => 'Can commissions be split between multiple partners?', 'a' => "Yes. A single deal's commission can be divided across multiple partners according to your rules."],
        ['q' => 'Can I import my existing referrals and deals?', 'a' => 'Yes. You can bring existing leads and deals into your pipeline in bulk.'],
        ['q' => 'Will I be notified about activity in my program?', 'a' => 'Yes. Referrers, partners, and company admins receive notifications when deals and commissions change.'],
        ['q' => 'How do I get started?', 'a' => 'Use "Build a Referral Program" to set up a new program, or "Join a Referral Program" if you\'ve been invited to one.'],
    ];
@endphp

<section id="faq" class="bg-card" data-rb-view-event="faq_viewed">
    <div class="mx-auto max-w-3xl px-4 py-16 sm:px-6 sm:py-20 lg:px-8">
        <div class="reveal mx-auto max-w-2xl text-center">
            <h2 class="text-3xl font-bold tracking-tight text-heading sm:text-4xl">Frequently asked questions</h2>
            <p class="mt-4 text-lg text-body">Everything you need to know before you get started.</p>
        </div>

        <div class="mt-10 space-y-3">
            @foreach($faqs as $i => $faq)
                <details class="group reveal card" @if($i === 0) open @endif>
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 p-5 font-semibold text-heading [&::-webkit-details-marker]:hidden">
                        <span>{{ $faq['q'] }}</span>
                        <svg class="h-5 w-5 shrink-0 text-muted transition-transform duration-200 group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M6 9l6 6 6-6" />
                        </svg>
                    </summary>
                    <div class="px-5 pb-5 text-sm text-body">{{ $faq['a'] }}</div>
                </details>
            @endforeach
        </div>
    </div>
</section>
