<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accept Invitation — ReferralBunny.ai</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #F3F4F6; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; }
        .card { background: #fff; border-radius: 20px; box-shadow: 0 8px 40px rgba(0,0,0,.08); max-width: 480px; width: 100%; overflow: hidden; }
        .header { background: linear-gradient(135deg, #7B61FF, #4F46E5); padding: 28px 32px; color: #fff; }
        .header h1 { margin: 0 0 4px; font-size: 20px; font-weight: 700; }
        .header p { margin: 0; font-size: 13px; opacity: .85; }
        .body { padding: 28px 32px; }
        .role-badge { display: inline-block; background: #F3F0FF; color: #7B61FF; font-size: 11px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; padding: 4px 12px; border-radius: 999px; margin-bottom: 16px; }
        .contact-info { background: #F9FAFB; border: 1px solid #E5E7EB; border-radius: 12px; padding: 14px 16px; margin-bottom: 20px; }
        .contact-info p { margin: 0; font-size: 14px; color: #374151; line-height: 1.7; }
        .contact-info strong { color: #1E1B4B; }
        label { display: block; font-size: 13px; font-weight: 500; color: #374151; margin-bottom: 4px; margin-top: 14px; }
        input { width: 100%; border: 1.5px solid #E5E7EB; border-radius: 10px; padding: 10px 14px; font-size: 14px; outline: none; box-sizing: border-box; transition: border-color .15s; }
        input:focus { border-color: #7B61FF; box-shadow: 0 0 0 3px rgba(123,97,255,.1); }
        .btn-primary { width: 100%; background: #7B61FF; color: #fff; border: none; border-radius: 12px; padding: 13px; font-size: 15px; font-weight: 600; cursor: pointer; margin-top: 20px; transition: background .15s; }
        .btn-primary:hover { background: #6851e0; }
        .error-card { background: #FEF2F2; border: 1px solid #FCA5A5; border-radius: 12px; padding: 20px 24px; text-align: center; }
        .error-card h2 { margin: 0 0 8px; color: #DC2626; font-size: 17px; }
        .error-card p { margin: 0; color: #6B7280; font-size: 14px; }
        .alert-error { background: #FEF2F2; border: 1px solid #FCA5A5; border-radius: 10px; padding: 10px 14px; font-size: 13px; color: #DC2626; margin-bottom: 12px; }
    </style>
</head>
<body>
<div class="card">
    <div class="header">
        <h1>ReferralBunny.ai</h1>
        <p>Role Invitation</p>
    </div>

    <div class="body">

        @if (isset($error))
            <div class="error-card">
                <h2>Invitation Unavailable</h2>
                <p>{{ $error }}</p>
            </div>

        @else
            <div class="role-badge">{{ $invitation->roleLabel() }}</div>

            <div class="contact-info">
                <p><strong>{{ trim(($contact->first_name ?? '') . ' ' . ($contact->last_name ?? '')) ?: $invitation->invited_email }}</strong></p>
                <p>{{ $invitation->invited_email }}</p>
                <p>Invited to join <strong>{{ $tenant->name ?? 'this workspace' }}</strong> as <strong>{{ $invitation->roleLabel() }}</strong></p>
            </div>

            @if (session('success'))
                <div style="background:#ECFDF5;border:1px solid #A7F3D0;border-radius:10px;padding:10px 14px;font-size:13px;color:#065F46;margin-bottom:12px;">
                    {{ session('success') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="alert-error">{{ $errors->first() }}</div>
            @endif

            @if ($existingUser ?? false)
                <p style="font-size:14px;color:#374151;margin-bottom:16px;">
                    An account already exists for <strong>{{ $invitation->invited_email }}</strong>. Enter your new password to activate this role and join this workspace.
                </p>
            @else
                <p style="font-size:14px;color:#374151;margin-bottom:16px;">
                    Set up your account to accept this invitation and access your new role.
                </p>
            @endif

            <form method="POST" action="{{ route('contact-role-invite.accept', $invitation->token) }}">
                @csrf
                <label>Full Name</label>
                <input type="text" name="name"
                       value="{{ old('name', trim(($contact->first_name ?? '') . ' ' . ($contact->last_name ?? ''))) }}"
                       required placeholder="Your full name">

                <label>Password <span style="font-size:11px;color:#9CA3AF;font-weight:400">— min. 8 characters</span></label>
                <input type="password" name="password" required minlength="8" placeholder="Create a secure password (min 8 chars)">

                <label>Confirm Password</label>
                <input type="password" name="password_confirmation" required placeholder="Repeat your password">

                <button type="submit" class="btn-primary" x-data="{ sub: false }" @click="sub=true" :disabled="sub" x-text="sub ? 'Setting up...' : 'Accept Invitation &amp; Set Up Account'">Accept Invitation &amp; Set Up Account</button>
            </form>

            <p style="font-size:12px;color:#9CA3AF;text-align:center;margin-top:16px;">
                This invitation expires {{ $invitation->expires_at?->diffForHumans() ?? 'soon' }}.
                If you did not expect this, ignore this page.
            </p>
        @endif

    </div>
</div>
</body>
</html>
