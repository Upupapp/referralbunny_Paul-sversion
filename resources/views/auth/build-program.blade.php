<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @include('partials.google-analytics')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sign Up — ReferralBunny.ai</title>
    <link rel="icon" type="image/webp" href="/images/logos/referralbunny-favicon.webp">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0; padding: 0; min-height: 100vh; font-family: 'Inter', sans-serif;
            background: linear-gradient(145deg, #0f0b1f 0%, #1e1347 50%, #0a0620 100%);
            background-attachment: fixed;
        }
        .wi { width:100%;border:1.5px solid #e5e7eb;border-radius:12px;padding:10px 14px;font-size:14px;outline:none;transition:all .15s;background:#fff;color:#1e1b4b; }
        .wi:focus { border-color:#7B61FF;box-shadow:0 0 0 3px rgba(123,97,255,.12); }
        .wi::placeholder { color:#9ca3af; }
        .bp { background:linear-gradient(135deg,#7B61FF,#5b4cdb);color:white;border:none;border-radius:12px;padding:12px 24px;font-size:14px;font-weight:600;cursor:pointer;transition:all .15s;display:inline-flex;align-items:center;gap:8px; }
        .bp:hover { opacity:.9;box-shadow:0 4px 16px rgba(123,97,255,.4); }
        .bp:disabled { opacity:.5;cursor:not-allowed; }
    </style>
</head>
<body>
<main class="min-h-screen py-8 px-4 flex flex-col justify-center">
    <a href="{{ route('public.home') }}" class="mb-6 mx-auto"><img src="/images/logos/referralbunny-horizontal-logo.webp" alt="Referral Bunny" class="h-9 object-contain"></a>
    <div class="w-full max-w-lg mx-auto bg-white rounded-2xl shadow-2xl p-6 sm:p-8">
        <h1 class="text-2xl font-bold text-[#1E1B4B]">Create your company account</h1>
        <p class="text-gray-600 text-sm mt-2 mb-6">Sign up and go straight to your dashboard. Customize your referral program anytime.</p>
        @if ($errors->any())
            <div role="alert" class="mb-5 rounded-xl bg-red-50 p-4 text-sm text-red-700">
                <ul class="list-disc pl-4">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        <form method="POST" action="{{ route('tenant.create.post') }}" class="space-y-4" x-data="{ submitting: false }" @submit="submitting = true">
            @csrf
            <div>
                <label for="workspace_name" class="block text-xs font-semibold text-gray-600 mb-1.5">Company name</label>
                <input id="workspace_name" name="workspace_name" value="{{ old('workspace_name') }}" required maxlength="255" autocomplete="organization" class="wi" placeholder="Acme Corp">
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="first_name" class="block text-xs font-semibold text-gray-600 mb-1.5">First name</label>
                    <input id="first_name" name="first_name" value="{{ old('first_name') }}" required maxlength="100" autocomplete="given-name" class="wi">
                </div>
                <div>
                    <label for="last_name" class="block text-xs font-semibold text-gray-600 mb-1.5">Last name</label>
                    <input id="last_name" name="last_name" value="{{ old('last_name') }}" required maxlength="100" autocomplete="family-name" class="wi">
                </div>
            </div>
            <div>
                <label for="email" class="block text-xs font-semibold text-gray-600 mb-1.5">Work email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email" class="wi" placeholder="you@company.com">
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="password" class="block text-xs font-semibold text-gray-600 mb-1.5">Password</label>
                    <input id="password" name="password" type="password" required minlength="8" autocomplete="new-password" class="wi" aria-describedby="password-hint">
                    <p id="password-hint" class="text-xs text-gray-500 mt-1">At least 8 characters.</p>
                </div>
                <div>
                    <label for="password_confirmation" class="block text-xs font-semibold text-gray-600 mb-1.5">Confirm password</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" required minlength="8" autocomplete="new-password" class="wi">
                </div>
            </div>
            <label class="flex items-start gap-2.5 cursor-pointer">
                <input name="terms" type="checkbox" value="1" required @checked(old('terms')) class="mt-0.5 w-4 h-4 accent-purple-600 flex-shrink-0">
                <span class="text-xs text-gray-600 leading-relaxed">I agree to the <a href="{{ route('terms') }}" target="_blank" rel="noopener noreferrer" class="text-[#7B61FF] underline">Terms of Service</a> and <a href="{{ route('privacy') }}" target="_blank" rel="noopener noreferrer" class="text-[#7B61FF] underline">Privacy Policy</a>.</span>
            </label>
            <button type="submit" class="bp w-full justify-center" :disabled="submitting" :aria-busy="submitting" x-text="submitting ? 'Creating account…' : 'Create account'">Create account</button>
        </form>
    </div>
    <p class="text-center text-white/80 text-sm mt-6">Already have an account? <a href="{{ route('login') }}" class="text-white underline">Sign in</a></p>
</main>
</body>
</html>
