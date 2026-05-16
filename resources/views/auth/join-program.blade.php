<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @include('partials.google-analytics')
    <title>Join a Referral Program — ReferralBunny.ai</title>
    <link rel="icon" type="image/webp" href="/images/logos/referralbunny-favicon.webp">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0; padding: 0; min-height: 100vh;
            font-family: 'Inter', sans-serif;
            background: linear-gradient(145deg, #1E1347 0%, #2D1B69 55%, #1a1040 100%);
            display: flex; align-items: center; justify-content: center;
        }
        .login-page {
            width: 100%; padding: 1.5rem;
            display: flex; align-items: center; justify-content: center; min-height: 100vh;
        }
        .login-card {
            width: 100%; max-width: 900px; display: flex;
            border-radius: 24px; overflow: hidden; box-shadow: 0 32px 80px rgba(0,0,0,.45); min-height: 580px;
        }
        .card-left {
            flex: 1; background: #ffffff;
            display: flex; flex-direction: column; justify-content: center;
            padding: 3rem;
        }
        .card-right {
            width: 360px; flex-shrink: 0;
            background: linear-gradient(145deg, #1E1347 0%, #2D1B69 60%, #1a1040 100%);
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            padding: 2.5rem; text-align: center; position: relative; overflow: hidden;
        }
        @media (max-width: 767px) {
            .card-right { display: none !important; }
            .card-left  { padding: 2.5rem 1.75rem; }
        }
        .form-wrap { max-width: 360px; }
        .field-label { display: block; font-size: .8125rem; font-weight: 500; color: #374151; margin-bottom: .375rem; }
        .field-input {
            width: 100%; border: 1px solid #e5e7eb; background: #f9fafb;
            border-radius: 12px; padding: .75rem 1rem; font-size: .875rem;
            color: #111827; outline: none; font-family: 'Inter', sans-serif;
            transition: border-color .15s, box-shadow .15s, background .15s;
        }
        .field-input:focus { border-color: #8b5cf6; box-shadow: 0 0 0 3px rgba(139,92,246,.15); background: #fff; }
        .field-input::placeholder { color: #9ca3af; }
        .btn-primary {
            width: 100%; background: #7c3aed; color: #fff; border: none;
            border-radius: 12px; padding: .75rem 1rem; font-size: .875rem;
            font-weight: 600; cursor: pointer; transition: background .15s;
            font-family: 'Inter', sans-serif; margin-top: .375rem;
        }
        .btn-primary:hover  { background: #6d28d9; }
        .btn-primary:active { background: #5b21b6; }
        .btn-primary:disabled { opacity: .5; cursor: not-allowed; }
        .tab-btn {
            flex: 1; border: 1px solid #e5e7eb; background: #fff;
            border-radius: 10px; padding: .625rem .5rem; font-size: .75rem; font-weight: 500;
            color: #6b7280; cursor: pointer; text-align: center; transition: all .15s;
            font-family: 'Inter', sans-serif;
        }
        .tab-btn.active { border-color: #7c3aed; background: #f5f3ff; color: #7c3aed; font-weight: 600; }
        .tab-btn:hover:not(.active) { border-color: #d1d5db; color: #374151; }
        .result-box {
            background: #f5f3ff; border: 1px solid #ddd6fe;
            border-radius: 12px; padding: 1rem; margin-top: .75rem;
        }
        .error-box {
            display: flex; align-items: flex-start; gap: .75rem;
            background: #fef2f2; border: 1px solid #fecaca;
            border-radius: 12px; padding: .75rem 1rem; margin-top: .75rem;
        }
        .error-box p { margin: 0; font-size: .875rem; color: #dc2626; }
        .success-box {
            display: flex; align-items: flex-start; gap: .75rem;
            background: #f0fdf4; border: 1px solid #bbf7d0;
            border-radius: 12px; padding: .75rem 1rem; margin-top: .75rem;
        }
        .success-box p { margin: 0; font-size: .875rem; color: #166534; }
    </style>
</head>
<body>

<div class="login-page">
    <div class="login-card">

        {{-- LEFT — join options --}}
        <div class="card-left" x-data="joinFlow()">

            <div style="margin-bottom:2rem">
                <x-rb-logo variant="horizontal" size="sm" :priority="true" :decorative="true" />
            </div>

            <h1 style="margin:0 0 .25rem;font-size:1.5rem;font-weight:700;color:#111827">Join a referral program</h1>
            <p style="margin:0 0 1.75rem;font-size:.8125rem;color:#6b7280;line-height:1.5">Use your invite link, a program code, or search by company name.</p>

            {{-- Tab selector --}}
            <div class="form-wrap" style="display:flex;gap:.5rem;margin-bottom:1.5rem">
                <button type="button" @click="tab = 'invite'" :class="tab === 'invite' ? 'active' : ''" class="tab-btn">
                    Invite Link
                </button>
                <button type="button" @click="tab = 'code'" :class="tab === 'code' ? 'active' : ''" class="tab-btn">
                    Program Code
                </button>
                <button type="button" @click="tab = 'search'" :class="tab === 'search' ? 'active' : ''" class="tab-btn">
                    Search
                </button>
            </div>

            {{-- ── Tab: Invite Link ─────────────────────────── --}}
            <div x-show="tab === 'invite'" class="form-wrap">
                <label class="field-label">Your invite link or code</label>
                <input x-model="invite.token" type="text" class="field-input"
                       placeholder="Paste your invite link here…"
                       @keydown.enter.prevent="lookupInvite()">
                <p style="font-size:.6875rem;color:#9ca3af;margin:.25rem 0 0">Check your email for an invite from a program admin.</p>

                <template x-if="invite.error">
                    <div class="error-box">
                        <x-r-bunny variant="warning" size="xs" :decorative="true" style="flex-shrink:0;margin-top:2px" />
                        <p x-text="invite.error"></p>
                    </div>
                </template>

                <template x-if="invite.result">
                    <div class="result-box">
                        <p style="font-size:.8125rem;font-weight:600;color:#1E1B4B;margin:0 0 .25rem" x-text="invite.result.label"></p>
                        <p style="font-size:.75rem;color:#6b7280;margin:0 0 .75rem" x-text="invite.result.message"></p>
                        <a :href="invite.result.url"
                           style="display:inline-block;background:#7c3aed;color:#fff;border-radius:10px;padding:.625rem 1.25rem;font-size:.8125rem;font-weight:600;text-decoration:none">
                            Continue →
                        </a>
                    </div>
                </template>

                <button type="button" @click="lookupInvite()" class="btn-primary" :disabled="loading || !invite.token.trim()">
                    <span x-text="loading ? 'Checking…' : 'Continue with Invite'"></span>
                </button>
            </div>

            {{-- ── Tab: Program Code ────────────────────────── --}}
            <div x-show="tab === 'code'" class="form-wrap">
                <label class="field-label">Program code or workspace ID</label>
                <input x-model="code.value" type="text" class="field-input"
                       placeholder="e.g. acme-referral or ACME2025"
                       @keydown.enter.prevent="lookupCode()">
                <p style="font-size:.6875rem;color:#9ca3af;margin:.25rem 0 0">Your program admin can provide this code.</p>

                <template x-if="code.error">
                    <div class="error-box">
                        <x-r-bunny variant="warning" size="xs" :decorative="true" style="flex-shrink:0;margin-top:2px" />
                        <p x-text="code.error"></p>
                    </div>
                </template>

                <template x-if="code.result">
                    <div class="result-box">
                        <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:.75rem">
                            <div style="width:36px;height:36px;border-radius:10px;background:#7c3aed;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                                <svg width="18" height="18" fill="none" stroke="white" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                            </div>
                            <div>
                                <p style="font-size:.875rem;font-weight:700;color:#1E1B4B;margin:0" x-text="code.result.name"></p>
                                <p style="font-size:.75rem;color:#6b7280;margin:0" x-text="code.result.industry"></p>
                            </div>
                        </div>
                        <button @click="requestAccess(code.result.id)"
                                class="btn-primary" style="margin-top:0" :disabled="loading">
                            <span x-text="loading ? 'Sending request…' : 'Request Access'"></span>
                        </button>
                    </div>
                </template>

                <template x-if="code.accessSent">
                    <div class="success-box">
                        <p>Your access request was sent. The program admin will review it shortly.</p>
                    </div>
                </template>

                <button x-show="!code.result && !code.accessSent" type="button"
                        @click="lookupCode()" class="btn-primary" :disabled="loading || !code.value.trim()">
                    <span x-text="loading ? 'Searching…' : 'Find Program'"></span>
                </button>
            </div>

            {{-- ── Tab: Search ──────────────────────────────── --}}
            <div x-show="tab === 'search'" class="form-wrap">
                <label class="field-label">Company or organization name</label>
                <input x-model="search.query" type="text" class="field-input"
                       placeholder="e.g. Acme Corporation"
                       @keydown.enter.prevent="searchPrograms()">
                <label class="field-label" style="margin-top:.75rem">Your work email <span style="color:#9ca3af;font-weight:400">(optional)</span></label>
                <input x-model="search.email" type="email" class="field-input" placeholder="you@company.com">
                <p style="font-size:.6875rem;color:#9ca3af;margin:.25rem 0 0">Helps the admin identify and approve your request.</p>

                <template x-if="search.error">
                    <div class="error-box">
                        <x-r-bunny variant="warning" size="xs" :decorative="true" style="flex-shrink:0;margin-top:2px" />
                        <p x-text="search.error"></p>
                    </div>
                </template>

                <template x-if="search.result">
                    <div class="result-box">
                        <p style="font-size:.8125rem;font-weight:600;color:#1E1B4B;margin:0 0 .25rem" x-text="search.result.name"></p>
                        <p style="font-size:.75rem;color:#6b7280;margin:0 0 .75rem" x-text="search.result.industry + ' · ' + search.result.country"></p>
                        <button @click="requestAccess(search.result.id)"
                                class="btn-primary" style="margin-top:0" :disabled="loading">
                            <span x-text="loading ? 'Sending request…' : 'Request to Join'"></span>
                        </button>
                    </div>
                </template>

                <template x-if="search.accessSent">
                    <div class="success-box">
                        <p>Your request was sent. The program admin will review it shortly.</p>
                    </div>
                </template>

                <template x-if="search.noResult">
                    <div class="error-box">
                        <p>No matching program found. Check the company name or ask the admin for an invite link.</p>
                    </div>
                </template>

                <button x-show="!search.result && !search.accessSent && !search.noResult"
                        type="button" @click="searchPrograms()" class="btn-primary"
                        :disabled="loading || !search.query.trim()">
                    <span x-text="loading ? 'Searching…' : 'Search Program'"></span>
                </button>
            </div>

            {{-- Bottom links --}}
            <div style="margin-top:1.75rem;padding-top:1.25rem;border-top:1px solid #f3f4f6" class="form-wrap">
                <div style="display:flex;flex-direction:column;gap:.5rem">
                    <a href="{{ route('tenant.login') }}"
                       style="font-size:.75rem;color:#7c3aed;text-decoration:none"
                       onmouseover="this.style.textDecoration='underline'" onmouseout="this.style.textDecoration='none'">
                        Already have an account? Sign in →
                    </a>
                    <a href="{{ route('reseller.login') }}"
                       style="font-size:.75rem;color:#9ca3af;text-decoration:none"
                       onmouseover="this.style.color='#6b7280'" onmouseout="this.style.color='#9ca3af'">
                        Sign in as a referrer / reseller →
                    </a>
                    <a href="{{ route('tenant.create') }}"
                       style="font-size:.75rem;color:#9ca3af;text-decoration:none"
                       onmouseover="this.style.color='#6b7280'" onmouseout="this.style.color='#9ca3af'">
                        Build your own program instead →
                    </a>
                </div>
            </div>

            <div style="margin-top:.875rem" class="form-wrap">
                <a href="{{ route('portal.select') }}"
                   style="display:inline-flex;align-items:center;gap:.375rem;font-size:.75rem;color:#9ca3af;text-decoration:none;transition:color .15s"
                   onmouseover="this.style.color='#7B61FF'" onmouseout="this.style.color='#9ca3af'">
                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Back
                </a>
            </div>
        </div>

        {{-- RIGHT — brand panel --}}
        <div class="card-right">
            <div style="position:absolute;width:260px;height:260px;border-radius:50%;top:-80px;right:-80px;opacity:.25;pointer-events:none;background:radial-gradient(circle,#7C3AED,transparent 65%)"></div>
            <div style="position:absolute;width:200px;height:200px;border-radius:50%;bottom:-60px;left:-60px;opacity:.2;pointer-events:none;background:radial-gradient(circle,#3B82F6,transparent 65%)"></div>
            <div style="position:relative;z-index:1">
                <x-r-bunny variant="waving" size="lg" :decorative="true" style="display:block;margin:0 auto 1.25rem" />
                <h2 style="margin:0 0 .5rem;font-size:1.125rem;font-weight:700;color:#fff;line-height:1.4">Join and start<br>earning commissions.</h2>
                <p style="font-size:.75rem;color:rgba(255,255,255,.5);line-height:1.6;max-width:180px;margin:0 auto">Accept an invite, submit deals, and track your commissions.</p>
            </div>
        </div>
    </div>
</div>

<script>
function joinFlow() {
    return {
        tab: 'invite',
        loading: false,
        invite: { token: '', error: null, result: null },
        code:   { value: '', error: null, result: null, accessSent: false },
        search: { query: '', email: '', error: null, result: null, noResult: false, accessSent: false },

        async lookupInvite() {
            if (!this.invite.token.trim() || this.loading) return;
            this.loading = true;
            this.invite.error = null;
            this.invite.result = null;

            // Extract token from URL if full link is pasted
            let token = this.invite.token.trim();
            const urlMatch = token.match(/(?:setup|invite|join)[?\/].*?token=([^&\s]+)/i)
                          || token.match(/\/([a-zA-Z0-9_-]{20,})\s*$/);
            if (urlMatch) token = urlMatch[1];

            // Try reseller setup token first, then tenant invite
            try {
                const r = await fetch(`/api/auth/reseller/lookup-token?token=${encodeURIComponent(token)}`, {
                    headers: { 'Accept': 'application/json' }
                });
                if (r.ok) {
                    const d = await r.json();
                    if (d.valid) {
                        this.invite.result = {
                            label:   'Referrer invite found!',
                            message: `You've been invited to join ${d.tenant_name ?? 'a referral program'}.`,
                            url:     `/reseller/setup?token=${encodeURIComponent(token)}`,
                        };
                        this.loading = false;
                        return;
                    }
                }
            } catch(e) {}

            // Try tenant invite
            try {
                const r = await fetch(`/api/auth/tenant/invite/${encodeURIComponent(token)}`, {
                    headers: { 'Accept': 'application/json' }
                });
                if (r.ok) {
                    const d = await r.json();
                    if (d.valid !== false) {
                        this.invite.result = {
                            label:   'Admin invite found!',
                            message: `You've been invited to join ${d.tenant_name ?? 'a workspace'}.`,
                            url:     `/tenant/invite/${encodeURIComponent(token)}`,
                        };
                        this.loading = false;
                        return;
                    }
                }
            } catch(e) {}

            this.invite.error = 'This invite link is invalid or has expired. Please check your email or contact the program admin.';
            this.loading = false;
        },

        async lookupCode() {
            if (!this.code.value.trim() || this.loading) return;
            this.loading = true;
            this.code.error = null;
            this.code.result = null;
            this.code.accessSent = false;
            try {
                const r = await fetch(`/api/auth/tenant/lookup-tenant?code=${encodeURIComponent(this.code.value.trim())}`, {
                    headers: { 'Accept': 'application/json' }
                });
                const d = await r.json();
                if (d.id) {
                    this.code.result = { id: d.id, name: d.name, industry: d.industry ?? '', country: d.country ?? '' };
                } else {
                    this.code.error = 'We couldn\'t find a program with that code. Double-check or ask your admin.';
                }
            } catch(e) {
                this.code.error = 'Something went wrong. Please try again.';
            }
            this.loading = false;
        },

        async searchPrograms() {
            if (!this.search.query.trim() || this.loading) return;
            this.loading = true;
            this.search.error = null;
            this.search.result = null;
            this.search.noResult = false;
            this.search.accessSent = false;
            try {
                const params = new URLSearchParams({ name: this.search.query.trim() });
                if (this.search.email) params.set('email', this.search.email);
                const r = await fetch(`/api/auth/tenant/lookup-tenant?${params}`, {
                    headers: { 'Accept': 'application/json' }
                });
                const d = await r.json();
                if (d.id) {
                    this.search.result = { id: d.id, name: d.name, industry: d.industry ?? '', country: d.country ?? '' };
                } else {
                    this.search.noResult = true;
                }
            } catch(e) {
                this.search.error = 'Something went wrong. Please try again.';
            }
            this.loading = false;
        },

        async requestAccess(tenantId) {
            if (this.loading) return;
            this.loading = true;
            try {
                await fetch('/api/auth/tenant/join-request', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({ tenant_id: tenantId, email: this.search.email || null }),
                });
                if (this.tab === 'code')   this.code.accessSent   = true;
                if (this.tab === 'search') this.search.accessSent = true;
                this.code.result   = null;
                this.search.result = null;
            } catch(e) {}
            this.loading = false;
        },
    };
}
</script>

</body>
</html>
