<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ count($digest->items ?? []) }} {{ $digest->topic_label ?? ucwords(str_replace('_', ' ', $digest->topic)) }} update{{ count($digest->items ?? []) !== 1 ? 's' : '' }}</title>
<style>
    body { margin:0; padding:0; font-family:'Inter',Arial,sans-serif; background:#f3f4f6; color:#111827; }
    .wrap { max-width:600px; margin:0 auto; background:#fff; border-radius:12px; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,0.1); }
    .header { background:linear-gradient(135deg,#1E1B4B,#3730a3); padding:32px 40px; text-align:center; }
    .header img { height:36px; margin-bottom:16px; }
    .header h1 { color:#fff; margin:0; font-size:20px; font-weight:700; }
    .header p { color:rgba(255,255,255,0.7); margin:6px 0 0; font-size:14px; }
    .body { padding:32px 40px; }
    .summary-badge { display:inline-block; background:#ede9fe; color:#7B61FF; font-size:13px; font-weight:600; padding:6px 14px; border-radius:9999px; margin-bottom:20px; }
    .item { background:#f9fafb; border:1px solid #e5e7eb; border-radius:10px; padding:14px 18px; margin-bottom:10px; }
    .item-title { font-size:14px; font-weight:600; color:#1E1B4B; margin:0 0 4px; }
    .item-body { font-size:13px; color:#6b7280; margin:0; line-height:1.5; }
    .item-meta { font-size:11px; color:#9ca3af; margin:6px 0 0; }
    .cta { text-align:center; margin:28px 0 8px; }
    .btn { display:inline-block; background:linear-gradient(135deg,#7B61FF,#6d28d9); color:#fff; text-decoration:none; padding:12px 28px; border-radius:10px; font-size:14px; font-weight:600; }
    .footer { padding:20px 40px; border-top:1px solid #f3f4f6; text-align:center; }
    .footer p { font-size:12px; color:#9ca3af; margin:0; }
    @media (max-width:640px) { .body,.header,.footer { padding:24px 20px !important; } }
</style>
</head>
<body>
<div class="wrap">
    <div class="header">
        <img src="https://referralbunny.ai/images/logos/referralbunny-logo-white-horizontal.webp" alt="ReferralBunny.ai">
        <h1>
            @php $count = count($digest->items ?? []); @endphp
            {{ $count }} {{ $digest->topic_label ?? ucwords(str_replace('_', ' ', $digest->topic)) }} Update{{ $count !== 1 ? 's' : '' }}
        </h1>
        <p>Hi {{ $digest->recipient_name ?? 'there' }}, here's your activity summary.</p>
    </div>

    <div class="body">
        <span class="summary-badge">
            {{ $count }} item{{ $count !== 1 ? 's' : '' }} · {{ now()->format('M j, Y') }}
        </span>

        @foreach($digest->items ?? [] as $item)
        <div class="item">
            @if(!empty($item['title']))
            <p class="item-title">{{ $item['title'] }}</p>
            @endif
            @if(!empty($item['body']))
            <p class="item-body">{{ $item['body'] }}</p>
            @elseif(!empty($item['deal_name']))
            <p class="item-body">{{ $item['deal_name'] }}</p>
            @endif
            @php
                $meta = collect($item)->only(['stage','amount','referrer','actor','date','municipality','province'])->filter()->map(fn($v, $k) => ucwords(str_replace('_',' ',$k)).': '.$v)->implode(' · ');
            @endphp
            @if($meta)
            <p class="item-meta">{{ $meta }}</p>
            @endif
        </div>
        @endforeach

        @if(!empty($digest->items[0]['action_url']))
        <div class="cta">
            <a href="{{ $digest->items[0]['action_url'] }}" class="btn">View in ReferralBunny →</a>
        </div>
        @endif
    </div>

    <div class="footer">
        <p>ReferralBunny.ai — You received this because you are part of a workspace.<br>
        These updates were grouped to reduce email noise.</p>
    </div>
</div>
</body>
</html>
