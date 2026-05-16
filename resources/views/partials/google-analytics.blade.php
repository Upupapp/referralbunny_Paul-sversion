@php $gaId = config('services.google_analytics_id'); @endphp
@if($gaId && app()->isProduction())
<!-- Google Analytics 4 -->
<script async src="https://www.googletagmanager.com/gtag/js?id={{ $gaId }}"></script>
<script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());
    gtag('config', '{{ $gaId }}', {
        // Scrub PII from page paths before sending to GA
        page_path: window.location.pathname,
        // User role dimension (no names/emails — just role type)
        custom_map: { dimension1: 'user_role' },
    });
    @auth('tenant')
    gtag('set', 'user_properties', { user_role: 'tenant_admin' });
    @endauth
    @auth('reseller')
    gtag('set', 'user_properties', { user_role: 'referrer' });
    @endauth
    @auth('partner')
    gtag('set', 'user_properties', { user_role: 'partner' });
    @endauth
    @auth('web')
    gtag('set', 'user_properties', { user_role: 'super_admin' });
    @endauth
</script>
@endif
