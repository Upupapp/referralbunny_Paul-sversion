<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $title }} · Referral Bunny</title>
<meta name="description" content="{{ $description }}">
<meta name="robots" content="noindex,nofollow">
<link rel="canonical" href="{{ $canonical }}">
<meta property="og:type" content="website"><meta property="og:site_name" content="Referral Bunny">
<meta property="og:title" content="{{ $title }}"><meta property="og:description" content="{{ $description }}">
<meta property="og:url" content="{{ $canonical }}">
<meta property="og:image" content="{{ asset('images/referral-share-banner.png') }}">
<meta property="og:image:width" content="1200"><meta property="og:image:height" content="630">
<meta property="og:image:type" content="image/png"><meta property="og:image:alt" content="An invitation worth sharing. Referral Bunny.">
<meta name="twitter:card" content="summary_large_image"><meta name="twitter:title" content="{{ $title }}">
<meta name="twitter:description" content="{{ $description }}"><meta name="twitter:image" content="{{ asset('images/referral-share-banner.png') }}">
<style>body{margin:0;padding:32px 16px;background:#f0fdfa;color:#134e4a;font:16px/1.6 system-ui,sans-serif}main{max-width:640px;margin:4vh auto;background:white;border:1px solid #ccfbf1;border-radius:24px;overflow:hidden;box-shadow:0 12px 40px #0d948811}img{width:100%;display:block}.content{padding:28px}h1{line-height:1.2;font-size:28px}p{color:#526f6b;overflow-wrap:anywhere}.button{display:inline-block;padding:12px 22px;border-radius:12px;background:#0f766e;color:white;text-decoration:none;font-weight:650}small{display:block;margin-top:18px;color:#526f6b}</style>
</head><body><main><img src="{{ asset('images/referral-share-banner.png') }}" alt="An invitation worth sharing. Referral Bunny."><div class="content">
<h1>{{ $title }}</h1><p>{{ $description }}</p>
@if($preview)<p>Short link: {{ $canonical }}</p><p>This referral link opens <strong>{{ $host }}</strong> and includes your program attribution.</p>@else<p>Taking you to {{ $host }}…</p>@endif
<a class="button" href="{{ $destination }}" rel="nofollow noreferrer">Continue to {{ $host }} →</a>
<small>Referral link powered by Referral Bunny</small></div></main>
@if(!$preview)<script>
(() => {
    const destination = {!! json_encode($destination, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!};
    const go = () => window.location.replace(destination);
    @if($clickToken)
    const body = new FormData();
    body.append('_token', @json(csrf_token()));
    body.append('token', @json($clickToken));
    // Keep navigation fast even if analytics is blocked or temporarily unavailable.
    const record = () => {
        Promise.race([
            fetch(@json($clickUrl), {method:'POST', body, credentials:'same-origin', keepalive:true}).catch(() => {}),
            new Promise(resolve => setTimeout(resolve,350))
        ]).finally(go);
    };
    if (document.prerendering) document.addEventListener('prerenderingchange',record,{once:true});
    else record();
    @else
    go();
    @endif
})();
</script>@endif
</body></html>
