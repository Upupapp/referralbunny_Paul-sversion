@if(($signupVariant ?? null) === 'compact')
<section class="panel signup-compact" aria-label="Click to signup conversion">
 <h2>Clicks to signups</h2>
 <div class="compact-stats">
 @foreach([[$signupMetrics['signups'],'Referred signups in this period'],[$signupMetrics['converted'],'Clicks leading to signup']] as [$value,$label])<div><strong>{{ $signupMetrics['synced_at'] ? number_format($value) : '—' }}</strong><p class="muted">{{ $label }}</p></div>@endforeach
 <div><strong>{{ $signupMetrics['synced_at'] && $signupMetrics['rate'] !== null ? $signupMetrics['rate'].'%' : '—' }}</strong><p class="muted">Click-to-signup conversion</p></div>
 </div>
 <p class="muted">A signup is not a payment or reward.</p>
 <p class="muted">@if($signupMetrics['synced_at'])Last signup sync: {{ \Carbon\Carbon::parse($signupMetrics['synced_at'],'UTC')->setTimezone($signupTimezone)->format('M j, Y H:i') }} · {{ $signupTimezone }}.@else Waiting for verified signup data from the connected platform.@endif @if($signupMetrics['status']==='unavailable') Sync is temporarily unavailable; showing the last received data.@elseif($signupMetrics['synced_at']) Updates approximately every five minutes.@endif</p>
 <details class="methodology"><summary class="link">How signup conversion is measured</summary><p class="muted">Referred signups count verified new GetHired employer registrations by signup date. Conversion counts selected-period clicks that led to at least one signup, including signups afterward. Older referrals without a recorded click are excluded from conversion.</p></details>
</section>
@else
<section class="panel" style="margin:16px 0" aria-label="Click to signup conversion">
 <h2>Clicks to signups</h2>
 <div style="display:flex;gap:28px;flex-wrap:wrap;margin:16px 0">
  <div><strong style="font-size:25px">{{ $signupMetrics['synced_at']?number_format($signupMetrics['signups']):'—' }}</strong><p class="muted">Referred signups in this period</p></div>
  <div><strong style="font-size:25px">{{ $signupMetrics['synced_at']?number_format($signupMetrics['converted']):'—' }}</strong><p class="muted">Clicks leading to signup</p></div>
  <div><strong style="font-size:25px">{{ $signupMetrics['synced_at'] && $signupMetrics['rate']!==null?$signupMetrics['rate'].'%':'—' }}</strong><p class="muted">Click-to-signup conversion</p></div>
 </div>
 <p class="muted" style="font-size:12px;line-height:1.7">Referred signups count verified new GetHired employer registrations by signup date. Conversion counts selected-period clicks that led to at least one signup, including signups afterward. Older referrals without a recorded click are excluded from conversion. A signup is not a payment or reward.</p>
 <p class="muted" style="font-size:12px;margin-top:8px">@if($signupMetrics['synced_at'])Last signup sync: {{ \Carbon\Carbon::parse($signupMetrics['synced_at'],'UTC')->setTimezone($signupTimezone)->format('M j, Y H:i') }} · {{ $signupTimezone }}.@else Waiting for verified signup data from the connected platform.@endif @if($signupMetrics['status']==='unavailable') Sync is temporarily unavailable; showing the last received data.@else Updates approximately every five minutes for connected GetHired programs.@endif</p>
</section>
@endif
