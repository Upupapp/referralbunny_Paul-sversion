<div class="card space-y-4" id="rb-finance-section">
    <h3 class="font-semibold">Program reward details</h3>
    @php
        $assignedProgram = ($ssrLead['data']['program_id'] ?? null);
        $matchingPrograms = collect($programFinancials)->filter(fn($p) => $assignedProgram ? $p['id'] === $assignedProgram : count($programFinancials) === 1);
    @endphp
    @forelse($matchingPrograms as $financial)
        <p class="font-semibold">{{ $financial['name'] }}</p>
        @foreach($financial['terms'] as $term)
            @include('tenant.programs._reward-terms')
            @if($term['supported'])
            <div class="rounded-xl border p-4 space-y-3" x-data="{ amount: @js(max(0,(float)($ssrLead['deal_value'] ?? 0))) }">
                <label class="text-sm block">Eligible payment amount excluding tax ({{ $term['currency'] }})
                    <input class="form-input mt-2" type="number" min="0" step="0.01" x-model.number="amount">
                </label>
                <p class="font-semibold">Estimated reward:
                    <span x-text="@js($term['currency']) + ' ' + (Math.max(0,Number(amount)||0) > 0 ? {{ $term['model'] === 'percentage' ? '(Math.round(Math.round(Math.max(0,Number(amount)||0)*100)*'.($term['rate']/100).')/100)' : $term['fixed'] }} : 0).toLocaleString('en',{minimumFractionDigits:2,maximumFractionDigits:2})"></span>
                </p>
                <p class="text-xs text-gray-500">Preview only, using the current offer. Starts with the referral’s deal value; replace it with one eligible payment amount. This does not save financial data or create a reward. Eligibility, collection and refunds must be verified. Recurring payments are evaluated individually within the program’s reward period.</p>
            </div>
            @endif
        @endforeach
        <p class="text-sm text-gray-500">Recorded rewards are tracked against verified customer and invoice IDs. This manual referral has no verified payment link, so its stage and deal value are not treated as earned rewards.</p>
        <a class="text-purple-700 underline text-sm" href="{{ route('tenant.quick-program.connection',[$tenant->id,$financial['id']]) }}">View recorded program rewards</a>
    @empty
        <p class="text-sm text-gray-500">No single published program could be matched to this referral. Review its program assignment before estimating a reward.</p>
    @endforelse
</div>
