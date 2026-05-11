<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Join as a Referrer — ReferralBunny.ai</title>
    <link rel="icon" type="image/webp" href="/images/logos/referralbunny-favicon.webp">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0; padding: 0; min-height: 100vh;
            font-family: 'Inter', sans-serif;
            background: linear-gradient(145deg, #052E2A 0%, #0F6B5F 55%, #042320 100%);
            display: flex; align-items: center; justify-content: center;
        }

        /* ── Card ───────────────────────────────────────────── */
        .setup-page { width: 100%; padding: 1.5rem; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .setup-card { width: 100%; max-width: 900px; display: flex; border-radius: 24px; overflow: hidden; box-shadow: 0 32px 80px rgba(0,0,0,.45); min-height: 580px; }
        .card-left  { flex: 1; background: #ffffff; display: flex; flex-direction: column; justify-content: center; padding: 3rem; }
        .card-right {
            width: 380px; flex-shrink: 0;
            background: linear-gradient(145deg, #073D37 0%, #0F766E 60%, #052E2A 100%);
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            padding: 2.5rem; text-align: center; position: relative; overflow: hidden;
        }
        @media (max-width: 767px) { .card-right { display: none !important; } .card-left { padding: 2.5rem 1.75rem; } }

        /* ── Form ───────────────────────────────────────────── */
        .form-wrap { max-width: 340px; }
        .field-label { display: block; font-size: .8125rem; font-weight: 500; color: #374151; margin-bottom: .375rem; }
        .field-input { width: 100%; border: 1px solid #e5e7eb; background: #f9fafb; border-radius: 12px; padding: .75rem 1rem; font-size: .875rem; color: #111827; outline: none; transition: border-color .15s, box-shadow .15s, background .15s; font-family: 'Inter', sans-serif; }
        .field-input:focus { border-color: #2DD4BF; box-shadow: 0 0 0 3px rgba(13,148,136,.15); background: #fff; }
        .field-input::placeholder { color: #9ca3af; }
        .btn-primary { width: 100%; background: #0D9488; color: #fff; border: none; border-radius: 12px; padding: .8125rem 1rem; font-size: .875rem; font-weight: 600; cursor: pointer; transition: background .15s; font-family: 'Inter', sans-serif; margin-top: .5rem; }
        .btn-primary:hover { background: #0F766E; }
        .pw-wrap { position: relative; }
        .pw-toggle { position: absolute; right: .875rem; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: #9ca3af; padding: 0; display: flex; align-items: center; }
        .pw-toggle:hover { color: #6b7280; }
        .error-box { display: flex; align-items: flex-start; gap: .75rem; background: #fef2f2; border: 1px solid #fecaca; border-radius: 12px; padding: .75rem 1rem; margin-bottom: 1.25rem; }
        .error-box p { margin: 0; font-size: .875rem; color: #dc2626; }

        /* ── Steps indicator ─────────────────────────────────── */
        .steps { display: flex; align-items: center; gap: .5rem; margin-bottom: 1.75rem; }
        .step { width: 24px; height: 4px; border-radius: 9999px; background: #E5E7EB; }
        .step.done { background: #0D9488; }
        .step.active { background: #0D9488; width: 32px; }
    </style>
</head>
<body>

<div class="setup-page">
    <div class="setup-card">

        {{-- LEFT — form --}}
        <div class="card-left">
            <div style="margin-bottom:1.5rem">
                <x-rb-logo variant="horizontal" size="sm" :priority="true" :decorative="true" />
            </div>

            {{-- Steps --}}
            <div class="steps">
                <div class="step done"></div>
                <div class="step active"></div>
                <div class="step"></div>
            </div>

            <h1 style="margin:0 0 .25rem;font-size:1.5rem;font-weight:700;color:#111827">Create your account</h1>
            <p style="margin:0 0 .25rem;font-size:.875rem;color:#6b7280">Welcome, <strong style="color:#0D9488">{{ $reseller->name }}</strong>! Set your password to get started.</p>
            <p style="margin:0 0 2rem;font-size:.6875rem;font-weight:600;color:#9ca3af;letter-spacing:.1em;text-transform:uppercase">Referrer Portal</p>

            @if ($errors->any())
            <div class="error-box form-wrap">
                <x-r-bunny variant="warning" size="xs" :decorative="true" style="flex-shrink:0;margin-top:2px" />
                <p>{{ $errors->first() }}</p>
            </div>
            @endif

            <form method="POST" action="{{ route('reseller.setup.post') }}" x-data="{ show1: false, show2: false }" class="form-wrap">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">

                <div style="margin-bottom:1rem">
                    <label class="field-label">Create Password</label>
                    <div class="pw-wrap">
                        <input name="password" :type="show1 ? 'text' : 'password'" required minlength="8"
                               class="field-input" style="padding-right:2.75rem" placeholder="At least 8 characters" autofocus>
                        <button type="button" class="pw-toggle" @click="show1 = !show1" tabindex="-1">
                            <svg x-show="!show1" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            <svg x-show="show1"  width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 4.411m0 0L21 21"/></svg>
                        </button>
                    </div>
                </div>

                <div style="margin-bottom:1.5rem">
                    <label class="field-label">Confirm Password</label>
                    <div class="pw-wrap">
                        <input name="password_confirmation" :type="show2 ? 'text' : 'password'" required
                               class="field-input" style="padding-right:2.75rem" placeholder="Repeat your password">
                        <button type="button" class="pw-toggle" @click="show2 = !show2" tabindex="-1">
                            <svg x-show="!show2" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            <svg x-show="show2"  width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 4.411m0 0L21 21"/></svg>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-primary" x-data="{ sub: false }" @click="if (!$el.closest('form').checkValidity()) return; sub=true" :disabled="sub" x-text="sub ? 'Activating...' : 'Activate My Account →'">Activate My Account →</button>

                <p style="margin:.875rem 0 0;font-size:.75rem;color:#9ca3af;text-align:center">
                    Already set up? <a href="{{ route('reseller.login') }}" style="color:#0D9488;text-decoration:none">Sign in</a>
                </p>
            </form>
        </div>

        {{-- RIGHT — brand panel --}}
        <div class="card-right">
            <div style="position:absolute;width:260px;height:260px;border-radius:50%;top:-80px;right:-80px;opacity:.25;pointer-events:none;background:radial-gradient(circle,#0D9488,transparent 65%)"></div>
            <div style="position:absolute;width:200px;height:200px;border-radius:50%;bottom:-60px;left:-60px;opacity:.2;pointer-events:none;background:radial-gradient(circle,#14B8A6,transparent 65%)"></div>
            <div style="position:relative;z-index:1">
                <x-r-bunny variant="portal" size="lg" :decorative="true" style="display:block;margin:0 auto 1.25rem" />
                <x-rb-logo variant="white" size="xs" :priority="true" :decorative="true" style="display:block;margin:0 auto 1rem;opacity:.9" />
                <h2 style="margin:0 0 .5rem;font-size:1.125rem;font-weight:700;color:#fff;line-height:1.4">Your referral journey<br>starts here.</h2>
                <p style="margin:0 auto;font-size:.75rem;color:rgba(255,255,255,.5);line-height:1.6;max-width:200px">Submit deals, track your pipeline, and earn commissions — all in one place.</p>

                {{-- Steps on right panel --}}
                <div style="margin-top:2rem;display:flex;flex-direction:column;gap:.75rem;text-align:left">
                    @foreach(['Receive invitation email' => true, 'Set up your account' => false, 'Access your dashboard' => false] as $step => $done)
                    <div style="display:flex;align-items:center;gap:.75rem">
                        <div style="width:20px;height:20px;border-radius:50%;background:{{ $done ? '#14B8A6' : 'rgba(255,255,255,0.15)' }};display:flex;align-items:center;justify-content:center;flex-shrink:0">
                            @if($done)
                            <svg width="10" height="10" fill="none" stroke="#fff" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                            @else
                            <div style="width:6px;height:6px;border-radius:50%;background:rgba(255,255,255,0.4)"></div>
                            @endif
                        </div>
                        <span style="font-size:.8125rem;color:{{ $done ? 'rgba(255,255,255,.9)' : 'rgba(255,255,255,.4)' }};{{ $done ? 'text-decoration:line-through' : '' }}">{{ $step }}</span>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>
