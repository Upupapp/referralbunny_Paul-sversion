<style>
.rb-program-summary{color:#1E1B4B}.rb-program-summary .rb-panel{background:#fff;border:1px solid #eeecf5;border-radius:20px;box-shadow:0 2px 5px #1e1b4b06;overflow:hidden}.rb-program-summary .rb-program-header{padding:20px 22px;display:flex;justify-content:space-between;align-items:center;gap:16px;border-bottom:1px solid #f3f1f8}.rb-program-summary .rb-program-body{padding:20px 22px}.rb-program-summary .rb-eyebrow{font-size:10px;font-weight:700;letter-spacing:.09em;text-transform:uppercase;color:#8b82a7}.rb-program-summary .rb-stat-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}.rb-program-summary .rb-money-card{padding:18px;border:1px solid #eeeaf7;border-radius:16px;background:linear-gradient(145deg,#fff,#fcfbff)}.rb-program-summary .rb-stat-label{font-size:12px;font-weight:500;color:#77758e}.rb-program-summary .rb-value{font-size:26px;font-weight:750;letter-spacing:-.6px;margin:12px 0 6px;font-variant-numeric:tabular-nums}.rb-program-summary .rb-help{font-size:11px;color:#9491a8;line-height:1.6}.rb-program-summary .rb-icon{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:10px;flex-shrink:0}.rb-program-summary .rb-pill{display:inline-flex;align-items:center;gap:6px;border-radius:999px;padding:5px 10px;font-size:11px;font-weight:600;background:#f3eeff;color:#7B61FF}.rb-program-summary .rb-terms{padding:14px 16px;background:#faf8ff;border:1px solid #f0eafa;border-radius:14px;margin-bottom:18px}.rb-program-summary .rb-link{font-size:12px;font-weight:600;color:#7B61FF;text-decoration:none}.rb-program-summary .rb-link:hover{text-decoration:underline}.rb-program-summary .rb-footer{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-top:16px}.rb-program-summary .rb-pipeline{display:flex;align-items:center;justify-content:space-between;gap:20px;padding:16px 22px}.rb-program-summary .rb-pipeline-stats{display:flex;gap:30px}.rb-program-summary .rb-pipeline strong{font-size:22px;display:block;margin-top:3px}@media(max-width:700px){.rb-program-summary .rb-stat-grid{grid-template-columns:1fr}.rb-program-summary .rb-program-header,.rb-program-summary .rb-footer,.rb-program-summary .rb-pipeline{align-items:flex-start;flex-direction:column}.rb-program-summary .rb-program-body{padding:16px}.rb-program-summary .rb-pipeline-stats{width:100%;justify-content:space-between}}
</style>
<div class="rb-program-summary space-y-4">
    <div class="rb-panel rb-pipeline">
        <div><p class="rb-eyebrow">Your referral program</p><h2 class="font-semibold mt-1">Referral overview</h2></div>
        <div class="rb-pipeline-stats">
            <div><p class="rb-stat-label">Total referrals</p><strong x-text="stats.total_leads || stats.leads_total || leadsTotal || leads.length">0</strong></div>
            <div><p class="rb-stat-label">Paid-stage conversion</p><strong x-text="conversionRate() + '%'">0%</strong></div>
        </div>
    </div>
    @forelse($programFinancials as $financial)
    <section class="rb-panel">
        <div class="rb-program-header">
            <div style="display:flex;align-items:center;gap:12px">
                <span class="rb-icon" style="background:#EDE9FE;color:#7B61FF"><svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M12 3l2.8 5.7 6.2.9-4.5 4.4 1.1 6.2-5.6-3-5.6 3 1.1-6.2L3 9.6l6.2-.9L12 3z"/></svg></span>
                <div><p class="rb-eyebrow">Program performance</p><h2 class="font-semibold mt-1">{{ $financial['name'] }}</h2></div>
            </div>
            <span class="rb-pill">{{ ucfirst($financial['status']) }}</span>
        </div>
        <div class="rb-program-body">
        @foreach($financial['terms'] as $term)
            <div class="rb-terms">
                <div style="display:flex;align-items:center;flex-wrap:wrap;gap:8px"><strong style="font-size:14px">{{ $term['label'] }} referral reward</strong><span class="rb-pill">{{ $term['currency'] }}</span><span class="rb-pill">{{ $term['hold'] }}-day hold</span></div>
                <p class="rb-help" style="margin-top:8px;color:#78718e">{{ $term['scope'] }} {{ $term['supported'] ? 'Based on eligible payments excluding tax. Manual approval; refunds reverse rewards.' : 'See the published offer for its eligibility and reward rules.' }}</p>
            </div>
        @endforeach
        @foreach($financial['totals'] as $total)
        <div class="rb-stat-grid">
            @foreach([
                ['Net collected revenue',$total['revenue'],'Payments minus refunds, excluding tax','#7B61FF','#EDE9FE','M12 6v12m-3-9h4a2 2 0 010 4h-2a2 2 0 000 4h4'],
                ['Referral rewards',$total['reward'],'Recorded rewards after refund reversals','#0D9488','#CCFBF1','M20 12v8H4v-8m-1-5h18v5H3V7zm9 0v13m0-13H8a2 2 0 112-2l2 2zm0 0h4a2 2 0 10-2-2l-2 2z'],
                ['Revenue after rewards',$total['retained'],'Net revenue less rewards, before other costs','#D97706','#FEF3C7','M4 20h16M6 16v-4m6 4V8m6 8V4'],
            ] as [$label,$value,$description,$color,$bg,$icon])
            <div class="rb-money-card"><div style="display:flex;justify-content:space-between;align-items:center;gap:10px"><p class="rb-stat-label">{{ $label }}</p><span class="rb-icon" style="background:{{ $bg }};color:{{ $color }}"><svg width="17" height="17" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}"/></svg></span></div><p class="rb-value">{{ $total['currency'] === 'PHP' ? '₱' : $total['currency'].' ' }}{{ number_format($value,2) }}</p><p class="rb-help">{{ $description }}</p></div>
            @endforeach
        </div>
        <div class="rb-footer"><p class="rb-help">{{ $total['count'] }} recorded payment(s) · Rewards shown are not confirmed payouts.</p><a class="rb-link" href="{{ route('tenant.programs.workspace',[$tenant->id,$financial['id']]) }}">Program settings →</a></div>
        @endforeach
        </div>
    </section>
    @empty
    <p class="rb-panel rb-program-body rb-help">Publish a referral program to see its reward terms and financial totals.</p>
    @endforelse
</div>
