@extends('emails.layouts.base', ['headerLabel' => 'Co-Referrer Removed'])
@section('content')
<p class="greeting">Hi {{ $resellerName }},</p>
<p class="text"><strong>{{ $actorName }}</strong> has removed you as a co-referrer on the deal <strong>{{ $dealName }}</strong>.</p>
<p class="text">If you have any questions, please contact your program administrator.</p>
<hr class="divider">
<p class="text" style="font-size:13px;color:#9CA3AF">You received this email because you were previously listed as a co-referrer on this deal.</p>
@endsection
