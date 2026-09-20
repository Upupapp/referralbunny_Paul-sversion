@php
    $clickValues=array_values($d['dailyClicks']);
    $clickDates=array_keys($d['dailyClicks']);
    $clickStep=max(1,(int)ceil(max($clickValues)/4));
    $clickMax=$clickStep*4;
    $clickX=fn($i)=>60+$i/max(1,count($clickValues)-1)*720;
    $clickY=fn($value)=>220-$value/$clickMax*175;
    $clickPoints=implode(' ',array_map(fn($i)=>$clickX($i).','.$clickY($clickValues[$i]),array_keys($clickValues)));
@endphp
<section class="panel" id="daily-link-clicks" style="scroll-margin-top:20px">
    <div class="row">
        <div><h2>Daily link clicks</h2><p class="muted">Your short-link visits · {{ $d['tz'] }}</p></div>
        <nav class="range" aria-label="Click chart range">
            @foreach([7,30,90] as $clickDays)
                <a class="{{ $d['days']===$clickDays?'active':'' }}" @if($d['days']===$clickDays) aria-current="page" @endif href="{{ route('reseller.dashboard',$args+['days'=>$clickDays,'currency'=>$d['currency']]) }}#daily-link-clicks">{{ $clickDays }}D</a>
            @endforeach
        </nav>
    </div>
    <div class="row" style="justify-content:flex-start;gap:24px;margin-top:18px">
        <div><strong style="font-size:24px">{{ number_format($d['periodClicks']) }}</strong><p class="muted">Clicks in this period</p></div>
        <div><strong style="font-size:24px">{{ number_format($d['periodClicks']/$d['days'],1) }}</strong><p class="muted">Daily average</p></div>
        <div><strong style="font-size:24px">{{ number_format(max($clickValues)) }}</strong><p class="muted">Most clicks in a day</p></div>
    </div>
    <svg class="chart" viewBox="0 0 810 260" role="img" aria-label="Daily link clicks for the past {{ $d['days'] }} days" aria-describedby="click-chart-description">
        <defs><linearGradient id="referrer-click-fill" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#0d9488" stop-opacity=".22"/><stop offset="100%" stop-color="#0d9488" stop-opacity=".02"/></linearGradient></defs>
        @for($i=0;$i<=4;$i++)
            @php $clickTick=$clickStep*$i; @endphp
            <line x1="60" x2="780" y1="{{ $clickY($clickTick) }}" y2="{{ $clickY($clickTick) }}" stroke="#dceee9"/>
            <text x="50" y="{{ $clickY($clickTick)+4 }}" text-anchor="end" fill="#526f6b" font-size="10">{{ number_format($clickTick) }}</text>
        @endfor
        <polygon points="60,220 {{ $clickPoints }} 780,220" fill="url(#referrer-click-fill)"/>
        <polyline points="{{ $clickPoints }}" fill="none" stroke="#0f766e" stroke-width="2.5"/>
        @foreach($clickValues as $i=>$clickCount)
            <circle cx="{{ $clickX($i) }}" cy="{{ $clickY($clickCount) }}" r="3" fill="#0f766e"><title>{{ $clickDates[$i] }}: {{ number_format($clickCount) }} clicks</title></circle>
        @endforeach
        @foreach([0,(int)floor((count($clickDates)-1)/2),count($clickDates)-1] as $i)
            <text x="{{ $clickX($i) }}" y="245" text-anchor="{{ $i===0?'start':($i===count($clickDates)-1?'end':'middle') }}" fill="#526f6b" font-size="10">{{ \Carbon\Carbon::parse($clickDates[$i])->format('M j') }}</text>
        @endforeach
    </svg>
    <p class="muted" id="click-chart-description">{{ $d['start']->format('M j') }} – {{ $d['end']->format('M j, Y') }} · Today is still in progress. Daily average uses all {{ $d['days'] }} calendar days in this view. No recorded clicks may mean no visits or tracking was not yet available.</p>
    @if($d['periodClicks']===0)<div class="note">No clicks recorded in this period yet. Share your short link to start seeing activity here.</div>@endif
    <details style="margin-top:12px"><summary class="link" style="cursor:pointer">View daily click counts</summary>
        <div style="max-height:220px;overflow:auto"><table><caption class="muted" style="text-align:left;padding:10px">Recorded clicks by date · {{ $d['tz'] }}</caption><thead><tr><th scope="col">Date</th><th scope="col">Clicks</th></tr></thead><tbody>
        @foreach($d['dailyClicks'] as $date=>$clickCount)<tr><td>{{ $date }}</td><td>{{ number_format($clickCount) }}</td></tr>@endforeach
        </tbody></table></div>
    </details>
</section>
