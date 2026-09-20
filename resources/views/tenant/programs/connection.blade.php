@extends('layouts.app')
@section('title', 'Connect your website')
@section('nav')
    @include('tenant._nav', ['tenant' => $tenant])
@endsection
@section('content')
<main class="max-w-4xl mx-auto p-5 sm:p-8 space-y-6">
    <div class="rounded-2xl bg-white border border-purple-100 p-6">
        <p class="text-xs font-semibold uppercase tracking-wide text-purple-700">{{ $program->name }}</p>
        <h1 class="text-2xl font-bold mt-2">Your program is ready. Connect your website next.</h1>
        <p class="mt-3 text-sm text-slate-600">{{ $connection->website }}</p>
        <p class="mt-3 font-semibold {{ $connection->status === 'connected' ? 'text-green-700' : 'text-amber-700' }}">{{ ['connected' => 'Tracking connected', 'tested' => 'Test received · Waiting for a live payment', 'not_connected' => 'Tracking not connected'][$connection->status] }}</p>
        <p class="text-sm text-slate-500 mt-2">Website analysis does not install tracking. GetHired or your other application must send signed payment events from its backend.</p>
        <a href="{{ route('tenant.programs.workspace', [$tenant->id, $program->id]) }}" class="inline-block mt-4 text-purple-700 underline">Open program workspace</a>
    </div>
    <section class="bg-white rounded-2xl p-6 space-y-4">
        <h2 class="text-lg font-bold">Connect GetHired or another application</h2>
        <ol class="list-decimal pl-5 space-y-3 text-sm text-slate-700">
            <li>Invite and activate referrers in your program’s Members tab. Use their program membership ID as the referral identifier.</li>
            <li>Add <code>?rb_ref=MEMBERSHIP_ID&amp;rb_program={{ $program->id }}</code> to links to your website. Your app must preserve this identifier and its arrival time through customer signup, for up to 30 days.</li>
            <li>From the trusted payment backend, send confirmed payments and refunds to the endpoint below. Exclude tax and use the amount actually collected after discounts. Never put the signing key in browser code.</li>
            <li>Send a test event, then a real payment. Refresh this page to check connection status and recorded rewards.</li>
        </ol>
        <label class="block text-sm font-semibold">Event endpoint<input readonly value="{{ url('/api/program-connections/'.$connection->id.'/events') }}" class="block w-full mt-2 rounded-lg border-slate-300 text-xs" onclick="this.select()"></label>
        <details><summary class="cursor-pointer text-sm text-purple-700 font-semibold">Show signing key for your backend developer</summary><input type="password" readonly value="{{ $connection->secret }}" autocomplete="off" class="w-full mt-3 rounded-lg border-slate-300 font-mono text-xs" x-data="{ visible: false }" :type="visible ? 'text' : 'password'" @click="visible = true; $el.select()" aria-label="Backend signing key"></details>
        <details><summary class="cursor-pointer text-sm text-purple-700 font-semibold">Developer instructions and test request</summary>
            <div class="mt-4 text-sm space-y-3"><p>Set <code>RB_ENDPOINT</code> and <code>RB_SECRET</code> on your application server. Sign <code>timestamp + '.' + exact JSON body</code> with HMAC-SHA256. Timestamps expire after five minutes.</p>
<pre class="overflow-auto rounded-xl bg-slate-900 text-slate-100 p-4 text-xs">const body = JSON.stringify({ event_id: 'connection-test-1', type: 'test' });
const timestamp = String(Math.floor(Date.now() / 1000));
const signature = createHmac('sha256', process.env.RB_SECRET)
  .update(timestamp + '.' + body).digest('hex');
await fetch(process.env.RB_ENDPOINT, {
  method: 'POST',
  headers: { 'Content-Type': 'application/json', 'Accept': 'application/json',
    'X-RB-Timestamp': timestamp, 'X-RB-Signature': signature }, body
});
// Import createHmac from node:crypto in your backend.</pre>
                <p>For a payment, send this payload instead:</p>
<pre class="overflow-auto rounded-xl bg-slate-100 p-4 text-xs">{
  "event_id": "payment-provider-event-unique-id",
  "type": "payment",
  "customer_id": "your-customer-id",
  "invoice_id": "your-invoice-id",
  "membership_id": "rb_ref-from-signup",
  "currency": "{{ $program->default_currency }}",
  "amount_minor": 100000,
  "occurred_at": "2026-09-20T10:00:00Z",
  "referred_at": "2026-09-19T10:00:00Z",
  "first_payment": true,
  "self_referral": false
}</pre>
                <p>Use actual UTC times. Set <code>first_payment</code> to false for renewals. Your backend must verify first purchase and self-referral status against its customer records; browser claims are not trusted.</p>
                <p>For refunds, send a unique <code>event_id</code>, <code>type: "refund"</code>, the original customer and invoice IDs, currency, refunded <code>amount_minor</code>, and occurrence time. Send payments before refunds. Retry failed requests with the same event ID and exact body, but a fresh signature.</p>
                <p>Responses: 201 recorded; 200 duplicate or test; 401 signature invalid; 409 ordering or conflicting event; 422 invalid reward data. Rewards are recorded for manual review, not automatically transferred or added to the legacy deal payout ledger.</p>
            </div>
        </details>
    </section>
    <section class="bg-white rounded-2xl p-6">
        <h2 class="text-lg font-bold mb-2">Recent tracked rewards</h2>
        <p class="text-sm text-slate-500 mb-4">Review eligibility after the hold period and arrange payment separately. Refund reversals reduce the reward owed. These entries do not initiate payouts.</p>
        <div class="overflow-x-auto"><table class="w-full text-sm text-left"><thead><tr><th class="p-2">Invoice</th><th class="p-2">Event</th><th class="p-2">Reward</th><th class="p-2">Status</th><th class="p-2">Review after</th></tr></thead><tbody>
        @forelse($events as $event)
            <tr class="border-t"><td class="p-2">{{ $event->invoice_id }}</td><td class="p-2">{{ $event->type }}</td><td class="p-2">{{ $event->currency }} {{ number_format($event->reward_minor / 100, 2) }}</td><td class="p-2">{{ str_replace('_', ' ', $event->status) }}</td><td class="p-2">{{ $event->available_at ?? '—' }}</td></tr>
        @empty
            <tr><td colspan="5" class="p-4 text-slate-500">No payment events yet. Send a signed test request to check your connection.</td></tr>
        @endforelse
        </tbody></table></div>
    </section>
</main>
@endsection
