@extends('emails.layouts.base', ['headerLabel' => 'Deal Expired'])
@section('content')
<p class="greeting">Deal expired</p>
<p class="text">Hi <strong>{{ $resellerName }}</strong>, your deal <strong>{{ $dealName }}</strong> has expired in the <em>{{ ucfirst(str_replace('_', ' ', $stage)) }}</em> stage.</p>
<div class="highlight-box highlight-red">
    <div class="highlight-title" style="color:#DC2626">What this means</div>
    <div class="highlight-text">
        This deal has reached the stage time limit and is now available for other referrers to claim.
        It will no longer appear as your active deal unless re-claimed.
    </div>
</div>
<p class="text">Don't let this slow you down — there are still available municipalities waiting to be claimed. Head to your dashboard to browse opportunities.</p>
<div class="cta-wrap">
    <a href="{{ $dashboardUrl }}" class="cta cta-teal">Browse Available Deals →</a>
</div>
<hr class="divider">
<p class="text" style="font-size:13px;color:#9CA3AF">Contact your program administrator if you believe this is an error.</p>
@endsection
