@extends('layouts.app')
@section('title', 'Connect your website')
@section('nav')
    @include('tenant._nav', ['tenant' => $tenant])
@endsection
@section('content')
<main class="max-w-4xl mx-auto p-5 sm:p-8 space-y-6">
    <div class="rounded-2xl bg-white border border-purple-100 p-6">
        <p class="text-xs font-semibold uppercase tracking-wide text-purple-700">{{ $program->name }}</p>
        <h1 class="text-2xl font-bold mt-2">Your program is ready. Connect your platform next.</h1>
        <p class="mt-3 text-sm text-slate-600">{{ $connection->website }}</p>
        <p class="mt-3 font-semibold {{ $connection->status === 'connected' ? 'text-green-700' : 'text-amber-700' }}">{{ ['connected' => 'Payment tracking connected', 'tested' => 'Test received · Waiting for a live payment', 'not_connected' => 'Payment tracking not connected'][$connection->status] }}</p>
        <p class="text-sm text-slate-500 mt-2">Connect your platform below. Account connection and payment tracking are shown separately.</p>
        <a href="{{ route('tenant.programs.workspace', [$tenant->id, $program->id]) }}" class="inline-block mt-4 text-purple-700 underline">Open program workspace</a>
    </div>
    <section class="bg-white rounded-2xl border border-purple-100 p-6 space-y-4">
        <h2 class="text-xl font-bold">Connect my platform</h2>
        <p class="text-sm text-slate-600">Choose your platform, sign in, and approve the connection. No code or API keys to copy.</p>
        @if(session('platform_message'))<p role="status" class="text-purple-700">{{ session('platform_message') }}</p>@endif
        @if($errors->has('platform'))<p role="alert" class="text-red-700">{{ $errors->first('platform') }}</p>@endif
        <div class="rounded-xl border p-5 space-y-4" x-data="gethiredConnection(@js(['enabled'=>(bool)config('services.gethired.enabled'),'previouslyConnected'=>(bool)$connection->platform_connected_at,'statusUrl'=>route('tenant.quick-program.gethired.status',[$tenant->id,$program->id])]))">
            <h3 class="font-bold text-lg">GetHired Online</h3>
            <dl class="grid sm:grid-cols-3 gap-3" aria-live="polite">
                <div class="rounded-lg bg-slate-50 p-3"><dt class="text-xs text-slate-500">Account connection</dt><dd class="mt-1 text-sm font-semibold" x-text="accountLabel">Checking connection…</dd></div>
                <div class="rounded-lg bg-slate-50 p-3"><dt class="text-xs text-slate-500">Signup tracking</dt><dd class="mt-1 text-sm font-semibold" x-text="signupLabel">Not verified</dd></div>
                <div class="rounded-lg bg-slate-50 p-3"><dt class="text-xs text-slate-500">Payment tracking</dt><dd class="mt-1 text-sm font-semibold" x-text="paymentLabel">Not verified</dd></div>
            </dl>
            <p class="text-sm text-slate-600">Sign in as a GetHired platform administrator to approve access. Reconnecting an active account preserves saved referral details. Payment tracking requires separate approval when enabled. Refund synchronization is not available yet.</p>
            <p x-show="error" x-text="error" x-cloak role="alert" class="text-sm text-red-700"></p>
            @if(config('services.gethired.enabled'))
                <a class="text-purple-700 underline font-semibold" href="{{ route('tenant.quick-program.gethired.review',[$tenant->id,$program->id]) }}">Review payments and refunds</a>
                <div class="flex flex-wrap gap-3">
                    <form method="POST" action="{{ route('tenant.quick-program.gethired.start',[$tenant->id,$program->id]) }}" @submit="submitting=true">@csrf<button :disabled="submitting" class="bg-purple-700 text-white rounded-lg px-5 py-3 font-semibold disabled:opacity-50" x-text="submitting ? 'Opening GetHired…' : connectLabel">Connect GetHired</button></form>
                    <button type="button" @click="check()" :disabled="checking" class="border rounded-lg px-4 py-2 text-sm" x-text="checking ? 'Checking…' : 'Check connection'">Check connection</button>
                </div>
                <button type="button" x-show="account==='connected' || account==='reconnect_required' || {{ $connection->platform === 'gethired' ? 'true' : 'false' }}" @click="disconnecting=true" class="underline text-sm text-slate-600">Disconnect GetHired</button>
                <div x-show="disconnecting" x-cloak class="rounded-lg border border-amber-200 bg-amber-50 p-4" role="group" aria-label="Confirm disconnection">
                    <p class="text-sm mb-3">Disconnecting stops new referral capture and signup attribution, and invalidates unclaimed referral details. Existing records are retained.</p>
                    <div class="flex gap-3"><form method="POST" action="{{ route('tenant.quick-program.gethired.disconnect',[$tenant->id,$program->id]) }}" @submit="submitting=true">@csrf<button :disabled="submitting" class="rounded-lg bg-slate-800 px-4 py-2 text-white text-sm">Confirm disconnect</button></form><button type="button" @click="disconnecting=false" class="text-sm underline">Keep connected</button></div>
                </div>
            @else
                <p class="text-sm text-slate-600">GetHired connection is being prepared. It will appear here when available.</p>
            @endif
        </div>
    </section>
    <details class="rounded-2xl bg-white p-5"><summary class="text-sm text-slate-500 cursor-pointer">Developer setup for other websites</summary>
    <div class="space-y-6 mt-4">
    <section class="bg-white rounded-2xl border border-purple-100 p-6 space-y-4" x-data="websiteTracking(@js(['statusUrl' => route('tenant.quick-program.installation', [$tenant->id, $program->id]), 'originsUrl' => route('tenant.quick-program.origins', [$tenant->id, $program->id])]))">
        <h2 class="text-lg font-bold">1. Add Referral Bunny to your website</h2>
        <p class="text-sm text-slate-600">Paste this snippet before the closing &lt;/head&gt; tag on your website, or in your website builder’s custom code settings. Include it on every page where visitors arrive from referral links.</p>
        <textarea x-ref="snippet" readonly rows="4" aria-label="Website tracking snippet" class="w-full rounded-lg border-slate-300 font-mono text-xs">&lt;script defer src="{{ url('/referral-bunny.js') }}" data-connection="{{ $connection->id }}" data-program="{{ $program->id }}"&gt;&lt;/script&gt;</textarea>
        <div class="flex flex-wrap gap-3">
            <button type="button" @click="copy()" class="rounded-lg bg-purple-700 text-white px-4 py-2 text-sm font-semibold">Copy snippet</button>
            <a href="{{ $connection->website }}" target="_blank" rel="noopener noreferrer" class="rounded-lg border px-4 py-2 text-sm">Open website ↗</a>
            <button type="button" @click="check()" :disabled="busy" class="rounded-lg border px-4 py-2 text-sm" x-text="busy ? 'Checking…' : 'Check installation'">Check installation</button>
        </div>
        <div class="rounded-xl bg-slate-50 p-4" aria-live="polite">
            <p class="font-semibold" :class="installed ? 'text-green-700' : 'text-amber-700'" x-text="installed ? '✓ Website snippet detected' : 'Waiting for your first website visit'">Checking installation…</p>
            <p x-show="installed" class="text-sm text-slate-600 mt-1" x-text="origin + ' · Last seen ' + (lastSeen ? new Date(lastSeen).toLocaleString() : '')"></p>
            <p class="text-sm text-slate-500 mt-2">After adding the snippet, open your website to verify it. Detection checks automatically for two minutes. Payment tracking is connected separately below.</p>
        </div>
        <p x-show="message" x-text="message" class="text-sm text-purple-700" role="status"></p>
        <p x-show="error" x-text="error" class="text-sm text-red-700" role="alert"></p>
        <details>
            <summary class="cursor-pointer text-sm font-semibold text-purple-700">Website and app domains</summary>
            <p class="text-sm text-slate-600 mt-3">Allow up to five HTTPS website addresses, one per line. Add your app address if you also install the snippet there. Referral storage is separate for each domain; your app must carry attribution across signup or domain changes.</p>
            <textarea x-model="origins" rows="3" aria-label="Allowed website origins" class="w-full mt-3 rounded-lg border-slate-300 text-sm"></textarea>
            <button type="button" @click="saveOrigins()" class="mt-2 rounded-lg border px-4 py-2 text-sm">Save websites</button>
        </details>
        <details>
            <summary class="cursor-pointer text-sm font-semibold text-purple-700">For your developer: read referral details</summary>
            <p class="text-sm text-slate-600 mt-3">After the script loads, await <code>ReferralBunny.programs['{{ $program->id }}'].ready</code>, then call <code>ReferralBunny.getReferral('{{ $program->id }}')</code>. Save the returned membership_id and referred_at with the customer at signup. Browser attribution is a hint; validate eligibility on your backend.</p>
            <p class="text-sm text-slate-600 mt-2">The snippet stores referral details in first-party local storage. For consent-controlled loading, add <code>data-consent="required"</code> and call <code>ReferralBunny.programs['{{ $program->id }}'].consent()</code> after consent is granted.</p>
        </details>
    </section>
    <section class="bg-white rounded-2xl p-6 space-y-4">
        <h2 class="text-lg font-bold">2. Connect payments from your application</h2>
        <ol class="list-decimal pl-5 space-y-3 text-sm text-slate-700">
            <li>Invite and activate referrers in your program’s Members tab. Use their program membership ID as the referral identifier.</li>
            <li>Add <code>?rb_ref=MEMBERSHIP_ID&amp;rb_program={{ $program->id }}</code> to links to your website. The snippet remembers valid referrals on that domain for the program attribution window. Your app must save the identifier and arrival time with the customer during signup.</li>
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
    </div></details>
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
