@extends('layouts.reseller')
@section('nav') @include('reseller._nav') @endsection
@section('title', $page)
@section('content')
<style>
.rb-referrer-program{max-width:1100px;margin:auto;color:#292147}.rb-referrer-program .rb-panel{background:white;border:1px solid #e6dfef;border-radius:18px;padding:24px;margin-bottom:20px}.rb-referrer-program h1{font-size:25px;font-weight:700}.rb-referrer-program h2{font-size:16px;font-weight:600;margin-bottom:12px}.rb-referrer-program .rb-muted{font-size:13px;color:#81748e;line-height:1.7;margin:8px 0}.rb-referrer-program .rb-link{display:inline-block;color:#8925cc;background:#f7edff;padding:10px 15px;border-radius:10px;font-size:13px;font-weight:600;margin-top:14px}.rb-referrer-program .rb-stats{display:flex;gap:16px;flex-wrap:wrap}.rb-referrer-program .rb-stat{padding:16px 20px;border:1px solid #eee4f6;border-radius:13px;flex:1;min-width:170px}.rb-referrer-program strong.value{font-size:25px;display:block;margin:8px 0}.rb-referrer-program table{width:100%;font-size:13px}.rb-referrer-program th{text-align:left;color:#93859e;font-size:11px;padding:12px}.rb-referrer-program td{padding:13px 12px;border-top:1px solid #f0eaf6}.rb-referrer-program .rb-table{overflow:auto}.rb-referrer-program code{overflow-wrap:anywhere;white-space:normal}
</style>
<div class="rb-referrer-program">
    @if(!$program)
    <div class="rb-panel"><h1>Your referral programs</h1><p class="rb-muted">Your dashboard, referrals, rewards, and conversations will appear here once you’re enrolled in a program.</p><a class="rb-link" href="{{ route('reseller.programs.index', $tenant->id) }}">Explore referral programs →</a></div>
    @else
    <div class="rb-panel">
        <p class="rb-muted">{{ $page }} · {{ ucfirst($mode) }} program</p><h1>{{ $program->name }}</h1>
        <p class="rb-muted">{{ $mode === 'automated' ? 'Your referred purchases and rewards are recorded through the connected website.' : 'Follow the progress of the referrals assigned to you in this program.' }}</p>
        @if($membership?->referral_code && $mode === 'automated')<p class="rb-muted">Your referral code: <code>{{ $membership->referral_code }}</code></p>@endif
        <a class="rb-link" href="{{ route('reseller.messages', ['tenantId'=>$tenant->id,'program_id'=>$program->id]) }}">Message company admins →</a>
        <a class="rb-link" href="{{ route('reseller.programs.show', [$tenant->id,$program->id]) }}">Program details →</a>
    </div>
    @if($page !== 'My Referrals' && $page !== 'Activity')
    <div class="rb-panel"><h2>{{ $mode === 'automated' ? 'Your recorded rewards' : 'Your referral progress' }}</h2><div class="rb-stats">
        @if($mode === 'automated')
            @forelse($totals as $total)<div class="rb-stat"><span>{{ $total->currency }} rewards</span><strong class="value">{{ number_format($total->reward_minor / 100, 2) }}</strong><p class="rb-muted">{{ $total->payments }} recorded payments · after refund reversals, not confirmed payouts.</p></div>@empty<p class="rb-muted">No referred payments have been recorded for you in this program yet.</p>@endforelse
        @else
            @forelse($stages as $stage)<div class="rb-stat"><span>{{ ucfirst(str_replace('_',' ', $stage->stage ?? 'Unassigned')) }}</span><strong class="value">{{ $stage->total }}</strong></div>@empty<p class="rb-muted">No referrals assigned to you in this program yet.</p>@endforelse
        @endif
    </div></div>
    <div class="rb-panel"><h2>Reward mechanics</h2>
        @forelse($offers as $offer)
            @php $version = $offer->currentVersion; @endphp
            @continue(!$version || $version->status !== 'published' || $version->tenant_id !== $tenant->id || $version->program_id !== $program->id)
            <p><strong>{{ $offer->name }} · {{ $version->reward_model === 'percentage' ? (float)$version->percentage_rate.'%' : ($version->reward_model === 'fixed' ? $version->currency.' '.number_format($version->fixed_amount,2) : 'Custom reward') }}</strong></p>
            <p class="rb-muted">Qualifying event: {{ ucfirst(str_replace('_',' ', $version->qualifying_event ?? 'Not specified')) }}.
            @if(isset($version->reward_rules['hold_days'])) {{ $version->reward_rules['hold_days'] }}-day hold. @endif
            @if(($version->reward_rules['scope'] ?? null) === 'recurring') Eligible payments for {{ $version->reward_rules['duration_months'] ?? 'the configured number of' }} months from the first payment.
            @elseif(($version->reward_rules['scope'] ?? null) === 'first_payment') First eligible payment only. @endif
            </p>
        @empty<p class="rb-muted">No active reward offer published yet.</p>@endforelse
        <p class="rb-muted">Recorded rewards retain the offer version used at the time. Manual program rewards are not estimated using a fixed commission split.</p>
    </div>
    @endif
    @if($page !== 'Rewards')
    <div class="rb-panel"><h2>{{ $mode === 'automated' ? 'Your purchase activity' : 'Your referrals' }}</h2><div class="rb-table"><table><thead><tr><th>{{ $mode === 'automated' ? 'Event' : 'Referral' }}</th><th>{{ $mode === 'automated' ? 'Recorded reward' : 'Stage' }}</th><th>Date</th></tr></thead><tbody>
    @forelse($records as $record)<tr><td>{{ $mode === 'automated' ? ucfirst($record->type) : $record->name }}</td><td>{{ $mode === 'automated' ? $record->currency.' '.number_format($record->reward_minor/100,2) : ucfirst(str_replace('_',' ', $record->stage ?? 'Unassigned')) }}</td><td>{{ \Carbon\Carbon::parse($mode === 'automated' ? $record->occurred_at : $record->created_at)->format('M j, Y') }}</td></tr>@empty<tr><td colspan="3">No activity recorded for you in this program yet.</td></tr>@endforelse
    </tbody></table></div>{{ $records?->links() }}</div>
    @endif
    @endif
</div>
@endsection
