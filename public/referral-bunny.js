/* Referral Bunny website capture. Public identifiers only; never include signing keys. */
(function () {
    'use strict';
    var script = document.currentScript;
    if (!script) return;
    var connection = script.dataset.connection, program = script.dataset.program;
    if (!connection || !program) return;
    var api = window.ReferralBunny = window.ReferralBunny || {};
    var clients = api.programs = api.programs || {};
    if (clients[program]) return;
    var key = 'referralbunny:' + connection, memory = null, started = false;
    var consent = script.dataset.consent !== 'required';
    function read() {
        if (!consent) return null;
        var value = memory;
        try { value = JSON.parse(localStorage.getItem(key)) || memory; } catch (_) {}
        if (!value || value.program_id !== program || !value.membership_id || !Number.isFinite(value.expires_at) || value.expires_at <= Date.now()) return null;
        return Object.assign({}, value);
    }
    async function capture() {
        if (started || !consent) return read();
        started = true;
        try {
            var params = new URLSearchParams(location.search);
            var member = params.get('rb_program') === program ? params.get('rb_ref') : null;
            if (member && !/^[a-zA-Z0-9_-]{1,100}$/.test(member)) member = null;
            var response = await fetch(new URL('/tracking/connections/' + encodeURIComponent(connection) + '/visit', script.src), {
                method: 'POST', credentials: 'omit', referrerPolicy: 'no-referrer',
                headers: { 'Content-Type': 'text/plain' },
                body: JSON.stringify({ program_id: program, membership_id: member })
            });
            if (!response.ok) return read();
            var result = await response.json();
            if (result.program_id === program && result.referral && result.referral.membership_id === member) {
                var existing = read();
                // Preserve the original arrival for repeated visits from the same referrer.
                if (!existing || existing.membership_id !== member) {
                    var now = Date.now();
                    memory = { program_id: program, membership_id: member, referred_at: new Date(now).toISOString(),
                        expires_at: now + Math.min(365, Math.max(1, Number(result.attribution_window_days) || 30)) * 86400000 };
                    try { localStorage.setItem(key, JSON.stringify(memory)); } catch (_) {}
                }
            }
        } catch (_) { /* Tracking must never break the host website. */ }
        return read();
    }
    var client = clients[program] = { getReferral: read, consent: function () { consent = true; return client.ready = capture(); } };
    api.getReferral = function (id) { var names = Object.keys(clients); return clients[id || (names.length === 1 ? names[0] : '')]?.getReferral() || null; };
    client.ready = capture();
})();
