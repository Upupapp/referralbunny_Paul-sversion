<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Build a Referral Program — ReferralBunny.ai</title>
    <link rel="icon" type="image/webp" href="/images/logos/referralbunny-favicon.webp">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0; padding: 0;
            min-height: 100vh;
            font-family: 'Inter', sans-serif;
            background: linear-gradient(145deg, #1E1347 0%, #2D1B69 55%, #1a1040 100%);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-page {
            width: 100%;
            padding: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        .login-card {
            width: 100%;
            max-width: 960px;
            display: flex;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 32px 80px rgba(0,0,0,.45);
        }
        .card-left {
            flex: 1;
            background: #ffffff;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 2.5rem 3rem;
            overflow-y: auto;
            max-height: 100vh;
        }
        .card-right {
            width: 360px;
            flex-shrink: 0;
            background: linear-gradient(145deg, #1E1347 0%, #2D1B69 60%, #1a1040 100%);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2.5rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        @media (max-width: 767px) {
            .card-right { display: none !important; }
            .card-left  { padding: 2.5rem 1.75rem; }
        }
        .form-wrap { max-width: 380px; }
        .field-label {
            display: block; font-size: .8125rem; font-weight: 500;
            color: #374151; margin-bottom: .375rem;
        }
        .field-input {
            width: 100%; border: 1px solid #e5e7eb; background: #f9fafb;
            border-radius: 12px; padding: .75rem 1rem; font-size: .875rem;
            color: #111827; outline: none; font-family: 'Inter', sans-serif;
            transition: border-color .15s, box-shadow .15s, background .15s;
        }
        .field-input:focus { border-color: #8b5cf6; box-shadow: 0 0 0 3px rgba(139,92,246,.15); background: #fff; }
        .field-input::placeholder { color: #9ca3af; }
        .field-select {
            width: 100%; border: 1px solid #e5e7eb; background: #f9fafb;
            border-radius: 12px; padding: .75rem 1rem; font-size: .875rem;
            color: #111827; outline: none; font-family: 'Inter', sans-serif;
            cursor: pointer; appearance: none;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
            background-repeat: no-repeat; background-position: right .75rem center; background-size: 1.25rem;
            padding-right: 2.5rem;
        }
        .field-select:focus { border-color: #8b5cf6; box-shadow: 0 0 0 3px rgba(139,92,246,.15); background-color: #fff; }
        .btn-primary {
            width: 100%; background: #7c3aed; color: #fff;
            border: none; border-radius: 12px; padding: .8125rem 1rem;
            font-size: .875rem; font-weight: 600; cursor: pointer;
            transition: background .15s; font-family: 'Inter', sans-serif; margin-top: .5rem;
        }
        .btn-primary:hover  { background: #6d28d9; }
        .btn-primary:active { background: #5b21b6; }
        .btn-primary:disabled { opacity: .6; cursor: not-allowed; }
        .error-box {
            display: flex; align-items: flex-start; gap: .75rem;
            background: #fef2f2; border: 1px solid #fecaca;
            border-radius: 12px; padding: .75rem 1rem; margin-bottom: 1.25rem;
        }
        .error-box p { margin: 0; font-size: .875rem; color: #dc2626; }
        .section-label {
            font-size: .625rem; font-weight: 700; color: #9ca3af;
            letter-spacing: .12em; text-transform: uppercase;
            margin: 1.5rem 0 1rem; padding-top: 1.25rem;
            border-top: 1px solid #f3f4f6;
        }
        .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: .75rem; }
        .pw-wrap { position: relative; }
        .pw-toggle {
            position: absolute; right: .875rem; top: 50%; transform: translateY(-50%);
            background: none; border: none; cursor: pointer; color: #9ca3af; padding: 0; display: flex;
        }
        .pw-toggle:hover { color: #6b7280; }
    </style>
</head>
<body>

<div class="login-page">
    <div class="login-card">

        {{-- LEFT — form --}}
        <div class="card-left">
            <div style="margin-bottom:1.75rem">
                <x-rb-logo variant="horizontal" size="sm" :priority="true" :decorative="true" />
            </div>

            <h1 style="margin:0 0 .25rem;font-size:1.5rem;font-weight:700;color:#111827">Build your referral program</h1>
            <p style="margin:0 0 1.75rem;font-size:.8125rem;color:#6b7280;line-height:1.5">Create your ReferralBunny.ai workspace. Manage referrers, deals, and commissions — all in one place.</p>

            @if ($errors->any())
                <div class="error-box form-wrap">
                    <x-r-bunny variant="warning" size="xs" :decorative="true" style="flex-shrink:0;margin-top:2px" />
                    <p>{{ $errors->first() }}</p>
                </div>
            @endif

            @if(session('success'))
                <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;padding:.75rem 1rem;margin-bottom:1.25rem" class="form-wrap">
                    <p style="margin:0;font-size:.875rem;color:#166534">{{ session('success') }}</p>
                </div>
            @endif

            <form method="POST" action="{{ route('tenant.create.post') }}" class="form-wrap" x-data="{ submitting: false }" @submit="submitting = true">
                @csrf

                {{-- Personal info --}}
                <p class="section-label" style="margin-top:0;padding-top:0;border-top:none">Your account</p>

                <div class="two-col" style="margin-bottom:.875rem">
                    <div>
                        <label class="field-label">First name</label>
                        <input name="first_name" type="text" required autocomplete="given-name"
                               value="{{ old('first_name') }}" class="field-input" placeholder="Jane">
                    </div>
                    <div>
                        <label class="field-label">Last name</label>
                        <input name="last_name" type="text" required autocomplete="family-name"
                               value="{{ old('last_name') }}" class="field-input" placeholder="Dela Cruz">
                    </div>
                </div>

                <div style="margin-bottom:.875rem">
                    <label class="field-label">Work email</label>
                    <input name="email" type="email" required autocomplete="email"
                           value="{{ old('email') }}" class="field-input" placeholder="you@company.com">
                </div>

                <div class="two-col" style="margin-bottom:.875rem">
                    <div>
                        <label class="field-label">Password</label>
                        <div class="pw-wrap" x-data="{ show: false }">
                            <input name="password" :type="show ? 'text' : 'password'" required
                                   class="field-input" style="padding-right:2.75rem" placeholder="Min. 8 characters">
                            <button type="button" class="pw-toggle" @click="show = !show" tabindex="-1">
                                <svg x-show="!show" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                <svg x-show="show"  width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 4.411m0 0L21 21"/></svg>
                            </button>
                        </div>
                    </div>
                    <div>
                        <label class="field-label">Confirm password</label>
                        <input name="password_confirmation" type="password" required
                               class="field-input" placeholder="••••••••">
                    </div>
                </div>

                {{-- Workspace info --}}
                <p class="section-label">Your workspace</p>

                <div style="margin-bottom:.875rem">
                    <label class="field-label">Workspace name</label>
                    <input name="workspace_name" type="text" required
                           value="{{ old('workspace_name') }}" class="field-input" placeholder="Acme Corp Referral Program">
                    <p style="font-size:.6875rem;color:#9ca3af;margin:.25rem 0 0">This is the name your referrers will see.</p>
                </div>

                <div class="two-col" style="margin-bottom:.875rem">
                    <div>
                        <label class="field-label">Industry</label>
                        <select name="industry" required class="field-select">
                            <option value="">Select…</option>
                            <option value="Technology" {{ old('industry') === 'Technology' ? 'selected' : '' }}>Technology</option>
                            <option value="Real Estate" {{ old('industry') === 'Real Estate' ? 'selected' : '' }}>Real Estate</option>
                            <option value="Financial Services" {{ old('industry') === 'Financial Services' ? 'selected' : '' }}>Financial Services</option>
                            <option value="Healthcare" {{ old('industry') === 'Healthcare' ? 'selected' : '' }}>Healthcare</option>
                            <option value="Education" {{ old('industry') === 'Education' ? 'selected' : '' }}>Education</option>
                            <option value="Government / LGU" {{ old('industry') === 'Government / LGU' ? 'selected' : '' }}>Government / LGU</option>
                            <option value="Retail / E-commerce" {{ old('industry') === 'Retail / E-commerce' ? 'selected' : '' }}>Retail / E-commerce</option>
                            <option value="Professional Services" {{ old('industry') === 'Professional Services' ? 'selected' : '' }}>Professional Services</option>
                            <option value="Insurance" {{ old('industry') === 'Insurance' ? 'selected' : '' }}>Insurance</option>
                            <option value="Other" {{ old('industry') === 'Other' ? 'selected' : '' }}>Other</option>
                        </select>
                    </div>
                    <div>
                        <label class="field-label">Country</label>
                        <select name="country" required class="field-select">
                            <option value="">Select…</option>
                            <option value="Philippines" {{ old('country', 'Philippines') === 'Philippines' ? 'selected' : '' }}>Philippines</option>
                            <option value="United States" {{ old('country') === 'United States' ? 'selected' : '' }}>United States</option>
                            <option value="Singapore" {{ old('country') === 'Singapore' ? 'selected' : '' }}>Singapore</option>
                            <option value="Australia" {{ old('country') === 'Australia' ? 'selected' : '' }}>Australia</option>
                            <option value="United Kingdom" {{ old('country') === 'United Kingdom' ? 'selected' : '' }}>United Kingdom</option>
                            <option value="Other" {{ old('country') === 'Other' ? 'selected' : '' }}>Other</option>
                        </select>
                    </div>
                </div>

                <div class="two-col" style="margin-bottom:1.25rem">
                    <div>
                        <label class="field-label">Timezone</label>
                        <select name="timezone" required class="field-select">
                            <option value="">Select…</option>
                            <option value="Asia/Manila" {{ old('timezone', 'Asia/Manila') === 'Asia/Manila' ? 'selected' : '' }}>Asia/Manila (PHT)</option>
                            <option value="Asia/Singapore" {{ old('timezone') === 'Asia/Singapore' ? 'selected' : '' }}>Asia/Singapore (SGT)</option>
                            <option value="Australia/Sydney" {{ old('timezone') === 'Australia/Sydney' ? 'selected' : '' }}>Australia/Sydney (AEST)</option>
                            <option value="America/New_York" {{ old('timezone') === 'America/New_York' ? 'selected' : '' }}>America/New_York (EST)</option>
                            <option value="America/Los_Angeles" {{ old('timezone') === 'America/Los_Angeles' ? 'selected' : '' }}>America/Los_Angeles (PST)</option>
                            <option value="Europe/London" {{ old('timezone') === 'Europe/London' ? 'selected' : '' }}>Europe/London (GMT)</option>
                            <option value="UTC" {{ old('timezone') === 'UTC' ? 'selected' : '' }}>UTC</option>
                        </select>
                    </div>
                    <div>
                        <label class="field-label">Currency</label>
                        <select name="preferred_currency" required class="field-select">
                            <option value="">Select…</option>
                            <option value="PHP" {{ old('preferred_currency', 'PHP') === 'PHP' ? 'selected' : '' }}>PHP — Philippine Peso</option>
                            <option value="USD" {{ old('preferred_currency') === 'USD' ? 'selected' : '' }}>USD — US Dollar</option>
                            <option value="SGD" {{ old('preferred_currency') === 'SGD' ? 'selected' : '' }}>SGD — Singapore Dollar</option>
                            <option value="AUD" {{ old('preferred_currency') === 'AUD' ? 'selected' : '' }}>AUD — Australian Dollar</option>
                            <option value="GBP" {{ old('preferred_currency') === 'GBP' ? 'selected' : '' }}>GBP — British Pound</option>
                        </select>
                    </div>
                </div>

                <div style="display:flex;align-items:flex-start;gap:.625rem;margin-bottom:1rem">
                    <input type="checkbox" name="terms" id="terms" required
                           style="margin-top:2px;width:16px;height:16px;accent-color:#7c3aed;cursor:pointer;flex-shrink:0">
                    <label for="terms" style="font-size:.75rem;color:#6b7280;cursor:pointer;line-height:1.5">
                        I agree to the <a href="#" style="color:#7c3aed">Terms of Service</a> and <a href="#" style="color:#7c3aed">Privacy Policy</a>.
                    </label>
                </div>

                <button type="submit" class="btn-primary" :disabled="submitting" x-text="submitting ? 'Creating workspace…' : 'Create My Workspace'">
                    Create My Workspace
                </button>
            </form>

            <div style="margin-top:1.5rem;padding-top:1.25rem;border-top:1px solid #f3f4f6" class="form-wrap">
                <div style="display:flex;flex-direction:column;gap:.5rem">
                    <a href="{{ route('tenant.login') }}"
                       style="font-size:.75rem;color:#7c3aed;text-decoration:none"
                       onmouseover="this.style.textDecoration='underline'" onmouseout="this.style.textDecoration='none'">
                        Already have an account? Sign in →
                    </a>
                    <a href="{{ route('tenant.join') }}"
                       style="font-size:.75rem;color:#9ca3af;text-decoration:none"
                       onmouseover="this.style.color='#6b7280'" onmouseout="this.style.color='#9ca3af'">
                        Joining an existing program? Join instead →
                    </a>
                </div>
            </div>

            <div style="margin-top:1rem" class="form-wrap">
                <a href="{{ route('portal.select') }}"
                   style="display:inline-flex;align-items:center;gap:.375rem;font-size:.75rem;color:#9ca3af;text-decoration:none;transition:color .15s"
                   onmouseover="this.style.color='#7B61FF'" onmouseout="this.style.color='#9ca3af'">
                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Back
                </a>
            </div>
        </div>

        {{-- RIGHT — brand panel --}}
        <div class="card-right">
            <div style="position:absolute;width:260px;height:260px;border-radius:50%;top:-80px;right:-80px;opacity:.25;pointer-events:none;background:radial-gradient(circle,#7C3AED,transparent 65%)"></div>
            <div style="position:absolute;width:200px;height:200px;border-radius:50%;bottom:-60px;left:-60px;opacity:.2;pointer-events:none;background:radial-gradient(circle,#3B82F6,transparent 65%)"></div>
            <div style="position:relative;z-index:1">
                <x-r-bunny variant="hero" size="lg" :decorative="true" style="display:block;margin:0 auto 1.25rem" />
                <h2 style="margin:0 0 .5rem;font-size:1.125rem;font-weight:700;color:#fff;line-height:1.4">Your referral program<br>starts here.</h2>
                <p style="font-size:.75rem;color:rgba(255,255,255,.5);line-height:1.6;max-width:180px;margin:0 auto">Build, launch, and manage a referral program in minutes.</p>
                <div style="margin-top:1.5rem;display:flex;flex-direction:column;gap:.5rem">
                    <div style="display:flex;align-items:center;gap:.5rem;text-align:left">
                        <div style="width:20px;height:20px;border-radius:50%;background:rgba(123,97,255,0.3);display:flex;align-items:center;justify-content:center;flex-shrink:0">
                            <svg width="10" height="10" fill="none" stroke="#a78bfa" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                        </div>
                        <span style="font-size:.6875rem;color:rgba(255,255,255,.6)">Invite referrers and resellers</span>
                    </div>
                    <div style="display:flex;align-items:center;gap:.5rem;text-align:left">
                        <div style="width:20px;height:20px;border-radius:50%;background:rgba(123,97,255,0.3);display:flex;align-items:center;justify-content:center;flex-shrink:0">
                            <svg width="10" height="10" fill="none" stroke="#a78bfa" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                        </div>
                        <span style="font-size:.6875rem;color:rgba(255,255,255,.6)">Track deals through the pipeline</span>
                    </div>
                    <div style="display:flex;align-items:center;gap:.5rem;text-align:left">
                        <div style="width:20px;height:20px;border-radius:50%;background:rgba(123,97,255,0.3);display:flex;align-items:center;justify-content:center;flex-shrink:0">
                            <svg width="10" height="10" fill="none" stroke="#a78bfa" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                        </div>
                        <span style="font-size:.6875rem;color:rgba(255,255,255,.6)">Automate commissions and payouts</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>
