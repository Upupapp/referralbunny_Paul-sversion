@if($performance)
<style>
.rb-performance{color:#29204c}.rb-performance h2{font-size:20px;font-weight:700;margin-bottom:8px}.rb-performance .rb-note{font-size:13px;color:#82788f;line-height:1.7;margin-bottom:20px}.rb-performance .rb-metrics{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:14px;margin-bottom:22px}.rb-performance .rb-metric,.rb-performance .rb-terms{background:white;border:1px solid #e9e1f2;border-radius:16px;padding:20px}.rb-performance .rb-metric p{font-size:12px;color:#857991}.rb-performance .rb-metric strong{display:block;font-size:24px;color:#392050;margin:8px 0}.rb-performance .rb-metric small{font-size:11px;color:#91869d;line-height:1.6;display:block}.rb-performance .rb-terms{margin:14px 0;background:#faf6ff}.rb-performance .rb-terms p{font-size:13px;line-height:1.7}.rb-performance h3{font-size:14px;font-weight:700;margin:20px 0 12px}.rb-performance .rb-stage{display:flex;justify-content:space-between;padding:13px 18px;border-bottom:1px solid #eee7f5;background:white;font-size:13px}.rb-performance .rb-stage:first-of-type{border-radius:12px 12px 0 0}
</style>
<section class="rb-performance">
    <h2>{{ $performance['mode'] === 'automated' ? 'Purchases & referral rewards' : 'Referrals & pipeline' }}</h2>
    <p class="rb-note">All-time activity for {{ $program->name }} only. {{ $performance['mode'] === 'automated' ? 'Based on recorded website events. Deal stages do not confirm a payment or payout.' : 'Based on referrals assigned to this program. Archived and deleted referrals are excluded.' }}</p>
    @if($performance['mode'] === 'automated')
        @php $financial = $performance['financials']; $signals = $financial['signals']; @endphp
        <div class="rb-metrics">
            @foreach([
                ['Active referrers', count($signals['referrers']), 'Active members enrolled in this program.'],
                ['Paying customers', count($signals['customers']), 'Distinct customers with recorded payments; includes refunded purchases.'],
                ['Rewards on hold', $signals['on_hold'], 'Positive rewards still within their recorded hold period.'],
                ['Ready for review', $signals['ready'], 'Hold ended; reward still requires review. Not a payout count.'],
            ] as [$label, $value, $description])
            <div class="rb-metric"><p>{{ $label }}</p><strong>{{ $value }}</strong><small>{{ $description }}</small></div>
            @endforeach
        </div>
        @foreach($financial['totals'] as $total)
        <h3>{{ $total['currency'] }} · {{ $total['count'] }} recorded {{ $total['count'] === 1 ? 'payment' : 'payments' }}</h3>
        <div class="rb-metrics">
            @foreach([
                ['Net collected revenue', $total['revenue'], 'Payments less refunds, excluding tax.'],
                ['Recorded referral rewards', $total['reward'], 'Stored rewards less refund reversals; not confirmed payouts.'],
                ['Revenue after rewards', $total['retained'], 'Net revenue less referral rewards, before other costs.'],
            ] as [$label, $value, $description])
            <div class="rb-metric"><p>{{ $label }}</p><strong>{{ $total['currency'] }} {{ number_format($value, 2) }}</strong><small>{{ $description }}</small></div>
            @endforeach
        </div>
        @endforeach
        <h3>Current reward mechanics</h3>
        @forelse($financial['terms'] as $term)
        <div class="rb-terms">
            <p><strong>{{ $term['name'] }} · {{ $term['label'] }} reward</strong> · Version {{ $term['version'] }}</p>
            @if($term['supported'])
            <p>{{ $term['scope'] }} {{ $term['model'] === 'percentage' ? 'Based on eligible collected revenue excluding tax.' : 'Fixed reward per eligible payment.' }} {{ $term['hold'] }}-day hold.</p>
            @else
            <p>Custom mechanics. Refer to this offer’s published rules for eligibility and calculations.</p>
            @endif
        </div>
        @empty
        <p class="rb-note">No active published reward offer yet. Configure an offer to define this program’s reward mechanics.</p>
        @endforelse
        <p class="rb-note">Historical rewards keep the offer version used when recorded. Changing the current reward rate does not recalculate past rewards. Currencies are shown separately.</p>
    @else
        <div class="rb-metrics">
            @foreach([['Total referrals', $performance['total']], ['Active referrals', $performance['active']], ['Expiring referrals', $performance['expiring']]] as [$label, $value])
            <div class="rb-metric"><p>{{ $label }}</p><strong>{{ $value }}</strong></div>
            @endforeach
        </div>
        <h3>Referrals by recorded stage</h3>
        @forelse($performance['stages'] as $stage)
        <div class="rb-stage"><span>{{ $stage->stage ? ucfirst(str_replace('_', ' ', $stage->stage)) : 'Unassigned stage' }}</span><strong>{{ $stage->total }}</strong></div>
        @empty
        <p class="rb-note">No referrals have been assigned to this program yet.</p>
        @endforelse
        <h3>Current reward mechanics</h3>
        @forelse($performance['offers'] as $offer)
        <div class="rb-terms"><p><strong>{{ $offer['name'] }} · {{ $offer['label'] }}</strong> · Version {{ $offer['version'] }}</p><p>Qualifying event: {{ ucfirst(str_replace('_', ' ', $offer['event'] ?? 'Not configured')) }}.</p></div>
        @empty
        <p class="rb-note">Publish a reward offer to define this program’s mechanics.</p>
        @endforelse
        <p class="rb-note">Referral stages are shown as recorded. Reward totals are not estimated from deal values or a fixed commission split.</p>
    @endif
</section>
@endif
