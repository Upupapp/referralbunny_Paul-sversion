<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><style>
body{font-family:'Inter',Arial,sans-serif;background:#f0effa;margin:0;padding:20px}
.wrap{max-width:560px;margin:0 auto;background:white;border-radius:16px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.08)}
.header{background:linear-gradient(135deg,#7B61FF,#5b4cdb);padding:32px 32px 24px;text-align:center}
.header h1{color:white;font-size:20px;margin:0;font-weight:700}
.header p{color:rgba(255,255,255,.8);font-size:13px;margin:6px 0 0}
.body{padding:28px 32px}
.badge{display:inline-block;padding:4px 14px;border-radius:9999px;background:#ede9fe;color:#7B61FF;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;margin-bottom:16px}
h2{color:#1E1B4B;font-size:17px;margin:0 0 16px}
.field{margin-bottom:12px}
.field label{display:block;font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.06em;margin-bottom:3px}
.field span{font-size:14px;color:#374151}
.notes-box{background:#f9fafb;border-radius:10px;padding:14px;border-left:3px solid #7B61FF;margin:16px 0;font-size:13px;color:#374151;line-height:1.6}
.cta-wrap{text-align:center;margin-top:24px}
.cta{display:inline-block;padding:12px 28px;background:linear-gradient(135deg,#7B61FF,#5b4cdb);color:white;border-radius:12px;text-decoration:none;font-weight:700;font-size:14px}
.footer{padding:20px 32px;border-top:1px solid #f3f4f6;text-align:center;font-size:11px;color:#9ca3af}
</style></head>
<body>
<div class="wrap">
    <div class="header">
        <h1>&#x1F4CB; New Request Assigned</h1>
        <p>You have a new request waiting for your attention.</p>
    </div>
    <div class="body">
        <div class="badge">Request Form</div>
        <h2>Hi {{ $recipientName }},</h2>
        <p style="color:#6b7280;font-size:14px;margin:0 0 20px">A new request has been submitted and assigned to you via <strong>{{ $formTitle }}</strong>.</p>

        <div class="field">
            <label>From</label>
            <span>{{ $submitterName }}</span>
        </div>
        <div class="field">
            <label>Request For</label>
            <span>{{ $requestFor }}</span>
        </div>
        @if($notes)
        <div class="field">
            <label>Notes</label>
        </div>
        <div class="notes-box">{{ $notes }}</div>
        @endif

        <div class="cta-wrap">
            <a href="{{ url('/tenant/' . $tenantId . '/tasks') }}" class="cta">View Your Tasks &rarr;</a>
        </div>
    </div>
    <div class="footer">
        This email was sent by ReferralBunny.ai on behalf of your tenant. Log in to manage this request.
    </div>
</div>
</body>
</html>
