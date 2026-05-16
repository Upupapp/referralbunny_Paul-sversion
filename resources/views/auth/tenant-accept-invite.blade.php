<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @include('partials.google-analytics')
    <title>Accept Invitation — ReferralBunny.ai</title>
    <link rel="icon" type="image/webp" href="/images/logos/referralbunny-favicon.webp">
    <link rel="apple-touch-icon" href="/images/logos/referralbunny-app-icon.webp">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0; padding: 0; min-height: 100vh;
            font-family: 'Inter', sans-serif;
            background: linear-gradient(145deg, #1E1347 0%, #2D1B69 55%, #1a1040 100%);
        }
        .page-wrap {
            width: 100%; padding: 1.5rem 1.25rem;
            display: flex; flex-direction: column; align-items: center;
            min-height: 100vh; justify-content: center;
        }
        .card {
            width: 100%; max-width: 460px; background: #fff;
            border-radius: 24px; overflow: hidden;
            box-shadow: 0 32px 80px rgba(0,0,0,.45);
            max-height: calc(100vh - 3rem);
            display: flex; flex-direction: column;
        }
        .card-header {
            padding: 1.75rem 2rem 1.25rem; text-align: center;
            border-bottom: 1px solid #f3f4f6; flex-shrink: 0;
        }
        .card-body { padding: 1.5rem 2rem 2rem; overflow-y: auto; flex: 1; }
        .invite-badge {
            display: inline-flex; align-items: center; gap: .375rem;
            background: #ede9fe; color: #6d28d9;
            border-radius: 20px; padding: .3125rem .75rem;
            font-size: .75rem; font-weight: 600; margin-bottom: .75rem;
        }
        .role-pill {
            display: inline-flex; align-items: center; gap: .375rem;
            border-radius: 20px; padding: .25rem .75rem;
            font-size: .6875rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: .06em;
        }
        .role-context {
            background: #f8f7ff; border: 1px solid #e8e4ff;
            border-radius: 12px; padding: .875rem 1rem;
            margin-bottom: 1.125rem;
        }
        .field-label {
            display: block; font-size: .8125rem; font-weight: 500;
            color: #374151; margin-bottom: .375rem;
        }
        .field-input {
            width: 100%; border: 1px solid #e5e7eb; background: #f9fafb;
            border-radius: 12px; padding: .75rem 1rem;
            font-size: .875rem; color: #111827; outline: none;
            transition: border-color .15s, box-shadow .15s, background .15s;
            font-family: 'Inter', sans-serif;
        }
        .field-input:focus { border-color: #8b5cf6; box-shadow: 0 0 0 3px rgba(139,92,246,.15); background: #fff; }
        .field-input::placeholder { color: #9ca3af; }
        .field-input:disabled { background: #f3f4f6; color: #6b7280; cursor: not-allowed; }
        .btn-primary {
            width: 100%; background: #7c3aed; color: #fff; border: none;
            border-radius: 12px; padding: .8125rem 1rem;
            font-size: .875rem; font-weight: 600; cursor: pointer;
            transition: background .15s, opacity .15s; font-family: 'Inter', sans-serif;
            display: flex; align-items: center; justify-content: center; gap: .5rem;
        }
        .btn-primary:hover:not(:disabled) { background: #6d28d9; }
        .btn-primary:active:not(:disabled) { background: #5b21b6; }
        .btn-primary:disabled { opacity: .65; cursor: not-allowed; }
        .error-box {
            display: flex; align-items: flex-start; gap: .75rem;
            background: #fef2f2; border: 1px solid #fecaca;
            border-radius: 12px; padding: .75rem 1rem; margin-bottom: 1.25rem;
        }
        .error-box p { margin: 0; font-size: .875rem; color: #dc2626; }
        .field-group { margin-bottom: 1rem; }
        .pw-wrap { position: relative; }
        .pw-toggle {
            position: absolute; right: .875rem; top: 50%; transform: translateY(-50%);
            background: none; border: none; cursor: pointer; color: #9ca3af; padding: 0;
            display: flex; align-items: center;
        }
        .pw-toggle:hover { color: #6b7280; }
        @media (max-width: 520px) {
            .card-header { padding: 1.5rem 1.5rem 1rem; }
            .card-body   { padding: 1.25rem 1.5rem 1.5rem; }
        }
    </style>
</head>
<body>

<div class="page-wrap">
    <div class="card">

        {{-- Header --}}
        <div class="card-header">
            <div style="margin-bottom:.875rem">
                <x-rb-logo variant="horizontal" size="sm" :priority="true" :decorative="true" />
            </div>

            {{-- R Bunny waving mascot --}}
            <div style="margin-bottom:.75rem">
                <x-r-bunny variant="waving" size="sm" :decorative="true" style="display:inline-block;width:56px;height:56px;object-fit:contain" />
            </div>

            <span class="invite-badge">
                <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
                Workspace Invitation
            </span>

            <h1 style="margin:.25rem 0 .25rem;font-size:1.25rem;font-weight:700;color:#111827">
                You're invited to join
            </h1>
            <p style="margin:0 0 .375rem;font-size:1rem;font-weight:600;color:#7c3aed">
                {{ $invitation->tenant?->name ?? 'a workspace' }}
            </p>

            {{-- Role badge with colour per role --}}
            @php
                $roleColors = [
                    'admin'   => 'background:#ede9fe;color:#6d28d9',
                    'manager' => 'background:#e0f2fe;color:#0369a1',
                    'member'  => 'background:#f0fdf4;color:#15803d',
                    'viewer'  => 'background:#f3f4f6;color:#4b5563',
                ];
                $roleStyle = $roleColors[$invitation->role] ?? 'background:#f3f4f6;color:#4b5563';
            @endphp
            <span class="role-pill" style="{{ $roleStyle }}">
                {{ ucfirst($invitation->role) }}
            </span>
        </div>

        {{-- Body --}}
        <div class="card-body">
            @if ($errors->any())
                <div class="error-box">
                    <svg width="16" height="16" fill="none" stroke="#dc2626" viewBox="0 0 24 24" style="flex-shrink:0;margin-top:2px">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <p>{{ $errors->first() }}</p>
                </div>
            @endif

            {{-- Role context card — tells new user what they can do --}}
            <div class="role-context">
                <p style="margin:0 0 .25rem;font-size:.75rem;font-weight:700;color:#7c3aed;text-transform:uppercase;letter-spacing:.08em">
                    What you'll have access to
                </p>
                <p style="margin:0;font-size:.8125rem;color:#4b5563;line-height:1.55">
                    @switch($invitation->role)
                        @case('admin')
                            Full access to deals, contacts, Referrers, messages, reports, and team management. No billing or ownership transfer.
                            @break
                        @case('manager')
                            Manage daily operations — deals, contacts, messages, and reports. Billing access is at your admin's discretion.
                            @break
                        @case('member')
                            View and work on deals, contacts, and messages assigned to you.
                            @break
                        @default
                            Read-only access to your workspace. You can view information but not make changes.
                    @endswitch
                </p>
            </div>

            @if($existingUser)
                {{-- Existing user: just confirm and log in --}}
                <p style="font-size:.875rem;color:#6b7280;margin:0 0 1.25rem;line-height:1.6">
                    We found an existing account for <strong style="color:#374151">{{ $invitation->email }}</strong>.
                    Click below to accept and join this workspace.
                </p>
                <form method="POST" action="{{ route('tenant.accept-invite', $invitation->token) }}"
                      x-data="{ submitting: false }" @submit="submitting = true">
                    @csrf
                    <button type="submit" class="btn-primary" :disabled="submitting">
                        <template x-if="!submitting">
                            <span>Accept Invitation &amp; Sign In</span>
                        </template>
                        <template x-if="submitting">
                            <span style="display:flex;align-items:center;gap:.5rem">
                                <svg style="animation:spin 1s linear infinite;width:14px;height:14px" fill="none" viewBox="0 0 24 24">
                                    <circle style="opacity:.25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path style="opacity:.75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                </svg>
                                Joining workspace…
                            </span>
                        </template>
                    </button>
                </form>
            @else
                {{-- New user: fill in details to create account --}}
                <p style="font-size:.875rem;color:#6b7280;margin:0 0 1.25rem;line-height:1.6">
                    Create your account for <strong style="color:#374151">{{ $invitation->email }}</strong>.
                </p>
                <form method="POST" action="{{ route('tenant.accept-invite', $invitation->token) }}"
                      x-data="{ showPw: false, showPwC: false, submitting: false }"
                      @submit="submitting = true">
                    @csrf

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
                        <div>
                            <label class="field-label" for="first_name">First name</label>
                            <input id="first_name" name="first_name" type="text" autocomplete="given-name" required
                                   class="field-input" value="{{ old('first_name') }}" placeholder="Jane">
                        </div>
                        <div>
                            <label class="field-label" for="last_name">Last name</label>
                            <input id="last_name" name="last_name" type="text" autocomplete="family-name" required
                                   class="field-input" value="{{ old('last_name') }}" placeholder="Smith">
                        </div>
                    </div>

                    <div class="field-group">
                        <label class="field-label">Email address</label>
                        <input type="email" class="field-input" value="{{ $invitation->email }}" disabled
                               aria-describedby="email-hint">
                        <p id="email-hint" style="margin:.25rem 0 0;font-size:.6875rem;color:#9ca3af">
                            This email is locked to your invitation.
                        </p>
                    </div>

                    <div class="field-group">
                        <label class="field-label" for="password">Password</label>
                        <div class="pw-wrap">
                            <input id="password" name="password" :type="showPw ? 'text' : 'password'"
                                   autocomplete="new-password" required minlength="8"
                                   class="field-input" style="padding-right:2.75rem"
                                   placeholder="Min. 8 characters">
                            <button type="button" class="pw-toggle" @click="showPw = !showPw"
                                    :aria-label="showPw ? 'Hide password' : 'Show password'" tabindex="-1">
                                <svg x-show="!showPw" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                <svg x-show="showPw"  width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 4.411m0 0L21 21"/></svg>
                            </button>
                        </div>
                    </div>

                    <div class="field-group">
                        <label class="field-label" for="password_confirmation">Confirm password</label>
                        <div class="pw-wrap">
                            <input id="password_confirmation" name="password_confirmation"
                                   :type="showPwC ? 'text' : 'password'"
                                   autocomplete="new-password" required
                                   class="field-input" style="padding-right:2.75rem"
                                   placeholder="Re-enter password">
                            <button type="button" class="pw-toggle" @click="showPwC = !showPwC"
                                    :aria-label="showPwC ? 'Hide password' : 'Show password'" tabindex="-1">
                                <svg x-show="!showPwC" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                <svg x-show="showPwC" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 4.411m0 0L21 21"/></svg>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn-primary" style="margin-top:.5rem" :disabled="submitting">
                        <template x-if="!submitting">
                            <span>Create Account &amp; Accept Invitation</span>
                        </template>
                        <template x-if="submitting">
                            <span style="display:flex;align-items:center;gap:.5rem">
                                <svg style="animation:spin 1s linear infinite;width:14px;height:14px" fill="none" viewBox="0 0 24 24">
                                    <circle style="opacity:.25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path style="opacity:.75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                </svg>
                                Creating your account…
                            </span>
                        </template>
                    </button>
                </form>
            @endif

            <div style="margin-top:1.25rem;padding-top:1.125rem;border-top:1px solid #f3f4f6;text-align:center">
                <p style="margin:0;font-size:.75rem;color:#9ca3af">
                    Invitation expires {{ $invitation->expires_at->diffForHumans() }}.
                </p>
                <a href="{{ route('tenant.login') }}"
                   style="display:inline-flex;align-items:center;gap:.3125rem;font-size:.75rem;color:#9ca3af;text-decoration:none;margin-top:.5rem;transition:color .15s"
                   onmouseover="this.style.color='#7c3aed'" onmouseout="this.style.color='#9ca3af'">
                    <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Back to Sign In
                </a>
            </div>
        </div>
    </div>
</div>

{{-- Full-page loading overlay --}}
<div id="rb-invite-loading"
     style="display:none;position:fixed;inset:0;z-index:9999;background:linear-gradient(145deg,#1E1347 0%,#2D1B69 55%,#1a1040 100%);align-items:center;justify-content:center">
    <div style="background:white;border-radius:24px;padding:40px 36px;max-width:360px;width:90%;text-align:center;box-shadow:0 32px 80px rgba(0,0,0,.5)">
        <div style="width:64px;height:64px;border-radius:20px;background:linear-gradient(135deg,#EDE9FE,#D1FAE5);display:flex;align-items:center;justify-content:center;margin:0 auto 20px">
            <svg style="width:30px;height:30px;color:#7c3aed;animation:spin 1s linear infinite" fill="none" viewBox="0 0 24 24">
                <circle style="opacity:.25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path style="opacity:.75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
            </svg>
        </div>
        <p style="font-size:11px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#7c3aed;margin-bottom:8px">Setting up your workspace</p>
        <p style="font-size:17px;font-weight:700;color:#111827;margin-bottom:8px">Almost there…</p>
        <p style="font-size:13px;color:#9ca3af;line-height:1.6;margin:0">Creating your account and adding you to the workspace.</p>
    </div>
</div>

<style>
@keyframes spin { to { transform: rotate(360deg); } }
</style>

<script>
// Show full-page loading overlay on form submit
document.querySelectorAll('form').forEach(function(form) {
    form.addEventListener('submit', function() {
        var overlay = document.getElementById('rb-invite-loading');
        if (overlay) overlay.style.display = 'flex';
    });
});
</script>

</body>
</html>
