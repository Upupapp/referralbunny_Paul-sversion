<div class="space-y-4">
    <div class="card">
        <h2 class="font-semibold">Referral pipeline</h2>
        <div class="flex flex-wrap gap-6 mt-3">
            <div><p class="text-xs text-gray-500">Total referrals</p><strong x-text="stats.total_leads || stats.leads_total || leadsTotal || leads.length">0</strong></div>
            <div><p class="text-xs text-gray-500">Conversion rate</p><strong x-text="conversionRate() + '%'">0%</strong><p class="text-xs text-gray-500">Referrals at the paid stage; rewards require verified payments.</p></div>
        </div>
    </div>
    @forelse($programFinancials as $financial)
    <section class="card space-y-4">
        <h2 class="font-semibold">{{ $financial['name'] }} <span class="text-xs text-gray-500">· {{ ucfirst($financial['status']) }}</span></h2>
        @foreach($financial['terms'] as $term)
            @include('tenant.programs._reward-terms')
        @endforeach
        @foreach($financial['totals'] as $total)
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:16px">
            @foreach([
                ['Net collected revenue',$total['revenue'],'Recorded payments minus refunds, excluding tax'],
                ['Recorded referral rewards',$total['reward'],'Stored rewards minus refund reversals; not a payout total'],
                ['Revenue after referral rewards',$total['retained'],'Net collected revenue minus referral rewards; before other costs'],
            ] as [$label,$value,$description])
            <div class="rounded-xl border p-4"><p class="text-xs text-gray-500">{{ $label }}</p><p class="text-xl font-bold mt-2">{{ $total['currency'] }} {{ number_format($value,2) }}</p><p class="text-xs text-gray-500 mt-2">{{ $description }}</p></div>
            @endforeach
        </div>
        <p class="text-xs text-gray-500">{{ $total['count'] }} recorded payment(s). Historical rewards retain the offer version used when the payment was received.</p>
        @endforeach
        <a class="text-sm text-purple-700 underline" href="{{ route('tenant.programs.workspace',[$tenant->id,$financial['id']]) }}">View program and reward settings</a>
    </section>
    @empty
    <p class="card text-sm text-gray-500">Publish a referral program to see its reward terms and financial totals.</p>
    @endforelse
</div>
