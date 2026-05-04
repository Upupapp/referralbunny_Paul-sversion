{{--
    Email: Access Extended by R Bunny
    TO:    Tenant admin email
    FROM:  R Bunny (noreply@referralbunny.ai)
    NOTE:  Wire up dispatch when mail provider is integrated.
           See: BillingController::extendAccess() — TODO comment
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>R Bunny extended your access</title>
</head>
<body style="margin:0;padding:0;background:#F0EFFA;font-family:'Inter',Arial,sans-serif;">

<table width="100%" cellpadding="0" cellspacing="0" style="background:#F0EFFA;padding:40px 16px;">
    <tr>
        <td align="center">
            <table width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;">

                {{-- Logo header --}}
                <tr>
                    <td align="center" style="padding-bottom:24px;">
                        <div style="display:inline-flex;align-items:center;gap:10px;">
                            <div style="width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,#FF6CAB,#7B61FF);display:flex;align-items:center;justify-content:center;font-size:18px;">🐰</div>
                            <span style="font-weight:700;font-size:15px;color:#1E1B4B;">Referral Bunny</span>
                        </div>
                    </td>
                </tr>

                {{-- Main card --}}
                <tr>
                    <td style="background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 4px 24px rgba(123,97,255,0.1);">

                        {{-- Purple gradient header --}}
                        <div style="background:linear-gradient(145deg,#1E1B4B,#7B61FF);padding:40px 32px;text-align:center;">
                            <img src="{{ config('app.url') }}/images/mascots/r-bunny-celebration.webp"
                                 alt="R Bunny celebrating"
                                 width="120" height="120"
                                 style="width:120px;height:120px;object-fit:contain;margin-bottom:16px;">
                            <h1 style="color:#fff;font-size:24px;font-weight:700;margin:0 0 8px;">
                                R Bunny extended your access!
                            </h1>
                            <p style="color:rgba(255,255,255,0.7);font-size:14px;margin:0;">
                                Good news from the Referral Bunny team
                            </p>
                        </div>

                        {{-- Body --}}
                        <div style="padding:32px;">

                            <p style="font-size:15px;color:#374151;margin:0 0 20px;">
                                Hi <strong>{{ $tenantAdminName }}</strong>,
                            </p>

                            <p style="font-size:15px;color:#374151;margin:0 0 24px;line-height:1.6;">
                                We have some great news — R Bunny has extended your access to
                                <strong>{{ $tenantName }}</strong> by
                                <strong style="color:#7B61FF;">{{ $days }} {{ $days === 1 ? 'day' : 'days' }}</strong>.
                            </p>

                            {{-- Highlight box --}}
                            <div style="background:#F0EFFA;border-radius:12px;padding:20px;margin-bottom:24px;border-left:4px solid #7B61FF;">
                                <p style="font-size:13px;color:#6B7280;margin:0 0 6px;text-transform:uppercase;letter-spacing:0.05em;font-weight:600;">Extension Details</p>
                                <p style="font-size:22px;font-weight:700;color:#1E1B4B;margin:0;">
                                    +{{ $days }} {{ $days === 1 ? 'Day' : 'Days' }}
                                </p>
                                @if($note)
                                <p style="font-size:13px;color:#6B7280;margin:8px 0 0;">
                                    Note from R Bunny: {{ $note }}
                                </p>
                                @endif
                            </div>

                            <p style="font-size:14px;color:#6B7280;margin:0 0 28px;line-height:1.6;">
                                Your referral program is still running smoothly. Log in to your dashboard to continue managing your deals, referrers, and commissions.
                            </p>

                            {{-- CTA --}}
                            <div style="text-align:center;margin-bottom:28px;">
                                <a href="{{ config('app.url') }}/tenant/{{ $tenantId }}/dashboard"
                                   style="display:inline-block;background:linear-gradient(135deg,#7B61FF,#FF6CAB);color:#fff;font-size:14px;font-weight:600;padding:14px 32px;border-radius:12px;text-decoration:none;">
                                    Open My Dashboard →
                                </a>
                            </div>

                            <hr style="border:none;border-top:1px solid #F3F4F6;margin:0 0 20px;">

                            <p style="font-size:12px;color:#9CA3AF;margin:0;text-align:center;line-height:1.6;">
                                This is an automated message from Referral Bunny.<br>
                                If you have questions, contact your account manager.
                            </p>
                        </div>
                    </td>
                </tr>

                {{-- Footer --}}
                <tr>
                    <td align="center" style="padding:24px 0 0;">
                        <p style="font-size:12px;color:#9CA3AF;margin:0;">
                            © {{ date('Y') }} Referral Bunny. All rights reserved.
                        </p>
                    </td>
                </tr>

            </table>
        </td>
    </tr>
</table>

</body>
</html>
