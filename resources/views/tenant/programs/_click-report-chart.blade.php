@php
 $clickDates=array_keys($daily); $clickValues=array_values($daily);
 $today=now($summary['tz'])->toDateString();
 $visible=array_values(array_filter(array_keys($clickDates),fn($i)=>$clickDates[$i]<=$today));
 $step=max(1,(int)ceil(max($clickValues)/4)); $ceiling=$step*4;
 $cx=fn($i)=>65+$i/max(1,count($clickDates)-1)*960;
 $cy=fn($n)=>250-$n/$ceiling*205;
 $line=implode(' ',array_map(fn($i)=>$cx($i).','.$cy($clickValues[$i]),$visible));
 $labels=array_unique([0,(int)floor((count($clickDates)-1)/2),count($clickDates)-1]);
@endphp
<section class="panel" id="admin-click-chart" style="scroll-margin-top:20px">
 <div class="row" style="justify-content:space-between;align-items:center">
  <div><h2>Daily clicks trend</h2><p>{{ $selected ? ($names[$selected]??'Former referrer') : 'All referrers' }} · {{ $summary['tz'] }}</p></div>
  <nav class="row" aria-label="Click chart date range" style="gap:8px">
   @foreach([7,30,90] as $rangeDays)
   @php $rangeFrom=now($summary['tz'])->subDays($rangeDays-1)->toDateString(); $active=$filters['from']===$rangeFrom && $filters['to']===$today; @endphp
   <a href="{{ route('tenant.programs.clicks',array_merge($filters,['from'=>$rangeFrom,'to'=>$today,'referrer'=>$selected])) }}#admin-click-chart" @if($active) aria-current="page" @endif style="padding:7px 12px;border:1px solid #e6def8;border-radius:9px;text-decoration:none;{{ $active?'background:#7838db;color:white':'' }}">{{ $rangeDays }}D</a>
   @endforeach
  </nav>
 </div>
 <svg viewBox="0 0 1060 300" role="img" aria-label="Daily recorded clicks for the selected program and referrer filters" aria-describedby="admin-click-chart-note" style="width:100%;height:auto;display:block;margin-top:14px">
  <defs><linearGradient id="admin-click-area" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#8b45e7" stop-opacity=".24"/><stop offset="100%" stop-color="#8b45e7" stop-opacity=".02"/></linearGradient></defs>
  @for($i=0;$i<=4;$i++)
   <line x1="65" x2="1025" y1="{{ $cy($i*$step) }}" y2="{{ $cy($i*$step) }}" stroke="#ece5f7"/>
   <text x="53" y="{{ $cy($i*$step)+4 }}" text-anchor="end" fill="#78678e" font-size="12">{{ number_format($i*$step) }}</text>
  @endfor
  @if(count($visible))
   <polygon points="{{ $cx($visible[0]) }},250 {{ $line }} {{ $cx(end($visible)) }},250" fill="url(#admin-click-area)"/>
   <polyline points="{{ $line }}" fill="none" stroke="#7838db" stroke-width="3"/>
   @foreach($visible as $i)
    @if(count($visible)<=90 || $clickValues[$i]>0 || $i===end($visible))<circle cx="{{ $cx($i) }}" cy="{{ $cy($clickValues[$i]) }}" r="3" fill="#7838db"><title>{{ $clickDates[$i] }}: {{ number_format($clickValues[$i]) }} clicks</title></circle>@endif
   @endforeach
  @endif
  @foreach($labels as $i)<text x="{{ $cx($i) }}" y="278" text-anchor="{{ $i===0?'start':($i===count($clickDates)-1?'end':'middle') }}" fill="#78678e" font-size="12">{{ \Carbon\Carbon::parse($clickDates[$i])->format('M j, Y') }}</text>@endforeach
 </svg>
 <p id="admin-click-chart-note">{{ $summary['from']->format('M j, Y') }} – {{ $summary['to']->format('M j, Y') }} · {{ number_format($total) }} recorded clicks. Today is partial; future dates are not plotted. Daily counts are available in the table below.</p>
 @if($total===0)<p style="background:#f8f4ff;border-radius:10px;padding:12px;margin-top:12px">No clicks recorded for these filters yet. Earlier visits and blocked tracking are not included.</p>@endif
</section>
