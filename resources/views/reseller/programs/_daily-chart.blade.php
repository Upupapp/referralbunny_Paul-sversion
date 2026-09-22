@php
 $values=array_values($series); $dates=array_keys($series);
 $minimum=$integerAxis ? 0 : min(0,min($values ?: [0]));
 $maximum=$integerAxis ? max(1,(int)ceil(max($values ?: [0])/4))*4 : max(1,max($values ?: [0]));
 $span=$maximum-$minimum;
 $plotX=fn($i)=>$i/max(1,count($values)-1)*1000;
 $plotY=fn($value)=>110-($value-$minimum)/$span*100;
 $plotPoints=implode(' ',array_map(fn($i)=>$plotX($i).','.$plotY($values[$i]),array_keys($values)));
@endphp
@if(count($values))
<div class="daily-chart" role="img" aria-label="{{ $chartLabel }}. Exact daily values are available below.">
 <div class="chart-axis" aria-hidden="true">@for($tick=4;$tick>=0;$tick--)<span>{{ number_format($minimum+$span*$tick/4,$integerAxis?0:2) }}</span>@endfor</div>
 <div class="chart-plot">
 <svg viewBox="0 0 1000 120" preserveAspectRatio="none" aria-hidden="true">
  @for($tick=0;$tick<=4;$tick++)<line x1="0" x2="1000" y1="{{ $plotY($minimum+$span*$tick/4) }}" y2="{{ $plotY($minimum+$span*$tick/4) }}" stroke="#e3ece7" vector-effect="non-scaling-stroke"/>@endfor
  <polyline points="{{ $plotPoints }}" fill="none" stroke="#087f6b" stroke-width="2" vector-effect="non-scaling-stroke"/>
 </svg>
 @foreach($values as $i=>$value)
 @if(count($values)<=30 || $i%3===0 || $i===count($values)-1)<span class="chart-point" style="left:{{ $plotX($i)/10 }}%;top:{{ $plotY($value)/1.2 }}%" title="{{ $dates[$i] }}: {{ number_format($value,$integerAxis?0:2) }}"></span>@endif
 @endforeach
 </div>
 <div class="chart-dates" aria-hidden="true">@foreach(array_unique([0,(int)floor((count($dates)-1)/2),count($dates)-1]) as $index)<span>{{ \Carbon\Carbon::parse($dates[$index])->format('M j') }}</span>@endforeach</div>
</div>
@else<p class="muted">No daily measurements are available for this period.</p>@endif
