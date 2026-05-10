<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Request Submitted — {{ $form->title }}</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',system-ui,sans-serif;background:#F0EFFA;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px 16px}
.card{max-width:480px;width:100%;background:white;border-radius:24px;box-shadow:0 8px 40px rgba(123,97,255,.12);padding:40px;text-align:center}
.icon{width:72px;height:72px;background:linear-gradient(135deg,#7B61FF,#5b4cdb);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;box-shadow:0 8px 24px rgba(123,97,255,.35)}
h1{font-size:22px;font-weight:800;color:#1E1B4B;margin-bottom:10px}
p{font-size:14px;color:#6b7280;line-height:1.6;margin-bottom:8px}
.ref{font-size:11px;color:#9ca3af;background:#f9fafb;border-radius:8px;padding:6px 12px;display:inline-block;margin-top:12px;font-family:monospace}
</style>
</head>
<body>
<div class="card">
    <div class="icon">
        <svg width="32" height="32" fill="none" stroke="white" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
        </svg>
    </div>
    <h1>Request Submitted!</h1>
    <p>{{ $form->success_message ?? 'Your request has been submitted successfully.' }}</p>
    <p style="margin-top:8px;font-size:13px;color:#9ca3af">The assigned person(s) will review your request and get back to you if needed.</p>
    @if(isset($submission) && $submission->public_submission_uuid)
    <div class="ref">Ref: {{ $submission->public_submission_uuid }}</div>
    @endif
    <p style="margin-top:24px;font-size:11px;color:#9ca3af">
        Powered by <a href="/" style="color:#7B61FF;text-decoration:none">ReferralBunny.ai</a>
    </p>
</div>
</body>
</html>
