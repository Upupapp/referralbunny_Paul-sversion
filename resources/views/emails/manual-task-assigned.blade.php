<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><style>
body{font-family:'Inter',Arial,sans-serif;background:#f0effa;margin:0;padding:20px}
.wrap{max-width:560px;margin:0 auto;background:white;border-radius:16px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.08)}
.header{background:linear-gradient(135deg,#7B61FF,#5b4cdb);padding:32px 32px 24px;text-align:center}
.header h1{color:white;font-size:20px;margin:0;font-weight:700}
.header p{color:rgba(255,255,255,.8);font-size:13px;margin:6px 0 0}
.body{padding:28px 32px}
.badge{display:inline-block;padding:4px 14px;border-radius:9999px;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;margin-bottom:16px}
.badge.urgent{background:#fef2f2;color:#dc2626}
.badge.high{background:#fff7ed;color:#ea580c}
.badge.medium{background:#fffbeb;color:#d97706}
.badge.low{background:#f3f4f6;color:#6b7280}
h2{color:#1E1B4B;font-size:17px;margin:0 0 16px}
.field{margin-bottom:12px}
.field label{display:block;font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.06em;margin-bottom:3px}
.field span{font-size:14px;color:#374151}
.task-box{background:#f9fafb;border-radius:12px;padding:16px;border-left:3px solid #7B61FF;margin:16px 0}
.task-title{font-size:16px;font-weight:700;color:#1E1B4B;margin:0 0 8px}
.cta-wrap{text-align:center;margin-top:24px}
.cta{display:inline-block;padding:12px 28px;background:linear-gradient(135deg,#7B61FF,#5b4cdb);color:white;border-radius:12px;text-decoration:none;font-weight:700;font-size:14px}
.footer{padding:20px 32px;border-top:1px solid #f3f4f6;text-align:center;font-size:11px;color:#9ca3af}
</style></head>
<body>
<div class="wrap">
    <div class="header">
        <h1>&#x1F4CB; Task Assigned to You</h1>
        <p>You have a new task waiting for your action.</p>
    </div>
    <div class="body">
        <span class="badge {{ $taskPriority }}">{{ ucfirst($taskPriority) }} Priority</span>
        <h2>Hi {{ $assigneeName }},</h2>
        <p style="color:#6b7280;font-size:14px;margin:0 0 20px">
            <strong>{{ $senderName }}</strong> has assigned you a new task.
        </p>

        <div class="task-box">
            <p class="task-title">{{ $taskTitle }}</p>
            @if($dueAt)
            <p style="font-size:12px;color:#9ca3af;margin:0">Due: {{ $dueAt }}</p>
            @endif
        </div>

        <div class="cta-wrap">
            <a href="{{ $taskUrl }}" class="cta">View Task &rarr;</a>
        </div>
    </div>
    <div class="footer">
        This email was sent by ReferralBunny.ai. Log in to manage your tasks.
    </div>
</div>
</body>
</html>
