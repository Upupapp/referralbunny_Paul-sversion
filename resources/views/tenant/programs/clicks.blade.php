@extends('layouts.app')
@section('title','Referral click report')
@section('nav')@include('tenant._nav',['tenant'=>$tenant])@endsection
@section('content')
@php
 $filters=['tenantId'=>$tenant->id,'programId'=>$program->id,'from'=>$summary['from']->toDateString(),'to'=>$summary['to']->toDateString(),'currency'=>$summary['currency']];
 $back=route('tenant.dashboard',['tenantId'=>$tenant->id,'program_id'=>$program->id,'from'=>$filters['from'],'to'=>$filters['to'],'currency'=>$filters['currency']]);
@endphp
<style>
.rb-click-report{max-width:1300px;margin:auto;color:#29184c}.rb-click-report .panel{background:white;border:1px solid #e6def8;border-radius:18px;padding:24px;margin:16px 0}.rb-click-report h1{font-size:27px;font-weight:750}.rb-click-report h2{font-size:18px;font-weight:650}.rb-click-report p,.rb-click-report label{color:#78678e;font-size:13px;line-height:1.7}.rb-click-report a{color:#7435cc}.rb-click-report .row{display:flex;gap:20px;align-items:end;flex-wrap:wrap}.rb-click-report input,.rb-click-report select{display:block;border:1px solid #ddd0ee;border-radius:9px;background:white;padding:10px;color:#29184c;max-width:100%}.rb-click-report button{background:#7838db;color:white;border:0;border-radius:9px;padding:11px 18px;cursor:pointer}.rb-click-report .stats{display:flex;flex-wrap:wrap;gap:40px;margin-top:24px}.rb-click-report strong{font-size:27px}.rb-click-report .grid{display:grid;grid-template-columns:1fr 1.5fr;gap:16px}.rb-click-report table{width:100%;border-collapse:collapse;font-size:13px}.rb-click-report th{text-align:left;background:#f8f4ff;padding:12px;position:sticky;top:0}.rb-click-report td{padding:13px 12px;border-bottom:1px solid #eee7f8}.rb-click-report .table-wrap{overflow:auto;max-height:410px;margin-top:16px}.rb-click-report a:focus-visible,.rb-click-report button:focus-visible{outline:3px solid #bf9afe;outline-offset:3px}@media(max-width:850px){.rb-click-report .grid{grid-template-columns:1fr}.rb-click-report .panel{padding:18px}}
</style>
<div class="rb-click-report">
 <a href="{{ $back }}">← Back to program dashboard</a>
 <section class="panel"><h1>Referral click report</h1><p>{{ $program->name }} · {{ $summary['tz'] }}</p>
 <form method="GET" class="row" style="margin-top:20px">
  <label>From<input type="date" name="from" value="{{ $filters['from'] }}" required></label>
  <label>To<input type="date" name="to" value="{{ $filters['to'] }}" required></label>
  <input type="hidden" name="currency" value="{{ $filters['currency'] }}">
  <label>Referrer<select name="referrer"><option value="">All referrers</option>@foreach($names as $id=>$name)<option value="{{ $id }}" @selected($selected===$id)>{{ $name }}</option>@endforeach</select></label>
  <button>Apply filters</button>
 </form>
 @if($errors->any())<p role="alert">{{ $errors->first() }}</p>@endif
 <div class="stats"><div><strong>{{ number_format($total) }}</strong><p>Recorded clicks</p></div><div><strong>{{ number_format($total/count($daily),1) }}</strong><p>Average per calendar day</p></div><div><strong>{{ number_format(max($daily)) }}</strong><p>Most clicks in one day</p></div></div>
 <p style="margin-top:18px">{{ $summary['from']->format('M j, Y') }} – {{ $summary['to']->format('M j, Y') }} · Clicks are independent of currency. Repeat visits count. Previews, known bots and signed-in own-referrer/company-team visits are excluded. Earlier visits and blocked tracking cannot be recovered; zero means no recorded clicks. Future days and today may be incomplete.</p>
 </section>
 @include('components.signup-metrics',['signupTimezone'=>$summary['tz']])
 @include('tenant.programs._click-report-chart')
 <div class="grid">
  <section class="panel"><h2>Daily clicks</h2><p>Calendar dates in {{ $summary['tz'] }}</p><div class="table-wrap"><table><thead><tr><th scope="col">Date</th><th scope="col">Clicks</th></tr></thead><tbody>@foreach($daily as $date=>$count)<tr><td>{{ $date }}</td><td>{{ $date>now($summary['tz'])->toDateString()?'Not yet due':number_format($count) }}</td></tr>@endforeach</tbody></table></div></section>
  <section class="panel"><h2>Clicks by referrer</h2><p>Unique browsers are estimated per referral link using a 30-day cookie; they are not verified people.</p><div class="table-wrap"><table><thead><tr><th scope="col">Referrer</th><th scope="col">Clicks</th><th scope="col">Unique browsers</th><th scope="col">Latest click</th></tr></thead><tbody>
  @forelse($breakdown as $row)@php $referrer=$memberReferrers[$row->membership_id]; @endphp<tr><td><a href="{{ route('tenant.programs.clicks',$filters+['referrer'=>$referrer]) }}">{{ $names[$referrer]??'Former referrer' }}</a></td><td>{{ number_format($row->clicks) }}</td><td>{{ number_format($row->browsers) }}</td><td>{{ \Carbon\Carbon::parse($row->latest,'UTC')->setTimezone($summary['tz'])->format('M j, H:i') }}</td></tr>
  @empty<tr><td colspan="4">No recorded clicks match these filters.</td></tr>@endforelse
  </tbody></table></div></section>
 </div>
 <section class="panel"><h2>Recent clicks</h2><p>Recorded visits, newest first · {{ $summary['tz'] }}</p><div class="table-wrap"><table><thead><tr><th scope="col">Date & time</th><th scope="col">Referrer</th></tr></thead><tbody>
 @forelse($recent as $click)<tr><td>{{ \Carbon\Carbon::parse($click->created_at,'UTC')->setTimezone($summary['tz'])->format('M j, Y H:i:s') }}</td><td>{{ $names[$memberReferrers[$click->membership_id]]??'Former referrer' }}</td></tr>@empty<tr><td colspan="2">No recorded clicks match these filters.</td></tr>@endforelse
 </tbody></table></div>{{ $recent->links() }}</section>
</div>
@endsection
