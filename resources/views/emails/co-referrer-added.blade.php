@extends('emails.layouts.base', ['headerLabel' => 'Co-Referrer Added'])
@section('content')
<p class="greeting">Hi {{ $resellerName }},</p>
<p class="text"><strong>{{ $primaryName }}</strong> has added you as a co-referrer on the deal <strong>{{ $dealName }}</strong>, with a commission share of <strong>{{ $percentage }}%</strong> of the referral pool.</p>
<p class="text">You can view this deal and track your commission from your Referrer Portal.</p>
<hr class="divider">
<p class="text" style="font-size:13px;color:#9CA3AF">You received this email because you were added as a co-referrer on this deal.</p>
@endsection
