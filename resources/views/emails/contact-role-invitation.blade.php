<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>You've been invited — ReferralBunny.ai</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f8f8f8; margin: 0; padding: 24px; color: #1a1a2e; }
        .container { max-width: 560px; margin: 0 auto; background: #fff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,.07); }
        .header { background: linear-gradient(135deg, #7B61FF, #4F46E5); padding: 32px 40px; color: #fff; }
        .header h1 { margin: 0 0 4px; font-size: 22px; font-weight: 700; }
        .header p { margin: 0; font-size: 14px; opacity: .85; }
        .body { padding: 32px 40px; }
        .role-badge { display: inline-block; background: #F3F0FF; color: #7B61FF; font-size: 12px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; padding: 4px 12px; border-radius: 999px; margin-bottom: 20px; }
        .body p { font-size: 15px; line-height: 1.7; color: #374151; margin: 0 0 16px; }
        .deal-note { background: #EFF6FF; border-left: 4px solid #3B82F6; border-radius: 8px; padding: 14px 16px; margin: 16px 0; font-size: 14px; color: #1E40AF; }
        .custom-message { background: #FAFAFA; border: 1px solid #E5E7EB; border-radius: 10px; padding: 14px 16px; margin: 16px 0; font-size: 14px; color: #6B7280; font-style: italic; }
        .cta { text-align: center; margin: 28px 0; }
        .btn { display: inline-block; background: #7B61FF; color: #fff; text-decoration: none; font-size: 15px; font-weight: 600; padding: 14px 36px; border-radius: 12px; }
        .permission-list { margin: 16px 0; padding-left: 20px; }
        .permission-list li { font-size: 14px; color: #374151; line-height: 1.8; }
        .footer { padding: 20px 40px; background: #F9FAFB; border-top: 1px solid #F3F4F6; font-size: 12px; color: #9CA3AF; text-align: center; }
        .footer a { color: #7B61FF; text-decoration: none; }
        @media (max-width: 600px) { .header, .body, .footer { padding-left: 24px; padding-right: 24px; } }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>ReferralBunny.ai</h1>
        <p>You've been invited by {{ $tenantName }}</p>
    </div>

    <div class="body">
        <div class="role-badge">{{ $roleLabel }}</div>

        <p>Hi {{ $contactName }},</p>

        @if ($role === 'referrer')
            <p>You have been invited to join <strong>{{ $tenantName }}</strong> on ReferralBunny.ai as a <strong>Referrer</strong>. As a Referrer, you can submit and manage referral deals based on the program rules.</p>
            <ul class="permission-list">
                <li>Submit and track referral deals</li>
                <li>Manage your own leads and pipeline</li>
                <li>Message the tenant team on active deals</li>
                <li>View your commission and performance</li>
            </ul>

        @elseif ($role === 'tenant_manager')
            <p>You have been invited to join <strong>{{ $tenantName }}</strong> as a <strong>Tenant Manager</strong>. You will have access to management tools based on the permissions assigned to your account.</p>
            <ul class="permission-list">
                <li>Manage deals, contacts, and referrers</li>
                <li>View reports and analytics</li>
                <li>Message referrers and partners</li>
                <li>Perform import and export operations</li>
            </ul>

        @elseif ($role === 'tenant_staff')
            <p>You have been invited to join <strong>{{ $tenantName }}</strong> as <strong>Tenant Staff</strong>. You will have access to operational tools based on your assigned permissions.</p>

        @elseif ($role === 'partner')
            <p>You have been invited to join <strong>ReferralBunny.ai</strong> as a <strong>Partner</strong> for a specific deal under <strong>{{ $tenantName }}</strong>.</p>
            @if ($dealName)
            <div class="deal-note">
                <strong>Deal:</strong> {{ $dealName }}<br>
                Your access is limited to this deal's details and connected messages.
            </div>
            @endif
            <p>As a Partner, your access is limited to the deal details and messages connected to that deal only. You will not have access to other deals or tenant-wide information.</p>
        @endif

        @if ($customMessage)
        <div class="custom-message">
            <strong>Personal message:</strong><br>
            {{ $customMessage }}
        </div>
        @endif

        <div class="cta">
            <a href="{{ $setupUrl }}" class="btn">Accept Invitation &amp; Set Up Account</a>
        </div>

        <p style="font-size:13px;color:#9CA3AF;text-align:center;">
            This invitation expires in 7 days. If you did not expect this invitation, you can safely ignore this email.
        </p>
    </div>

    <div class="footer">
        &copy; {{ date('Y') }} ReferralBunny.ai &nbsp;&middot;&nbsp;
        <a href="{{ url('/') }}">Visit Platform</a>
    </div>
</div>
</body>
</html>
