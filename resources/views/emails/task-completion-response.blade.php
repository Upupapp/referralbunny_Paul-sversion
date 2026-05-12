<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
body{font-family:'Inter',Arial,sans-serif;background:#f0effa;margin:0;padding:20px}
.wrap{max-width:580px;margin:0 auto;background:white;border-radius:16px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.08)}
.header{background:linear-gradient(135deg,#10B981,#059669);padding:32px 32px 24px;text-align:center}
.header h1{color:white;font-size:20px;margin:0;font-weight:700;line-height:1.3}
.header p{color:rgba(255,255,255,.8);font-size:13px;margin:6px 0 0}
.body{padding:28px 32px}
.badge{display:inline-block;padding:4px 14px;border-radius:9999px;background:#dcfce7;color:#15803d;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;margin-bottom:16px}
.body-text{font-size:14px;color:#374151;line-height:1.75;background:#f9fafb;border-radius:12px;padding:18px 20px;border:1px solid #f3f4f6;margin:16px 0;word-break:break-word}
.body-text a{color:#7B61FF;text-decoration:underline;word-break:break-all}
.footer{padding:20px 32px;border-top:1px solid #f3f4f6;text-align:center;font-size:11px;color:#9ca3af;line-height:1.6}
</style>
</head>
<body>
<div class="wrap">
    <div class="header">
        <h1>&#x2705; Task Completion Response</h1>
        <p>{{ $senderName }} has responded to your request.</p>
    </div>
    <div class="body">
        <div class="badge">Response</div>
        <p style="color:#6b7280;font-size:14px;margin:0 0 10px">Hi {{ $recipientName }},</p>
        <p style="color:#6b7280;font-size:14px;margin:0 0 16px">
            <strong style="color:#1E1B4B">{{ $senderName }}</strong> has completed your request
            @if(!empty($taskTitle)) regarding <em>{{ $taskTitle }}</em>@endif
            and sent you the following response.
        </p>

        {{-- Body rendered with safe HTML (links clickable, line breaks preserved) --}}
        <div class="body-text">{!! $bodyHtml ?? nl2br(e($bodyPlain ?? $body)) !!}</div>

        @if(count($attachments) > 0)
        <p style="font-size:13px;color:#6b7280;margin-top:16px;display:flex;align-items:center;gap:6px">
            &#x1F4CE; <strong>{{ count($attachments) }}</strong> file(s) attached to this email.
        </p>
        @endif

        <p style="font-size:12px;color:#9ca3af;margin-top:20px;padding-top:16px;border-top:1px solid #f3f4f6">
            Links in the message above are clickable. If your email client does not display them, copy and paste the URL into your browser.
        </p>
    </div>
    <div class="footer">
        This response was sent regarding your request submitted through ReferralBunny.ai.<br>
        If you did not submit a request, please disregard this email.
    </div>
</div>
</body>
</html>
