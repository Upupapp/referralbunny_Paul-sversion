@extends('layouts.reseller')
@section('title','My Referrals')
@section('nav') @include('reseller._nav') @endsection
@section('content')
@php
 extract($referralAccounts);
 $args=['tenantId'=>$tenant->id,'program_id'=>$program->id];
 $index=route('reseller.deals',$args);
 $mechanics=route('reseller.programs.show',[$tenant->id,$program->id]).'?tab=mechanics';
 $messages=route('reseller.messages',$args);
 $detailBase=route('reseller.referral-accounts.detail',[$tenant->id,'__REFERENCE__']);
 $money=fn($minor)=>$f['currency'].' '.number_format($minor/100,2);
 $date=fn($v)=>$v ? \Carbon\CarbonImmutable::parse($v,'UTC')->setTimezone($program->timezone ?: 'UTC')->format('M j, Y') : 'Date unavailable';
 $preview=$referralLink ? (string)\Illuminate\Support\Uri::of($referralLink)->withQuery(['preview'=>1]) : null;
@endphp
<div class="ra-page" data-detail-base="{{ $detailBase }}" data-program="{{ $program->id }}" data-currency="{{ $f['currency'] }}" data-messages="{{ $messages }}">
 <div class="ra-heading">
  <div><h1>My Referrals</h1><p class="ra-muted">{{ $program->name }} · Your activity only</p></div>
  <div class="ra-actions">
   <details class="ra-menu"><summary class="ra-button ra-primary">Share referral link <span aria-hidden="true">⌄</span></summary><div class="ra-menu-panel">
    <strong>Your link · {{ $program->name }}</strong>
    @if($referralLink)<label for="ra-link" class="ra-muted">Select the link to copy it manually if needed.</label><input id="ra-link" value="{{ $referralLink }}" readonly><button type="button" data-copy>Copy referral link</button><a href="{{ $preview }}" target="_blank" rel="noopener">Preview link ↗</a>@else<p>Your referral link is not available. Check your membership with the company.</p>@endif
    <a href="{{ $mechanics }}">Program mechanics →</a><a href="{{ $messages }}">Message company admins →</a><span role="status" data-copy-status></span>
   </div></details>
   @if($exportPermission['allowed'])<button class="ra-button" type="button" data-open-export>Export CSV</button>@else<span class="ra-export-disabled" title="{{ $exportPermission['reason'] }}">Export unavailable</span>@endif
  </div>
 </div>

 @if($errors->any())<div class="ra-notice" role="alert">{{ $errors->first() }}</div>@endif
 <div class="ra-kpis" aria-label="Referral cohort totals">
  @foreach([
   ['Total referred accounts',$kpis->total,'Accounts in this referral cohort','users'],
   ['Registered · No payment recorded',$kpis->unpaid,'Registration received; no payment recorded','users'],
   ['Paying customers',$kpis->paying,'At least one distinct recorded payment','wallet'],
   ['Repeat-paying customers',$kpis->repeat_paying,'Two or more distinct recorded payments','renew'],
   ['Net recorded rewards',$money($kpis->rewards),'After refund reversals · not a payout balance','gift']
  ] as [$label,$value,$caption,$icon])
   <section class="ra-kpi"><div class="ra-kpi-label"><span class="ra-icon">@include('tenant.programs._dashboard-icon',['icon'=>$icon])</span><h2>{{ $label }}</h2></div><strong class="ra-kpi-value">{{ $value }}</strong><p>{{ $caption }}</p></section>
  @endforeach
 </div>
 <section class="ra-table-panel" aria-labelledby="ra-table-title">
  <h2 id="ra-table-title" class="sr-only">Referred accounts</h2>
  <form method="GET" action="{{ route('reseller.deals',$tenant->id) }}" class="ra-filters" id="ra-filters">
   <input type="hidden" name="program_id" value="{{ $program->id }}">
   <label class="ra-search"><span class="sr-only">Search referral reference</span><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10" cy="10" r="6"/><path d="m15 15 5 5"/></svg><input name="q" value="{{ $f['q'] }}" maxlength="100" placeholder="Search referral reference…"></label>
   <label><span class="sr-only">Referral progress</span><select name="progress">@foreach(['all'=>'All progress','registered'=>'Registered','unpaid'=>'No payment recorded','paying'=>'Paying customers','repeat'=>'Repeat-paying customers'] as $value=>$label)<option value="{{ $value }}" @selected($f['progress']===$value)>{{ $label }}</option>@endforeach</select></label>
   <label><span class="sr-only">Referred date range</span><select name="range" id="ra-range">@foreach(['all'=>'All time','30'=>'Last 30 days','90'=>'Last 90 days','custom'=>'Custom dates'] as $value=>$label)<option value="{{ $value }}" @selected($f['range']===(string)$value)>{{ $label }}</option>@endforeach</select></label>
   <span class="ra-date-fields" @if($f['range']!=='custom') hidden @endif><label>From<input type="date" name="from" value="{{ $f['from'] }}"></label><label>To<input type="date" name="to" value="{{ $f['to'] }}"></label></span>
   <details class="ra-menu ra-more"><summary class="ra-button">More filters <span aria-hidden="true">⌄</span></summary><div class="ra-menu-panel"><label>Reward currency<select name="currency">@foreach($currencies as $currency)<option @selected($f['currency']===$currency)>{{ $currency }}</option>@endforeach</select></label><label>Sort by<select name="sort">@foreach(['newest'=>'Newest referred','oldest'=>'Oldest referred','activity'=>'Latest activity','rewards'=>'Highest rewards'] as $value=>$label)<option value="{{ $value }}" @selected($f['sort']===$value)>{{ $label }}</option>@endforeach</select></label><label>Rows per page<select name="per_page">@foreach([10,25,50] as $count)<option @selected((int)$f['per_page']===$count)>{{ $count }}</option>@endforeach</select></label><p class="ra-muted">Subscription and eligibility filters are unavailable until the connected platform supplies reliable status data.</p></div></details>
   <button class="ra-button ra-primary" type="submit">Apply</button><a class="ra-clear" href="{{ $index }}">Clear filters</a>
  </form>
  <div class="ra-table-scroll {{ $rows->isEmpty() ? 'ra-table-empty' : '' }}" tabindex="0" role="region" aria-label="Referred accounts table; scroll horizontally on smaller screens">
   <table><thead><tr><th scope="col">Referral</th><th scope="col">Referred on</th><th scope="col">Progress</th><th scope="col">Payments</th><th scope="col">Your rewards</th><th scope="col">Eligibility</th><th scope="col">Latest activity</th><th scope="col">Actions</th></tr></thead><tbody>
   @forelse($rows as $row)
    <tr><td><button class="ra-reference" type="button" data-referral="{{ $row->reference }}" aria-label="{{ $row->reference }}"><span class="ra-account-icon" aria-hidden="true">#</span><span>{{ $row->reference }}<small>Private account reference</small></span></button></td><td>{{ $date($row->referred_at) }}@unless($row->referred_at)<small>Signup mapping unavailable</small>@endunless</td><td><span class="ra-badge {{ $row->payments>0 ? 'ra-paid' : '' }}">{{ $row->payments>1 ? 'Repeat payment' : ($row->payments>0 ? 'First payment' : ($row->registered_at ? 'Registered' : 'Activity recorded')) }}</span></td><td><strong>{{ $row->payments }}</strong><small>{{ $row->last_payment ? 'Last: '.$date($row->last_payment) : 'No payment recorded' }}</small></td><td><strong>{{ $money($row->rewards) }}</strong><small>Net recorded</small></td><td><span class="ra-muted" title="The connected feed does not supply reliable subscription or reward-window status.">Not available</span></td><td>{{ \Illuminate\Support\Str::after($row->activity_description,'|') }}<small>{{ $date($row->activity_at) }}</small></td><td><button class="ra-button ra-view" type="button" data-referral="{{ $row->reference }}" aria-label="View referral {{ $row->reference }}">View →</button></td></tr>
   @empty<tr><td colspan="8"><div class="ra-empty"><img src="/images/mascots/r-bunny-thumbs-up.webp" alt="" width="48" height="48"><h3>{{ $kpis->total ? 'No referrals match these filters' : 'Your first referral starts with a share' }}</h3><p>{{ $kpis->total ? 'Try another reference or clear your filters.' : 'Verified registrations and attributed payments will appear here when received.' }}</p>@if($kpis->total)<a href="{{ $index }}">Clear filters →</a>@endif</div></td></tr>@endforelse
   </tbody></table>
  </div>
  <div class="ra-pagination"><span>Showing {{ $rows->firstItem() ?? 0 }}–{{ $rows->lastItem() ?? 0 }} of {{ number_format($rows->total()) }} referrals</span><nav aria-label="Referral table pagination">@if($rows->onFirstPage())<span aria-disabled="true">← Previous</span>@else<a href="{{ $rows->previousPageUrl() }}">← Previous</a>@endif<span>Page {{ $rows->currentPage() }} of {{ $rows->lastPage() }}</span>@if($rows->hasMorePages())<a href="{{ $rows->nextPageUrl() }}">Next →</a>@else<span aria-disabled="true">Next →</span>@endif</nav></div>
 </section>
 <div class="ra-footnote"><p>{{ $program->timezone ?: 'UTC' }} · {{ $f['from'] ? $f['from'].' – '.$f['to'].' referred' : 'All-time referrals' }} · Payments and rewards recorded to date.</p><p>Totals use the referred-date cohort; search and progress filters affect the table only. Currency changes reward amounts, not account counts. Unknown referral dates appear only in All time.</p><p>Last signup sync: {{ $sync?->synced_at ? $date($sync->synced_at).' · '.ucfirst($sync->status) : 'Not yet available' }}. @if($unmatched){{ $unmatched }} payment/refund records have no account identity and are excluded.@endif @if($unlinked){{ $unlinked }} payment-linked accounts have no verified signup mapping. Their registration dates are unavailable; registrations may appear separately until mapping is received.@endif</p></div>
 <aside class="ra-help"><div><strong>Can’t find a referral?</strong><p>Ask the company to review it. Include the date and any details you can share.</p></div><a class="ra-button" href="{{ $messages }}&missing_referral=1">Report missing referral →</a></aside>
 <dialog class="ra-drawer" id="ra-detail" aria-labelledby="ra-detail-title"><header><div><p class="ra-muted">Referral details</p><h2 id="ra-detail-title">Loading referral…</h2></div><button type="button" class="ra-button" data-close-dialog aria-label="Close referral details">✕</button></header><div class="ra-drawer-content" data-detail-content aria-live="polite"></div><footer><a class="ra-button" href="{{ $mechanics }}">Program mechanics</a><a class="ra-button" data-detail-rewards data-base="{{ route('reseller.commission',$args) }}" href="{{ route('reseller.commission',$args) }}">View rewards</a><a class="ra-button ra-primary" data-detail-message href="{{ $messages }}">Message company</a></footer></dialog>
 @if($exportPermission['allowed'])<dialog class="ra-export-dialog" id="ra-export" aria-labelledby="ra-export-title"><h2 id="ra-export-title">Export matching referrals</h2><p>Includes all {{ $rows->total() }} matching accounts, with {{ $f['currency'] }} rewards recorded to date. {{ $exportPermission['requires_approval'] ? 'Company approval is required before download.' : 'Your export will be prepared for download.' }}</p><form method="POST" action="{{ route('reseller.referral-accounts.export',$args) }}">@csrf @foreach($f as $key=>$value)@if($key!=='page')<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif @endforeach<label>Reason for export<textarea name="reason" maxlength="1000" required></textarea></label><footer><button class="ra-button" type="button" data-close-dialog>Cancel</button><button class="ra-button ra-primary" type="submit">Request export</button></footer></form></dialog>@endif
</div>
@endsection
@push('scripts')
<script>
(()=>{
 const page=document.querySelector('.ra-page'); if(!page)return;
 const dialog=page.querySelector('#ra-detail'),content=page.querySelector('[data-detail-content]');let opener=null,request=null,current=null;
 const text=(tag,value,parent,cls)=>{const el=document.createElement(tag);el.textContent=value;if(cls)el.className=cls;parent.append(el);return el;};
 const show=d=>{opener=document.activeElement;d.showModal();document.body.style.overflow='hidden';};
 page.querySelectorAll('dialog').forEach(d=>{d.addEventListener('close',()=>{document.body.style.overflow='';opener?.focus();if(d===dialog){request?.abort();const url=new URL(location);url.searchParams.delete('referral');history.replaceState({},'',url);}});d.addEventListener('click',e=>{if(e.target===d){const r=d.getBoundingClientRect();if(e.clientX<r.left||e.clientX>r.right||e.clientY<r.top||e.clientY>r.bottom)d.close();}});});
 page.querySelectorAll('[data-close-dialog]').forEach(b=>b.addEventListener('click',()=>b.closest('dialog').close()));
 page.querySelector('[data-open-export]')?.addEventListener('click',()=>show(page.querySelector('#ra-export')));
 page.querySelector('#ra-range').addEventListener('change',e=>{page.querySelector('.ra-date-fields').hidden=e.target.value!=='custom';});
 page.querySelector('[data-copy]')?.addEventListener('click',async()=>{const input=page.querySelector('#ra-link'),status=page.querySelector('[data-copy-status]');try{await navigator.clipboard.writeText(input.value);status.textContent='Referral link copied.';}catch{input.focus();input.select();status.textContent='Select and copy this link using your device’s copy command.';}});
 document.addEventListener('click',e=>{page.querySelectorAll('details[open]').forEach(d=>{if(!d.contains(e.target))d.open=false;});});
 page.addEventListener('keydown',e=>{if(e.key==='Escape')page.querySelectorAll('details[open]').forEach(d=>d.open=false);});
 async function load(reference,historyPage=1){
  current=reference;request?.abort();request=new AbortController();content.replaceChildren();text('p','Loading referral details…',content);page.querySelector('#ra-detail-title').textContent=reference;if(!dialog.open)show(dialog);page.querySelector('[data-detail-message]').hidden=true;page.querySelector('[data-detail-rewards]').hidden=true;
  const url=new URL(page.dataset.detailBase.replace('__REFERENCE__',encodeURIComponent(reference)),location.origin);url.searchParams.set('program_id',page.dataset.program);url.searchParams.set('currency',page.dataset.currency);url.searchParams.set('history_page',historyPage);
  const deep=new URL(location);deep.searchParams.set('referral',reference);history.replaceState({},'',deep);
  try{const res=await fetch(url,{headers:{Accept:'application/json'},credentials:'same-origin',signal:request.signal});if(!res.ok)throw new Error(res.status===404?'This referral is unavailable in your selected program.':'Unable to load details. Please try again.');const d=await res.json();content.replaceChildren();
   text('p',d.program,content,'ra-muted');text('p',d.attribution,content,'ra-notice');
   const facts=text('dl','',content,'ra-facts');for(const [k,v]of Object.entries({'Referred on':d.referred,'Registered on':d.registered,'Last signup sync':d.synced,'Timezone':d.timezone,'First payment':d.first_payment,'Latest payment':d.last_payment,'Latest activity':d.latest_activity,'Distinct payments':d.payments,'Net recorded rewards':d.rewards,'Refund reversals':d.reversals})){text('dt',k,facts);text('dd',v,facts);}
   text('h3','Recorded reward states',content);if(!d.reward_states.length)text('p','No recorded rewards yet.',content,'ra-muted');d.reward_states.forEach(s=>text('p',s.status+' · '+s.amount,content));text('h3','Reward eligibility',content);text('p',d.eligibility,content,'ra-muted');text('p','Recorded rewards are not confirmed payouts. Payment arrangements are handled by the company.',content,'ra-notice');
   text('h3','Historical reward rules',content);if(!d.offers.length)text('p','No reward offer has been recorded for this account yet.',content,'ra-muted');d.offers.forEach(o=>text('p',`Offer ${o.version} · ${o.rate} · ${o.event} · ${o.scope}${o.duration ? ' · '+o.duration+' months' : ''}${o.hold!==null ? ' · '+o.hold+'-day hold' : ''}`,content));
   text('h3','Recorded activity',content);if(!d.history.length)text('p','No activity recorded.',content,'ra-muted');d.history.forEach(e=>{const line=text('div','',content,'ra-timeline-item');text('strong',e.type+(e.amount ? ' · '+e.amount : ''),line);text('p',e.date+' · '+e.status,line,'ra-muted');if(e.available!=='Date unavailable')text('p','Review eligibility date: '+e.available,line,'ra-muted');});
   if(d.history_pages>1){const nav=text('div','',content,'ra-actions');if(d.history_page>1){const b=text('button','← Newer',nav,'ra-button');b.onclick=()=>load(reference,d.history_page-1);}text('span',`Page ${d.history_page} of ${d.history_pages}`,nav);if(d.history_page<d.history_pages){const b=text('button','Older →',nav,'ra-button');b.onclick=()=>load(reference,d.history_page+1);}}
   page.querySelector('[data-detail-rewards]').hidden=false;page.querySelector('[data-detail-rewards]').href=page.querySelector('[data-detail-rewards]').dataset.base+'&referral_reference='+encodeURIComponent(reference);page.querySelector('[data-detail-message]').hidden=false;page.querySelector('[data-detail-message]').href=page.dataset.messages+'&referral_reference='+encodeURIComponent(reference);
  }catch(e){if(e.name==='AbortError')return;content.replaceChildren();text('p',e.message,content,'ra-notice');const retry=text('button','Retry',content,'ra-button');retry.onclick=()=>load(reference,historyPage);}
 }
 page.querySelectorAll('[data-referral]').forEach(b=>b.addEventListener('click',()=>load(b.dataset.referral)));
 const initial=new URL(location).searchParams.get('referral');if(initial)load(initial);
})();
</script>
@endpush
