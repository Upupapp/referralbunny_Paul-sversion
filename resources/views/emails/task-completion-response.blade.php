<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><style>
body{font-family:'Inter',Arial,sans-serif;background:#f0effa;margin:0;padding:20px}
.wrap{max-width:560px;margin:0 auto;background:white;border-radius:16px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.08)}
.header{background:linear-gradient(135deg,#10B981,#059669);padding:32px 32px 24px;text-align:center}
.header h1{color:white;font-size:20px;margin:0;font-weight:700}
.header p{color:rgba(255,255,255,.8);font-size:13px;margin:6px 0 0}
.body{padding:28px 32px}
.badge{display:inline-block;padding:4px 14px;border-radius:9999px;background:#dcfce7;color:#15803d;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;margin-bottom:16px}
.body-text{font-size:15px;color:#374151;line-height:1.7;white-space:pre-wrap;background:#f9fafb;border-radius:12px;padding:18px;border:1px solid #f3f4f6;margin:16px 0}
.footer{padding:20px 32px;border-top:1px solid #f3f4f6;text-align:center;font-size:11px;color:#9ca3af}
</style></head>
<body>
<div class="wrap">
    <div class="header">
        <h1>&#x2705; Your Request Has Been Completed</h1>
        <p>A response has been prepared for you.</p>
    </div>
    <div class="body">
        <div class="badge">Response</div>
        <p style="color:#6b7280;font-size:14px;margin:0 0 16px">Hi {{ $recipientName }},</p>
        <p style="color:#6b7280;font-size:14px;margin:0 0 16px"><strong>{{ $senderName }}</strong> has completed your request and sent you a response.</p>

        <div class="body-text">{{ $body }}</div>

        @if(count($attachments) > 0)
        <p style="font-size:12px;color:#9ca3af;margin-top:16px">
            &#x1F4CE; {{ count($attachments) }} file(s) attached to this email.
        </p>
        @endif
    </div>
    <div class="footer">
        This response was sent regarding your request through ReferralBunny.ai.<br>
        Please do not reply to this email if it is a no-reply address.
    </div>
</div>
</body>
</html>
