<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accept Invitation — ReferralBunny.ai</title>
    <link rel="icon" type="image/webp" href="/images/logos/referralbunny-favicon.webp">
    <link rel="apple-touch-icon" href="/images/logos/referralbunny-app-icon.webp">

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
            display: flex; align-items: center; justify-content: center;
        }
        .page-wrap {
            width: 100%; padding: 2rem 1.25rem;
            display: flex; flex-direction: column; align-items: center;
            min-height: 100vh; justify-content: center;
        }
        .card {
            width: 100%; max-width: 460px; background: #fff;
            border-radius: 24px; overflow: hidden;
            box-shadow: 0 32px 80px rgba(0,0,0,.45);
        }
        .card-header {
            padding: 2rem 2rem 1.25rem; text-align: center;
            border-bottom: 1px solid #f3f4f6;
        }
        .card-body { padding: 1.5rem 2rem 2rem; }
        .invite-badge {
            display: inline-flex; align-items: center; gap: .375rem;
            background: #ede9fe; color: #6d28d9;
            border-radius: 20px; padding: .3125rem .75rem;
            font-size: .75rem; font-weight: 600; margin-bottom: .875rem;
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
            transition: background .15s; font-family: 'Inter', sans-serif;
        }
        .btn-primary:hover  { background: #6d28d9; }
        .btn-primary:active { background: #5b21b6; }
        .error-box {
            display: flex; align-items: flex-start; gap: .75rem;
            background: #fef2f2; border: 1px solid #fecaca;
            border-radius: 12px; padding: .75rem 1rem; margin-bottom: 1.25rem;
        }
        .error-box p { margin: 0; font-size: .875rem; color: #dc2626; }
        .field-group { margin-bottom: 1rem; }
        @media (max-width: 520px) {
            .card-header { padding: 1.5rem 1.5rem 1rem; }
            .card-body   { padding: 1.25rem 1.5rem 1.5rem; }
        }
    </style>
</head>
<body>

<div class="page-wrap">
    <div class="card">
        <div class="card-header">
            <div style="margin-bottom:1rem">
                <x-rb-logo variant="horizontal" size="sm" :priority="true" :decorative="true" />
            </div>
            <span class="invite-badge">
                <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
                Workspace Invitation
            </span>
            <h1 style="margin:.25rem 0 .375rem;font-size:1.25rem;font-weight:700;color:#111827">
                You're invited to join
            </h1>
            <p style="margin:0;font-size:1rem;font-weight:600;color:#7c3aed">
                {{ $invitation->tenant?->name ?? 'a workspace' }}
            </p>
            <p style="margin:.375rem 0 0;font-size:.8125rem;color:#9ca3af">
                as <strong style="color:#374151">{{ ucfirst($invitation->role) }}</strong>
            </p>
        </div>

        <div class="card-body">
            @if ($errors->any())
                <div class="error-box">
                    <p>{{ $errors->first() }}</p>
                </div>
            @endif

            @if($existingUser)
                {{-- Existing user: just confirm and log in --}}
                <p style="font-size:.875rem;color:#6b7280;margin:0 0 1.25rem;line-height:1.6">
                    We found an existing account for <strong style="color:#374151">{{ $invitation->email }}</strong>.
                    Click below to accept the invitation and join this workspace.
                </p>
                <form method="POST" action="{{ route('tenant.accept-invite', $invitation->token) }}">
                    @csrf
                    <button type="submit" class="btn-primary">Accept Invitation &amp; Sign In</button>
                </form>
            @else
                {{-- New user: fill in details to create account --}}
                <p style="font-size:.875rem;color:#6b7280;margin:0 0 1.25rem;line-height:1.6">
                    Create your account for <strong style="color:#374151">{{ $invitation->email }}</strong> to accept this invitation.
                </p>
                <form method="POST" action="{{ route('tenant.accept-invite', $invitation->token) }}" x-data="{ showPw: false, showPwC: false }">
                    @csrf
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;margin-bottom:1rem">
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
                        <input type="email" class="field-input" value="{{ $invitation->email }}" disabled>
                    </div>
                    <div class="field-group">
                        <label class="field-label" for="password">Password</label>
                        <div style="position:relative">
                            <input id="password" name="password" :type="showPw ? 'text' : 'password'"
                                   autocomplete="new-password" required
                                   class="field-input" style="padding-right:2.75rem" placeholder="Min. 8 characters">
                            <button type="button" style="position:absolute;right:.875rem;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#9ca3af;padding:0;display:flex;align-items:center"
                                    @click="showPw = !showPw" tabindex="-1">
                                <svg x-show="!showPw" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                <svg x-show="showPw"  width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 4.411m0 0L21 21"/></svg>
                            </button>
                        </div>
                    </div>
                    <div class="field-group">
                        <label class="field-label" for="password_confirmation">Confirm password</label>
                        <div style="position:relative">
                            <input id="password_confirmation" name="password_confirmation"
                                   :type="showPwC ? 'text' : 'password'"
                                   autocomplete="new-password" required
                                   class="field-input" style="padding-right:2.75rem" placeholder="Re-enter password">
                            <button type="button" style="position:absolute;right:.875rem;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#9ca3af;padding:0;display:flex;align-items:center"
                                    @click="showPwC = !showPwC" tabindex="-1">
                                <svg x-show="!showPwC" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                <svg x-show="showPwC" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 4.411m0 0L21 21"/></svg>
                            </button>
                        </div>
                    </div>
                    <button type="submit" class="btn-primary" style="margin-top:.5rem">
                        Create Account &amp; Accept Invitation
                    </button>
                </form>
            @endif

            <div style="margin-top:1.25rem;padding-top:1.125rem;border-top:1px solid #f3f4f6;text-align:center">
                <p style="margin:0;font-size:.75rem;color:#9ca3af">
                    This invitation expires {{ $invitation->expires_at->diffForHumans() }}.
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

</body>
</html>
