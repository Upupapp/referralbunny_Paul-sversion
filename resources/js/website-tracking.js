export default (config) => ({
    installed: false, lastSeen: null, origin: null, origins: '', error: '', message: '', busy: false, timer: null,
    async init() {
        await this.check();
        let remaining = 24;
        this.timer = setInterval(() => {
            if (--remaining <= 0 || this.installed) { clearInterval(this.timer); return; }
            if (!document.hidden) this.check();
        }, 5000);
    },
    destroy() { clearInterval(this.timer); },
    async check() {
        if (this.busy) return;
        this.busy = true; this.error = '';
        try {
            const res = await fetch(config.statusUrl, { headers: { Accept: 'application/json' }, cache: 'no-store' });
            if (!res.ok) throw new Error('Could not check installation. Please try again.');
            const data = await res.json();
            this.installed = data.installed; this.lastSeen = data.last_seen_at; this.origin = data.origin;
            if (!this.origins) this.origins = data.origins.join('\n');
        } catch (e) { this.error = e.message; } finally { this.busy = false; }
    },
    async copy() {
        try { await navigator.clipboard.writeText(this.$refs.snippet.value); this.message = 'Snippet copied'; }
        catch (_) { this.$refs.snippet.focus(); this.$refs.snippet.select(); this.message = 'Select and copy the snippet below.'; }
    },
    async saveOrigins() {
        this.error = ''; this.message = '';
        try {
            const res = await fetch(config.originsUrl, { method: 'PUT', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }, body: JSON.stringify({ origins: this.origins.split(/\n/).map(s => s.trim()).filter(Boolean) }) });
            const data = await res.json();
            if (!res.ok) throw new Error(Object.values(data.errors || {}).flat()[0] || data.message || 'Could not save websites.');
            this.origins = data.origins.join('\n'); this.installed = data.installed; this.lastSeen = data.last_seen_at; this.origin = data.origin;
            this.message = 'Allowed websites saved.';
        } catch (e) { this.error = e.message; }
    }
});
