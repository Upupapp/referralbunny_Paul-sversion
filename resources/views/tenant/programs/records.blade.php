@extends('layouts.app')
@section('title','Subscription records')
@section('nav')@include('tenant._nav',['tenant'=>$tenant])@endsection
@section('content')
@php $back=route('tenant.dashboard',['tenantId'=>$tenant->id,'program_id'=>$program->id,'from'=>$summary['from']->toDateString(),'to'=>$summary['to']->toDateString(),'currency'=>$summary['currency']]); @endphp
<div style="max-width:1250px;margin:auto;color:#29184c">
 <a href="{{ $back }}" style="color:#813cff">← Back to program dashboard</a>
 <div style="background:white;border:1px solid #e6def8;border-radius:18px;padding:24px;margin-top:16px">
 <h1 style="font-size:25px;font-weight:700">{{ $queue==='ready'?'Rewards ready for review':($queue==='hold'?'Rewards on hold':'Payments & rewards') }}</h1>
 <p style="color:#817094;margin:8px 0">{{ $program->name }} · {{ $queue ? 'Current queue · all dates and currencies' : $summary['from']->format('M j, Y').' – '.$summary['to']->format('M j, Y').' · '.$summary['currency'] }} · {{ $summary['tz'] }}</p>
 <p style="color:#817094;font-size:13px">Recorded events retain their original reward amounts. Refunds appear as reversals. This screen does not approve rewards or send payouts.</p>
 <div style="overflow:auto;margin-top:22px"><table style="width:100%;font-size:13px;border-collapse:collapse"><thead><tr>@foreach(['Date','Referrer','Invoice','Event','Net revenue effect','Recorded reward','Status','Review after'] as $label)<th style="text-align:left;padding:12px;background:#f8f4ff">{{ $label }}</th>@endforeach</tr></thead><tbody>
 @forelse($records as $r)<tr>@foreach([\Carbon\Carbon::parse($r->occurred_at,'UTC')->setTimezone($summary['tz'])->format('M j, Y H:i'),$names[$r->referrer_id]??'Unassigned',$r->invoice_id,ucfirst($r->type),$r->currency.' '.number_format(($r->type==='refund'?-1:1)*$r->amount_minor/100,2),$r->currency.' '.number_format($r->reward_minor/100,2),ucfirst(str_replace('_',' ',$r->status)),$r->available_at?\Carbon\Carbon::parse($r->available_at,'UTC')->setTimezone($summary['tz'])->format('M j, Y'):'—'] as $value)<td style="padding:13px;border-bottom:1px solid #eee7f8">{{ $value }}</td>@endforeach</tr>@empty<tr><td colspan="8" style="padding:30px;color:#817094;text-align:center">No recorded events match this view.</td></tr>@endforelse
 </tbody></table></div>{{ $records->links() }}
 </div>
</div>
@endsection
