<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Connect your referrer profile — Referral Bunny</title>@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body class="min-h-screen flex items-center justify-center p-5 bg-purple-50">
<main class="bg-white border border-purple-100 rounded-2xl p-8 w-full max-w-md shadow-sm">
<img src="{{ asset('images/logos/referralbunny-favicon.webp') }}" alt="Referral Bunny" width="48" height="48" class="mb-4">
<h1 class="text-xl font-bold text-purple-950">Connect your referrer profile</h1>
<p class="text-sm text-gray-600 mt-3">You already have a referrer profile with this email. Connect it once to switch views on any computer while keeping your referrals and rewards.</p>
@if($canVerify)
<form method="POST" action="{{ route('portal.switch-view') }}" class="mt-5 space-y-4">@csrf
<input type="hidden" name="view" value="referrer"><input type="hidden" name="tenant_id" value="{{ $tenantId }}">
<label for="link-password" class="block text-sm font-medium">Existing referrer password</label>
<input id="link-password" type="password" name="password" autocomplete="current-password" required class="form-input w-full">
@if($linkError)<p role="alert" class="text-sm text-red-700">{{ $linkError }}</p>@endif
<button class="w-full rounded-xl bg-purple-700 text-white py-3 font-semibold">Connect and open referrer portal</button>
</form>
@else
<p role="status" class="mt-4 text-sm text-gray-600">This profile needs account assistance before it can be connected. Contact your company administrator to check its account link and access status.</p>
@endif
<a class="block mt-5 text-sm text-purple-700" href="{{ route('tenant.dashboard',$tenantId) }}">← Return to company dashboard</a>
</main></body></html>
