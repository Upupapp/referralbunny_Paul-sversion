@php
    $clickValues=array_values($d['dailyClicks']);
    $clickDates=array_keys($d['dailyClicks']);
@endphp
<section class="panel" id="daily-link-clicks" style="scroll-margin-top:20px">
    <div class="row">
        <div><h2>Daily link clicks</h2><p class="muted">Your short-link visits · {{ $d['tz'] }}</p></div>
        <nav class="range" aria-label="Click chart range">
            @foreach([7,30,90] as $clickDays)
                <a class="{{ $d['days']===$clickDays?'active':'' }}" @if($d['days']===$clickDays) aria-current="page" @endif href="{{ route('reseller.dashboard',array_replace($args,['days'=>$clickDays,'currency'=>$d['currency']])) }}#daily-link-clicks">{{ $clickDays }}D</a>
            @endforeach
        </nav>
    </div>
    <div class="compact-stats">
        <div><strong>{{ number_format($d['periodClicks']) }}</strong><p class="muted">Clicks in this period</p></div>
        <div><strong>{{ number_format($d['periodClicks']/$d['days'],1) }}</strong><p class="muted">Daily average</p></div>
        <div><strong>{{ number_format(max($clickValues ?: [0])) }}</strong><p class="muted">Most clicks in a day</p></div>
    </div>
    @include('reseller.programs._daily-chart',['series'=>$d['dailyClicks'],'integerAxis'=>true,'chartLabel'=>'Daily link clicks for the past '.$d['days'].' days'])
    <p class="muted" id="click-chart-description">{{ $d['start']->format('M j') }} – {{ $d['end']->format('M j, Y') }} · Today is partial. Average uses all {{ $d['days'] }} calendar days.</p>
    <details class="methodology"><summary class="link" style="cursor:pointer">View daily click counts</summary>
        <p class="muted">No recorded clicks may mean no visits or tracking was not yet available.</p><div style="max-height:220px;overflow:auto"><table><caption class="muted" style="text-align:left;padding:10px">Recorded clicks by date · {{ $d['tz'] }}</caption><thead><tr><th scope="col">Date</th><th scope="col">Clicks</th></tr></thead><tbody>
        @foreach($d['dailyClicks'] as $date=>$clickCount)<tr><td>{{ $date }}</td><td>{{ number_format($clickCount) }}</td></tr>@endforeach
        </tbody></table></div>
    </details>
</section>
