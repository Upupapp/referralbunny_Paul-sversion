<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ $subject ?? 'ReferralBunny.ai' }}</title>
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { background: #F0EFFA; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; -webkit-font-smoothing: antialiased; }
    .wrapper { max-width: 600px; margin: 0 auto; padding: 32px 16px; }
    .header { background: linear-gradient(135deg, #1E1B4B 0%, #4C3FA0 100%); border-radius: 16px 16px 0 0; padding: 32px 40px; text-align: center; }
    .header img { height: 32px; }
    .header-label { color: rgba(255,255,255,0.5); font-size: 11px; font-weight: 600; letter-spacing: 0.15em; text-transform: uppercase; margin-top: 8px; }
    .body { background: #ffffff; padding: 40px; }
    .greeting { font-size: 22px; font-weight: 700; color: #1E1B4B; margin-bottom: 12px; }
    .text { font-size: 15px; color: #4B5563; line-height: 1.7; margin-bottom: 16px; }
    .cta-wrap { text-align: center; margin: 32px 0; }
    .cta { display: inline-block; background: #7B61FF; color: #ffffff !important; text-decoration: none; font-size: 15px; font-weight: 600; padding: 14px 32px; border-radius: 12px; }
    .cta:hover { background: #6D4FE8; }
    .cta-teal { background: #0D9488; }
    .cta-teal:hover { background: #0F766E; }
    .cta-blue { background: #1D4ED8; }
    .cta-blue:hover { background: #1E40AF; }
    .divider { border: none; border-top: 1px solid #F3F4F6; margin: 24px 0; }
    .link-box { background: #F9FAFB; border: 1px solid #E5E7EB; border-radius: 8px; padding: 12px 16px; font-size: 13px; color: #6B7280; word-break: break-all; margin: 12px 0 24px; }
    .link-box a { color: #7B61FF; text-decoration: none; }
    .highlight-box { border-radius: 12px; padding: 20px 24px; margin: 20px 0; }
    .highlight-purple { background: #F5F3FF; border-left: 4px solid #7B61FF; }
    .highlight-teal { background: #F0FDFA; border-left: 4px solid #0D9488; }
    .highlight-orange { background: #FFFBEB; border-left: 4px solid #F59E0B; }
    .highlight-red { background: #FEF2F2; border-left: 4px solid #EF4444; }
    .highlight-title { font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 4px; }
    .highlight-text { font-size: 14px; color: #374151; }
    .footer { background: #F9FAFB; border-radius: 0 0 16px 16px; padding: 24px 40px; text-align: center; border-top: 1px solid #F3F4F6; }
    .footer-text { font-size: 12px; color: #9CA3AF; line-height: 1.6; }
    .footer-text a { color: #7B61FF; text-decoration: none; }
    @media (max-width: 600px) {
        .body { padding: 24px 20px; }
        .header { padding: 24px 20px; }
        .footer { padding: 20px; }
    }
</style>
</head>
<body>
<div class="wrapper">
    <div class="header">
        <table width="100%" cellpadding="0" cellspacing="0" border="0">
            <tr>
                <td align="center" style="padding-bottom:8px">
                    <img src="https://referralbunny.ai/images/logos/referralbunny-social-avatar.png"
                         alt="ReferralBunny.ai" width="56" height="56"
                         style="width:56px;height:56px;border-radius:50%;display:block;margin:0 auto;border:3px solid rgba(255,255,255,0.2)">
                </td>
            </tr>
            <tr>
                <td align="center" style="padding-bottom:6px">
                    <div style="color:#ffffff;font-size:18px;font-weight:700;letter-spacing:-0.02em">referralbunny.ai</div>
                </td>
            </tr>
            @if(isset($headerLabel))
            <tr>
                <td align="center">
                    <div class="header-label">{{ $headerLabel }}</div>
                </td>
            </tr>
            @endif
        </table>
    </div>
    <div class="body">
        @yield('content')
    </div>
    <div class="footer">
        <p class="footer-text">
            © {{ date('Y') }} ReferralBunny.ai · All rights reserved<br>
            This email was sent automatically. Please do not reply directly to this email.
        </p>
    </div>
</div>
</body>
</html>
