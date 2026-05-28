<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Partner Invite — {{ $tenantName }}</title>
<style>
  * { box-sizing:border-box; margin:0; padding:0; }
  body { background:#f0fdfa; font-family:'Inter',Arial,sans-serif; color:#374151; }
  .wrap { max-width:560px; margin:40px auto; padding:16px; }
  .card { background:#fff; border-radius:20px; overflow:hidden; box-shadow:0 4px 24px rgba(0,0,0,.08); }
  .header { background:linear-gradient(135deg,#0D9488,#14B8A6); padding:36px 32px 28px; text-align:center; }
  .header img { width:48px; height:48px; margin-bottom:12px; border-radius:12px; }
  .header h1 { color:#fff; font-size:22px; font-weight:700; line-height:1.3; }
  .header p  { color:rgba(255,255,255,.8); font-size:13px; margin-top:6px; }
  .body { padding:32px; }
  .badge { display:inline-block; padding:4px 12px; border-radius:9999px; background:#CCFBF1; color:#0F766E; font-size:12px; font-weight:700; margin-bottom:18px; }
  .body p  { font-size:14px; color:#374151; line-height:1.7; margin-bottom:14px; }
  .btn-wrap { text-align:center; margin:28px 0 20px; }
  .btn { display:inline-block; padding:14px 36px; border-radius:14px; background:linear-gradient(135deg,#0D9488,#14B8A6); color:#fff; font-size:14px; font-weight:700; text-decoration:none; letter-spacing:.01em; }
  .divider { height:1px; background:#f3f4f6; margin:24px 0; }
  .url-box { background:#f9fafb; border-radius:10px; padding:10px 14px; font-size:12px; color:#6b7280; word-break:break-all; }
  .url-box a { color:#0D9488; }
  .footer { text-align:center; padding:20px 32px 28px; font-size:11px; color:#9ca3af; line-height:1.6; }
</style>
</head>
<body>
<div class="wrap">
  <div class="card">

    {{-- Header --}}
    <div class="header">
      <h1>You're invited as a Partner</h1>
      <p>{{ $tenantName }} · ReferralBunny.ai</p>
    </div>

    {{-- Body --}}
    <div class="body">
      <span class="badge">Partner Invite</span>

      <p>Hi {{ $partnerFirstName ?? 'there' }},</p>

      <p>
        <strong>{{ $inviterName }}</strong> has invited you to join
        <strong>{{ $tenantName }}</strong> as a <strong>Partner</strong>
        on ReferralBunny.ai. As a Partner, you'll have access to the deals
        you're connected to and can track your commission.
      </p>

      <p>
        Click the button below to create your password and activate your account.
        This invite link expires in <strong>7 days</strong>.
      </p>

      <div class="btn-wrap">
        <a href="{{ $setupUrl }}" class="btn">Accept Invite &amp; Set Up Account</a>
      </div>

      <div class="divider"></div>

      <p style="font-size:12px;color:#9ca3af;margin-bottom:8px">
        If the button doesn't work, copy and paste this link into your browser:
      </p>
      <div class="url-box">
        <a href="{{ $setupUrl }}">{{ $setupUrl }}</a>
      </div>
    </div>

    {{-- Footer --}}
    <div class="footer">
      This invite was sent by {{ $inviterName }} via {{ $tenantName }} on ReferralBunny.ai.<br>
      If you weren't expecting this, you can safely ignore this email.
    </div>

  </div>
</div>
</body>
</html>
