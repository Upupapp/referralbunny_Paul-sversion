export default (config) => ({
    payments: 'unknown', account: 'checking', signups: 'unknown', checking: false, error: '', lastSignup: null, disconnecting: false, submitting: false,
    init() { if (config.enabled) this.check(); else this.account = 'unavailable'; },
    get accountLabel() { return {checking:'Checking connection…',connected:'Connected',not_connected:'Not connected',disconnected:'Disconnected',reconnect_required:'Reconnect required',unknown:'Unable to verify',unavailable:'Not available yet'}[this.account] || 'Unable to verify'; },
    get signupLabel() { return {ready: this.lastSignup ? 'Receiving referred signups' : 'Ready · Waiting for the first referred signup',paused:'Paused · Reconnect your account',not_connected:'Connect your account first',unknown:'Not verified'}[this.signups] || 'Not verified'; },
    get paymentLabel() { return {unknown:'Not verified',not_available:'Not enabled yet',authorization_required:'Reconnect to approve payment tracking',ready:'Enabled · Waiting for a confirmed payment',receiving:'Receiving subscription payments',attention:'Needs attention · A payment is held or failed'}[this.payments] || 'Not verified'; },
    get connectLabel() { return config.previouslyConnected || ['connected','disconnected','reconnect_required'].includes(this.account) ? 'Reconnect GetHired' : 'Connect GetHired'; },
    async check() {
        if (this.checking) return;
        this.checking = true; this.error = '';
        try {
            const response = await fetch(config.statusUrl, {headers:{Accept:'application/json'},cache:'no-store'});
            if (!response.ok) throw new Error('Connection could not be checked. Please try again.');
            const result = await response.json();
            this.payments = result.payments; this.account = result.account; this.signups = result.signups; this.lastSignup = result.lastSignupAt || null; this.error = result.message || '';
        } catch(e) { this.payments='unknown'; this.account='unknown'; this.signups='unknown'; this.error=e.message; }
        finally { this.checking = false; }
    },
});
