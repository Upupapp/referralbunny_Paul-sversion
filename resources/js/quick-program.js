export default function quickProgram(config) {
    return {
        needed: true, step: 1, busy: false, error: '', saveLabel: '', analysis: null, confirmed: false,
        form: { website: '', name: `${config.company} Referrals`.slice(0,120), pricing_model: 'unknown', price: '', currency: ['PHP','USD','EUR','GBP','AUD','SGD','CAD'].includes(config.currency) ? config.currency : 'USD', option: 'first', reward_model: 'percentage', reward_value: 20, reward_scope: 'first_payment', duration_months: 6, hold_days: 30 },
        init() {
            this.restore();
            this.$watch('form', () => {
                this.confirmed = false;
                clearTimeout(this.saveTimer);
                this.saveTimer = setTimeout(() => this.save(), 700);
            });
        },
        async api(url, method = 'GET', body = null) {
            const res = await fetch(url, {method, credentials: 'same-origin', headers: {'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content}, ...(body ? {body: JSON.stringify(body)} : {})});
            const data = await res.json().catch(() => ({}));
            if (res.status === 429) {
                const seconds = Math.max(1, Number(res.headers.get('Retry-After')) || 60);
                throw new Error(`Please wait ${seconds} seconds before trying this action again. Your setup details are still here.`);
            }
            if (!res.ok) throw new Error(Object.values(data.errors || {}).flat().join(' ') || data.message || 'Unable to save. Please try again.');
            return data;
        },
        async restore() {
            try {
                const data = await this.api(config.status);
                this.needed = data.needed;
                if (data.draft) { this.form = {...this.form, ...data.draft}; this.step = 2; }
                if (this.needed) this.open();
            } catch (e) { this.error = e.message; this.open(); }
        },
        open() { this.$refs.dialog.showModal(); },
        minimize() { this.$refs.dialog.close(); this.$nextTick(() => this.$refs.resume.focus()); },
        async analyze() {
            if (this.busy) return;
            this.busy = true; this.error = '';
            try {
                this.analysis = await this.api(config.analyze, 'POST', {website: this.form.website});
                this.form.website = this.analysis.website;
                this.form.pricing_model = this.analysis.pricing_model;
                if (this.analysis.company_name) this.form.name = `${this.analysis.company_name} Referrals`.slice(0,120);
                // Never select an unverified price automatically; the admin chooses it explicitly.
                this.step = 2;
                await this.save();
            } catch (e) { this.error = e.message; }
            finally { this.busy = false; }
        },
        async manual() {
            if (!this.form.website) { this.error = 'Enter your company domain first.'; return; }
            if (await this.save(true)) { this.step = 2; this.error = ''; }
        },
        choose(option) {
            this.form.option = option;
            if (option === 'first') Object.assign(this.form, {reward_model:'percentage',reward_value:20,reward_scope:'first_payment'});
            if (option === 'recurring') {
                if (this.form.pricing_model === 'subscription') Object.assign(this.form, {reward_model:'percentage',reward_value:10,reward_scope:'recurring',duration_months:6});
                else Object.assign(this.form, {reward_model:'fixed',reward_value:this.form.price ? Math.max(1, Math.round(Number(this.form.price)*.1)) : 10,reward_scope:'first_payment'});
            }
        },
        get payout() { return this.form.reward_model === 'percentage' ? Number(this.form.price)*Number(this.form.reward_value)/100 : Number(this.form.reward_value); },
        async save(showError = false) {
            if (!this.form.website || !this.form.name || !this.needed || this.publishing) return false;
            // Serialize writes to prevent an older autosave overwriting the final review.
            const payload = {...this.form, price: this.form.price === '' ? null : this.form.price};
            this.saveChain = (this.saveChain || Promise.resolve()).catch(() => {}).then(() => this.api(config.draft, 'PUT', payload));
            try { await this.saveChain; this.saveLabel = 'Progress saved'; return true; }
            catch (e) { this.saveLabel = 'Not saved'; if(showError) this.error = e.message; return false; }
        },
        async review() { if (await this.save(true)) { this.step = 3; this.error = ''; } },
        async publish() {
            if (this.busy || !this.confirmed) return;
            this.busy = true; this.publishing = true; this.error = ''; clearTimeout(this.saveTimer);
            try {
                await (this.saveChain || Promise.resolve()).catch(() => {});
                const result = await this.api(config.publish, 'POST', {...this.form, price:this.form.price === '' ? null : this.form.price, confirmed:true});
                this.needed = false; window.location.assign(result.redirect);
            } catch (e) { this.error = e.message; this.busy = false; this.publishing = false; }
        },
    };
}
