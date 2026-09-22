@extends('layouts.reseller')
@section('title','Rewards')
@section('nav')
@include('reseller._nav')
@endsection
@section('content')
@php
 $f=$rewards['f']??['currency'=>$program->default_currency?:'PHP','range'=>'all','q'=>'','status'=>'all','event'=>'all','from'=>null,'to'=>null,'referral_reference'=>null];
 $money=fn($minor,$currency=null)=>($currency?:$f['currency']).' '.number_format($minor/100,2);
 $base=['tenantId'=>$tenant->id,'program_id'=>$program->id];$messages=route('reseller.messages',$base+array_filter(['referral_reference'=>$f['referral_reference']]));$mechanics=route('reseller.programs.show',[$tenant->id,$program->id,'tab'=>'mechanics']);
@endphp
<svg class="rw-icons" aria-hidden="true"><defs><symbol id="rw-coins" viewBox="0 0 24 24"><ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v6c0 4 16 4 16 0V5M4 11v6c0 4 16 4 16 0v-6M4 11c0 4 16 4 16 0"/></symbol><symbol id="rw-gift" viewBox="0 0 24 24"><path d="M3 9h18v4H3zM5 13v8h14v-8M12 9v12M12 9C2 9 5 0 9 4l3 5c10 0 7-9 3-5z"/></symbol><symbol id="rw-check" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="m7 12 3 3 7-7"/></symbol><symbol id="rw-reverse" viewBox="0 0 24 24"><path d="M5 8a8 8 0 1 1-1 9M5 3v6h6M9 12h6"/></symbol></defs></svg>
<section class="rw-page">
 <form id="rw-filters" action="{{ route('reseller.commission',$tenant->id) }}" method="GET"><input type="hidden" name="program_id" value="{{ $program->id }}">@if($f['referral_reference'])<input type="hidden" name="referral_reference" value="{{ $f['referral_reference'] }}">@endif</form>
 <header class="rw-heading"><div><h1>Rewards</h1><p>Track the rewards you’ve earned from your referrals.</p></div><div class="rw-report-controls">
 <label><span class="sr-only">Reporting range</span><select form="rw-filters" name="range" id="rw-range" onchange="document.getElementById('rw-custom').hidden=this.value!=='custom'">@foreach(['all'=>'All time','30'=>'Last 30 days','90'=>'Last 90 days','custom'=>'Custom dates'] as $key=>$label)<option value="{{ $key }}" @selected($f['range']===$key)>{{ $label }}</option>@endforeach</select></label>
 <label>Currency <select form="rw-filters" name="currency">@foreach($rewards['currencies']??collect([$f['currency']]) as $c)<option @selected($c===$f['currency'])>{{ $c }}</option>@endforeach</select></label><button type="submit" form="rw-filters" class="rw-btn rw-primary">Apply</button>
 </div></header>
 <div id="rw-custom" class="rw-custom" @if($f['range']!=='custom') hidden @endif><label>From <input form="rw-filters" type="date" name="from" value="{{ $f['from'] }}"></label><label>To <input form="rw-filters" type="date" name="to" value="{{ $f['to'] }}"></label></div>
 @if($errors->any())<div role="alert" class="rw-notice">{{ $errors->first() }}</div>@endif
 <div class="rw-grid"><div class="rw-main">
 @if($rewardError)<div class="rw-card rw-notice" role="alert"><h2>Unable to load rewards</h2><p>{{ $rewardError }}</p><a href="{{ request()->fullUrl() }}" class="rw-btn">Retry</a></div>@else
 <div class="rw-kpis" aria-label="Recorded reward totals">
 @foreach([['net','Net recorded rewards','After refund reversals','coins'],['hold','Rewards on hold','Waiting for hold period to end','gift'],['ready','Ready for review','Awaiting company review','check'],['reversals','Refund reversals','From recorded refunds','reverse']] as [$key,$label,$helper,$icon])
 <article class="rw-card rw-kpi"><div class="rw-kpi-title"><span class="rw-icon"><svg><use href="#rw-{{ $icon }}"/></svg></span><h2>{{ $label }}</h2></div><strong>{{ $money($rewards['kpis']->$key) }}</strong><p>{{ $helper }}</p></article>
 @endforeach</div>
 <section class="rw-card rw-history" aria-labelledby="rw-history-title"><div class="rw-history-heading"><h2 id="rw-history-title">Reward history <small>{{ number_format($records->total()) }} results</small></h2><label class="rw-search"><span class="sr-only">Search by referral or reference ID</span><input form="rw-filters" name="q" value="{{ $f['q'] }}" placeholder="Search by referral or reference ID…"></label></div>
 <div class="rw-history-filters"><label><span class="sr-only">Reward status</span><select form="rw-filters" name="status"><option value="all">All statuses</option>@foreach(\App\Services\Programs\ReferrerRewardHistory::STATES as $key=>$label)<option value="{{ $key }}" @selected($f['status']===$key)>{{ $label }}</option>@endforeach</select></label><label><span class="sr-only">Event type</span><select form="rw-filters" name="event">@foreach(['all'=>'All event types','payment'=>'Payment received','refund'=>'Refund reversal'] as $key=>$label)<option value="{{ $key }}" @selected($f['event']===$key)>{{ $label }}</option>@endforeach</select></label><button form="rw-filters" type="submit" class="rw-btn">Filter</button><a href="{{ route('reseller.commission',$base+['currency'=>$f['currency']]) }}">Clear filters</a></div>
 @if($f['referral_reference'])<p class="rw-scope">Showing referral {{ $f['referral_reference'] }} · <a href="{{ route('reseller.commission',$base) }}">All referrals</a></p>@endif
 <div class="rw-table-scroll" tabindex="0" role="region" aria-label="Reward history; scroll horizontally on small screens"><table><thead><tr>@foreach(['Date','Referral','Event','Description','Amount','Status','Actions'] as $heading)<th scope="col">{{ $heading }}</th>@endforeach</tr></thead><tbody>
 @foreach($records as $row)@php $version=$rewards['versions']->get($row->offer_version_id); @endphp
 <tr><td>{{ \Carbon\CarbonImmutable::parse($row->occurred_at,'UTC')->setTimezone($rewards['tz'])->format('M j, Y') }}</td><td><a href="{{ route('reseller.deals',$base+['referral'=>$row->reference,'currency'=>$f['currency']]) }}">{{ $row->reference }}</a></td><td>{{ $row->type==='refund'?'Refund reversal':'Payment received' }}</td><td>{{ $version?'Offer v'.$version->version_number:'Recorded event' }}<small>{{ $version?ucwords(str_replace('_',' ',$version->qualifying_event)):'Historical terms unavailable' }}</small></td><td class="rw-amount">{{ $money($row->reward_minor) }}</td><td><span class="rw-badge rw-state-{{ $row->reward_state }}">{{ \App\Services\Programs\ReferrerRewardHistory::STATES[$row->reward_state]??'Recorded · '.ucwords(str_replace('_',' ',$row->reward_state)) }}</span></td><td><a class="rw-btn" aria-label="View referral {{ $row->reference }}" href="{{ route('reseller.deals',$base+['referral'=>$row->reference,'currency'=>$f['currency']]) }}">View →</a></td></tr>
 @endforeach</tbody></table></div>
 @if($records->isEmpty())<div class="rw-empty">
 @if($rewards['unfiltered']>0 || $f['range']!=='all' || $f['q']!=='' || $f['status']!=='all' || $f['event']!=='all')<h3>No rewards match these filters.</h3><a class="rw-btn" href="{{ route('reseller.commission',$base) }}">Clear filters</a>@else
 <img src="{{ asset('images/mascots/r-bunny-celebration.webp') }}" width="96" height="96" alt="R Bunny celebrating" loading="lazy"><h3>No rewards recorded yet</h3><p>Your rewards will appear here once your referred customers make qualifying payments.</p><a class="rw-btn" href="{{ route('reseller.deals',$base) }}">View my referrals →</a>@endif</div>@endif
 @if($records->hasPages())<nav class="rw-pagination" aria-label="Reward history pagination"><span>{{ $records->firstItem() }}–{{ $records->lastItem() }} of {{ $records->total() }}</span>@if($records->previousPageUrl())<a class="rw-btn" href="{{ $records->previousPageUrl() }}">← Previous</a>@endif<span>Page {{ $records->currentPage() }} of {{ $records->lastPage() }}</span>@if($records->nextPageUrl())<a class="rw-btn" href="{{ $records->nextPageUrl() }}">Next →</a>@endif</nav>@endif
 </section><p class="rw-footnote">{{ $rewards['tz'] }} · {{ $f['range']==='all'?'All recorded activity':$f['from'].' – '.$f['to'] }}. Totals follow date, currency and referral scope; search/status/event filters affect the table only. Hold and review amounts reflect remaining rewards after all recorded refunds. Recorded rewards are not confirmed payouts.</p>
 @endif
 <div class="rw-card rw-support"><div><strong>Need help with a reward?</strong><p>Message the company admins with the referral reference.</p></div><a class="rw-btn" href="{{ $messages }}">Message company admins →</a></div>
 </div><aside class="rw-side" aria-label="Estimates and reward mechanics">
 <section class="rw-card rw-calculator" x-data="{data:{{ \Illuminate\Support\Js::from($estimate['calculator']) }},cadence:'monthly',selected:{{ \Illuminate\Support\Js::from($estimate['calculator']['default']) }},get pkg(){return this.data.packages.find(p=>p.id===this.selected)},get result(){return this.pkg?.[this.cadence]},money(n,c){return new Intl.NumberFormat('en-PH',{style:'currency',currency:c,minimumFractionDigits:0,maximumFractionDigits:2}).format(n/100)}}">
 <div class="rw-calc-title"><div><h2>Potential income calculator</h2><p>See how your referrals can add up.</p></div><span class="rw-estimate">Estimate</span></div>
 @if($estimate['calculator']['error'])<div class="rw-notice" role="status">{{ $estimate['calculator']['error'] }}</div>@else
 <div class="rw-cadence" role="group" aria-label="Customer billing cadence"><button type="button" @click="cadence='monthly'" :aria-pressed="cadence==='monthly'">Monthly</button><button type="button" @click="cadence='annual'" :aria-pressed="cadence==='annual'">Annual</button></div>
 <label class="rw-package">Select GetHired package<select x-model="selected"><template x-for="p in data.packages" :key="p.id"><option :value="p.id" x-text="p.name+' — '+(p[cadence+'_amount_minor']?money(p[cadence+'_amount_minor'],p.currency)+(cadence==='monthly'?' / month':' / year'):'Unavailable')"></option></template></select></label>
 <div class="rw-fixed"><span>Paying referrals<strong>100</strong></span><span>Fixed scenario</span></div>
 <div aria-live="polite" aria-atomic="true"><template x-if="result?.error"><p class="rw-notice" x-text="result.error"></p></template><template x-if="result && !result.error"><div>
 <div class="rw-result rw-rate"><div><strong x-text="result.rate"></strong><span>Your reward model</span></div><div><strong x-text="money(result.per_minor,result.currency)"></strong><span x-text="cadence==='annual'?'Per referral / annual payment':'Per referral / eligible payment'"></span></div></div>
 <div class="rw-result"><div><strong x-text="money(result.monthly_minor,result.currency)"></strong><span x-text="result.monthly_label"></span></div><div><strong x-text="money(result.year_minor,result.currency)"></strong><span>Estimated 1-year income</span></div></div>
 <p class="rw-calc-helper" x-text="cadence==='annual'?'One annual upfront payment per referral. Monthly equivalent is for comparison, not a monthly payout.':'100 paying referrals × '+result.eligible_payments+' eligible payment(s) in year one. Later months contribute no rewards after the eligible window ends.'"></p></div></template></div>
 <noscript><p>Enable JavaScript to select a package and view estimates.</p></noscript>
 @endif
 <p class="rw-disclosure">Illustrative estimate for 100 paying referrals using current GetHired prices and published reward rules. Assumes qualifying payments succeed throughout the eligible period. Refunds, cancellations, failed payments, plan changes, taxes and review outcomes may reduce rewards. Estimates are not confirmed rewards or payout balances.</p>
 </section>
 <section class="rw-card rw-mechanics"><div class="rw-section-title"><h2>Reward mechanics</h2><a href="{{ $mechanics }}">Full mechanics →</a></div>
 @forelse($estimate['offers'] as $offer)@php $v=$offer->currentVersion;$rules=$v->reward_rules??[]; @endphp
 <h3>{{ $offer->name }}</h3><dl><dt>Reward</dt><dd>{{ $v->reward_model==='percentage'?rtrim(rtrim($v->percentage_rate,'0'),'.').'%':($v->reward_model==='fixed'?$money((int)round((float)$v->fixed_amount*100),$v->currency):'Custom reward') }}</dd><dt>Qualifying event</dt><dd>{{ ucwords(str_replace('_',' ',$v->qualifying_event)) }}</dd><dt>Hold period</dt><dd>{{ isset($rules['hold_days'])?$rules['hold_days'].' days':'Not specified' }}</dd><dt>Eligible payments</dt><dd>{{ ($rules['scope']??'')==='first_payment'?'First payment only':(($rules['scope']??'')==='recurring' && isset($rules['duration_months'])?$rules['duration_months'].' months from first payment':'View full mechanics') }}</dd></dl>
 @empty<p>No current published offer.</p>@endforelse
 <p class="rw-disclosure">Current mechanics apply to future eligible events. Historical rewards retain the offer version applied when recorded.</p></section>
 <section class="rw-card rw-help"><h2>Need help?</h2><p>Have a question about a reward or missing payment?</p><a class="rw-btn" href="{{ $messages }}">Message company admins →</a></section>
 </aside></div>
</section>
@endsection
