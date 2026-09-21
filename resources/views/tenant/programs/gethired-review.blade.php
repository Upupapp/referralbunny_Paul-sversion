@extends('layouts.app')
@section('title', 'Review payments and refunds')
@section('nav')
@include('tenant._nav', ['tenant'=>$tenant])
@endsection
@section('content')
@php
$base=[$tenant->id,$program->id];
$reasons=[
 'DELIVERY_FAILED'=>'GetHired could not confirm delivery. Automatic retries are scheduled.',
 'RECEIVER_REJECTED'=>'Referral Bunny rejected this event. Check the connection and program settings before retrying.',
 'PAYMENT_CHANGED'=>'The payment changed after it was prepared. Ask your billing administrator to reconcile the invoice and refund records.',
 'INVOICE_AMOUNT_MISMATCH'=>'Invoice and payment totals do not match. Your billing administrator must correct the source records.',
 'INVALID_INVOICE_AMOUNT'=>'The invoice amount needs review by your billing administrator.',
 'CURRENCY_MISMATCH'=>'The invoice and payment currencies do not match.',
 'EXISTING_CUSTOMER'=>'This account existed before the referral and does not qualify.',
 'PRIOR_PAYMENT_OR_MISSING_START'=>'An earlier payment or a missing first purchase prevents attribution.',
 'REFERRAL_INELIGIBLE'=>'The referral is inactive, invalid, or a self-referral.',
 'ATTRIBUTION_EXPIRED'=>'The first payment falls outside the referral window.',
 'REFUND_AMOUNT_INVALID'=>'Refund totals exceed the eligible payment or contain an invalid amount.',
 'REFUND_PAYMENT_MISMATCH'=>'Refund currency or timing does not match the original payment.',
 'EARLIER_REFUND_HELD'=>'An earlier refund needs review before this correction can proceed.',
 'ROUNDING_ZERO'=>'Recorded successfully. This refund required no additional reward adjustment.',
];
@endphp
<style>.gh-review{color:#241d50}.gh-review header,.gh-review>form,.gh-review article,.gh-review>section{background:#fff;border:1px solid #e9e2f7;border-radius:18px;padding:24px}.gh-review a{color:#7734ff}.gh-review select{border:1px solid #e3d9f1;border-radius:10px;background:white}.gh-review .readiness{background:linear-gradient(120deg,#fff,#f7f2ff)}.gh-review .readiness h2{font-size:20px;font-weight:700}.gh-review .readiness p{margin-top:12px;line-height:1.6}.gh-review .review-action{display:inline-block;padding:10px 16px;border-radius:10px;border:1px solid #dfd2f4;margin:16px 8px 0 0}</style>
<main class="gh-review max-w-5xl mx-auto p-5 sm:p-8 space-y-6">
 <a href="{{ route('tenant.quick-program.connection',$base) }}" class="text-purple-700 underline">Back to connection</a>
 <header><h1 class="text-2xl font-bold">Review payments and refunds</h1><p class="text-slate-600">{{ $program->name }} · GetHired</p><p class="mt-2">Resolve delivery problems here. Reward amounts and eligibility cannot be overridden on this screen.</p></header>
 @if(session('review_message'))<p role="status" class="rounded-xl bg-green-50 p-4">{{ session('review_message') }}</p>@endif
 @if($errors->any())<div role="alert" class="rounded-xl bg-red-50 p-4">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
 <form method="GET" class="flex flex-wrap items-end gap-4 bg-white rounded-xl border p-4">
  <label>Event type<select name="kind" class="block border rounded-lg p-2"><option value="payment" @selected($filters['kind']==='payment')>Payments</option><option value="refund" @selected($filters['kind']==='refund')>Refunds</option></select></label>
  <label>Status<select name="status" class="block border rounded-lg p-2">@foreach(['held'=>'Needs review','failed'=>'Retry scheduled','pending'=>'Queued','delivered'=>'Delivered'] as $value=>$label)<option value="{{ $value }}" @selected($filters['status']===$value)>{{ $label }}</option>@endforeach</select></label>
  <button class="bg-purple-700 text-white rounded-lg px-4 py-2">Show events</button>
 </form>
 @if($unavailable)
 <section class="readiness" role="status">
 @if(($readiness['account']??'')==='connected' && in_array($readiness['payments']??'',['not_available','authorization_required']))
 <h2>Payment tracking {{ ($readiness['payments']??'')==='authorization_required'?'needs authorization':'is not enabled yet' }}</h2>
 <p>GetHired · Connected &nbsp; | &nbsp; Signups · {{ ($readiness['signups']??'')==='ready'?'Ready':'Not ready' }} &nbsp; | &nbsp; Payments & refunds · Setup required</p>
 <p>Your GetHired connection is active. Payments and refunds require separate billing access before their review list is available.</p>
 <p><strong>Next step:</strong> The GetHired platform owner needs to verify payment and refund records, authorize payment access, and enable delivery. Reconnecting signup tracking alone will not enable payments.</p>
 <p>Signup tracking does not confirm a payment or generate a referral reward. No payment or reward records were changed.</p>
 @elseif(in_array($readiness['account']??'',['not_connected','disconnected','reconnect_required']))
 <h2>Reconnect GetHired to continue</h2><p>The platform connection needs attention before payments and refunds can be reviewed. Open connection details to reconnect the authorized platform account.</p>
 @else
 <h2>The review list is unavailable right now</h2><p>GetHired could not provide the review list. Try again shortly or check connection details. No records were changed.</p>
 @endif
 <a class="review-action" href="{{ route('tenant.quick-program.connection',$base) }}">Open connection details →</a><a class="review-action" href="{{ request()->fullUrl() }}">Check again</a>
 </section>
 @else
 @forelse(($result['items'] ?? []) as $item)
 <article class="bg-white border rounded-xl p-5 space-y-3">
  <h2 class="font-semibold">{{ $filters['kind']==='payment'?'Payment':'Refund' }} · {{ $item['invoice_id'] ?: 'Invoice reference unavailable' }}</h2>
  <p>{{ $reasons[$item['reason'] ?? ''] ?? ($filters['status']==='delivered'?'Delivery confirmed.':($filters['status']==='pending'?'Waiting for the worker, an earlier event, or completed refund evidence.':'Your billing administrator needs to review this event.')) }}</p>
  <dl class="flex flex-wrap gap-6 text-sm text-slate-600">
   <div><dt>Eligible amount</dt><dd>{{ isset($item['amount_minor']) ? ($item['currency'].' '.number_format((float)$item['amount_minor']/100,2)) : 'Not established' }}</dd></div>
   <div><dt>Delivery attempts</dt><dd>{{ $item['attempts'] }}</dd></div>
   <div><dt>Next retry (UTC)</dt><dd>{{ empty($item['next_attempt_at']) ? '—' : \Carbon\Carbon::parse($item['next_attempt_at'])->utc()->format('Y-m-d H:i') }}</dd></div>
  </dl>
  @if($item['canRetry'] ?? false)
  <details class="border-t pt-3"><summary class="cursor-pointer font-semibold text-purple-700">Queue a retry</summary>
   <form method="POST" action="{{ route('tenant.quick-program.gethired.retry',$base) }}" class="mt-3 space-y-3">@csrf
    <input type="hidden" name="kind" value="{{ $filters['kind'] }}"><input type="hidden" name="id" value="{{ $item['id'] }}">
    <label class="block">What did you check or correct?<textarea name="note" required minlength="5" maxlength="500" class="block w-full border rounded-lg p-2" placeholder="Describe the connection or program setting you checked. Do not include passwords or payment details."></textarea></label>
    <label class="block"><input type="checkbox" name="confirmed" value="1" required> I understand this queues the existing event and does not approve a payout.</label>
    <button class="rounded-lg bg-purple-700 text-white px-4 py-2">Queue retry</button>
   </form>
  </details>
  @elseif($filters['status']==='held')<p class="text-sm text-slate-600">Contact your billing administrator with event reference <code class="break-all">{{ $item['id'] }}</code>. A retry cannot bypass this eligibility or billing check.</p>@endif
 </article>
 @empty
 <p class="bg-white rounded-xl border p-6">No {{ $filters['kind']==='payment'?'payments':'refunds' }} match this status.</p>
 @endforelse
 <nav aria-label="Review pages" class="flex gap-5">
 @if($filters['page']>1)<a class="underline" href="{{ route('tenant.quick-program.gethired.review',array_merge($base,$filters,['page'=>$filters['page']-1])) }}">Previous</a>@endif
 <span>Page {{ $filters['page'] }}</span>
 @if($result['hasMore'] ?? false)<a class="underline" href="{{ route('tenant.quick-program.gethired.review',array_merge($base,$filters,['page'=>$filters['page']+1])) }}">Next</a>@endif
 </nav>
 <section class="bg-white border rounded-xl p-5 space-y-3"><h2 class="font-semibold">Recent review activity</h2>
 @forelse(($result['history'] ?? []) as $action)<div class="border-t pt-3 text-sm"><p>{{ \Carbon\Carbon::parse($action['created_at'])->utc()->format('Y-m-d H:i') }} UTC · Admin {{ $action['actor_id'] }} queued a {{ $action['event_kind'] }} retry.</p><p>{{ $action['note'] }}</p><p class="text-slate-500 break-all">Event {{ $action['delivery_id'] }}</p></div>@empty<p class="text-slate-600">No manual retries recorded.</p>@endforelse
 </section>
 @endif
</main>
@endsection
