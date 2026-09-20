@props(['tenant'])
<div x-data="quickProgram(@js([
    'company' => $tenant->name, 'currency' => $tenant->preferred_currency ?? 'PHP',
    'status' => route('tenant.quick-program.status', $tenant->id),
    'analyze' => route('tenant.quick-program.analyze', $tenant->id),
    'draft' => route('tenant.quick-program.draft', $tenant->id),
    'publish' => route('tenant.quick-program.publish', $tenant->id),
]))">
    <button x-ref="resume" x-cloak x-show="needed" @click="open()" type="button" class="fixed bottom-5 right-5 z-40 rounded-2xl bg-purple-700 text-white px-5 py-3 shadow-xl font-semibold text-sm">Finish setting up your program</button>
    <dialog x-ref="dialog" @cancel.prevent="minimize()" aria-labelledby="quick-program-title" class="m-auto w-[calc(100%-2rem)] max-w-3xl max-h-[90dvh] overflow-y-auto rounded-3xl border-0 bg-white text-slate-900 p-0 shadow-2xl backdrop:bg-slate-950/50">
        <div class="p-5 sm:p-8">
            <div class="flex items-start justify-between gap-4 mb-5">
                <div><p class="text-xs font-bold uppercase tracking-widest text-purple-600 mb-2">{{ $tenant->id === 'lgu-ids' ? 'Your first referral program' : 'Subscription Referral Program' }}</p><h2 id="quick-program-title" class="text-2xl font-bold">Turn happy customers into your next customers.</h2><p class="text-sm text-slate-500 mt-2">{{ $tenant->id === 'lgu-ids' ? 'A website, a reward, and you’re ready to start.' : 'Set up referrals for your subscription platform with a website and a reward.' }}</p></div>
                <button type="button" @click="minimize()" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100" aria-label="Minimize setup">✕</button>
            </div>
            <ol class="flex gap-3 text-xs mb-6" aria-label="Setup progress"><template x-for="(label, i) in ['Your website', 'Your reward', 'Review']"><li class="rounded-full px-3 py-2" :class="step === i+1 ? 'bg-purple-100 text-purple-800 font-bold' : 'bg-slate-100 text-slate-500'"><span x-text="`${i+1}. ${label}`"></span></li></template></ol>
            <p x-show="error" x-text="error" role="alert" class="mb-4 rounded-xl bg-red-50 p-3 text-sm text-red-700"></p>
            <div x-show="step === 1">
                <label for="quick-website" class="block text-sm font-semibold mb-2">What’s your company website?</label>
                <input id="quick-website" x-model="form.website" @keydown.enter.prevent="analyze()" placeholder="yourcompany.com" autocomplete="url" class="w-full rounded-xl border-slate-300">
                <p class="text-xs text-slate-500 mt-3">We’ll look for public pricing. You’ll confirm the details before creating anything.</p>
                <div class="flex flex-wrap gap-3 mt-6"><button @click="analyze()" :disabled="busy" class="rounded-xl bg-purple-700 px-5 py-3 text-white font-semibold disabled:opacity-50" x-text="busy ? 'Reading your website…' : 'Analyze website'"></button><button @click="manual()" :disabled="busy" class="px-3 py-3 text-purple-700 font-semibold">Enter details myself</button></div>
            </div>
            <div x-show="step === 2" class="space-y-5">
                <div x-show="analysis" class="rounded-2xl border border-purple-100 bg-purple-50 p-4 text-sm">
                    <p class="font-semibold mb-1">Confirm what we found</p><p class="text-slate-600 mb-2" x-text="analysis?.description"></p><p x-text="analysis?.message"></p>
                    <div class="flex flex-wrap gap-2 mt-2"><template x-for="(price, i) in (analysis?.prices || [])" :key="i"><button @click="form.price = price.amount; form.currency = price.currency" class="border border-purple-200 bg-white px-3 py-1.5 rounded-lg" x-text="`Use ${price.currency} ${price.amount}`"></button></template></div>
                    <template x-for="source in (analysis?.sources || [])"><a :href="source" target="_blank" rel="noopener noreferrer" class="block text-xs underline mt-2" x-text="source"></a></template>
                </div>
                <div class="grid sm:grid-cols-2 gap-4">
                    <label class="text-sm font-medium">Program name<input x-model="form.name" maxlength="120" class="mt-1 w-full rounded-xl border-slate-300"></label>
                    <label class="text-sm font-medium">Pricing model<select x-model="form.pricing_model" @change="choose(form.option)" class="mt-1 w-full rounded-xl border-slate-300"><option value="unknown">Not sure yet</option><option value="subscription">Subscription</option><option value="one_time">One-time purchase</option><option value="usage">Usage-based</option></select></label>
                    <label class="text-sm font-medium">Example payment (optional)<input x-model="form.price" type="number" min="0" step="0.01" placeholder="Enter a verified price" class="mt-1 w-full rounded-xl border-slate-300"></label>
                    <label class="text-sm font-medium">Currency<select x-model="form.currency" class="mt-1 w-full rounded-xl border-slate-300"><template x-for="currency in ['PHP','USD','EUR','GBP','AUD','SGD','CAD']"><option :value="currency" x-text="currency"></option></template></select></label>
                </div>
                <div class="grid sm:grid-cols-3 gap-3" role="group" aria-label="Choose a referral program">
                    <button @click="choose('first')" :aria-pressed="form.option === 'first'" class="text-left p-4 rounded-2xl border-2" :class="form.option === 'first' ? 'border-purple-600 bg-purple-50' : 'border-slate-200'"><span class="text-xs text-purple-700">OPTION 1</span><strong class="block mt-2">First purchase</strong><span class="block text-sm mt-2">20% of the first paid invoice. A simple, one-time reward.</span></button>
                    <button @click="choose('recurring')" :aria-pressed="form.option === 'recurring'" class="text-left p-4 rounded-2xl border-2" :class="form.option === 'recurring' ? 'border-purple-600 bg-purple-50' : 'border-slate-200'"><span class="text-xs text-purple-700">OPTION 2</span><strong class="block mt-2" x-text="form.pricing_model === 'subscription' ? 'Ongoing rewards' : 'Fixed reward'"></strong><span class="block text-sm mt-2" x-text="form.pricing_model === 'subscription' ? '10% of paid invoices for six months.' : 'A fixed amount for the first paying customer. Edit the amount below.'"></span></button>
                    <button @click="choose('custom')" :aria-pressed="form.option === 'custom'" class="text-left p-4 rounded-2xl border-2" :class="form.option === 'custom' ? 'border-purple-600 bg-purple-50' : 'border-slate-200'"><span class="text-xs text-purple-700">OPTION 3</span><strong class="block mt-2">Customize your own</strong><span class="block text-sm mt-2">Choose the amount, duration, and payout timing.</span></button>
                </div>
                <p class="text-xs text-slate-500">These are editable starting points, not a profitability forecast. Public prices don’t reveal your margins.</p>
                <details :open="form.option === 'custom'" class="bg-slate-50 p-4 rounded-2xl"><summary class="text-sm font-semibold text-purple-800 cursor-pointer">Adjust reward and payout timing</summary><div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mt-4">
                    <label class="text-xs font-semibold">Reward type<select x-model="form.reward_model" class="block mt-1 w-full text-sm rounded-lg border-slate-300"><option value="percentage">Percentage</option><option value="fixed">Fixed amount</option></select></label>
                    <label class="text-xs font-semibold">Reward value<input x-model="form.reward_value" type="number" min="0.01" :max="form.reward_model === 'percentage' ? 100 : 1000000" step="0.01" class="block mt-1 w-full text-sm rounded-lg border-slate-300"></label>
                    <label class="text-xs font-semibold">Reward applies to<select x-model="form.reward_scope" class="block mt-1 w-full text-sm rounded-lg border-slate-300"><option value="first_payment">First paid invoice</option><option value="recurring">Paid invoices over time</option></select></label>
                    <label x-show="form.reward_scope === 'recurring'" class="text-xs font-semibold">Duration in months<input x-model="form.duration_months" type="number" min="1" max="24" class="block mt-1 w-full text-sm rounded-lg border-slate-300"></label>
                    <label class="text-xs font-semibold">Hold before review (days)<input x-model="form.hold_days" type="number" min="0" max="90" class="block mt-1 w-full text-sm rounded-lg border-slate-300"></label>
                </div>
                </details>
                <p x-show="form.price !== ''" class="text-sm font-semibold text-purple-800" x-text="`On a ${form.currency} ${form.price} eligible payment, the referrer earns ${form.currency} ${payout.toFixed(2)}.`"></p>
                <div class="flex justify-between"><button @click="step = 1" class="text-slate-600">Back</button><button @click="review()" class="rounded-xl bg-purple-700 px-5 py-3 text-white font-semibold">Review program</button></div>
            </div>
            <div x-show="step === 3" class="space-y-5">
                <h3 class="text-xl font-bold" x-text="form.name"></h3>
                <div class="bg-purple-50 rounded-2xl p-5 space-y-3 text-sm"><p><strong>Reward: </strong><span x-text="`${form.reward_value}${form.reward_model === 'percentage' ? '%' : ' '+form.currency}`"></span></p><p x-text="form.reward_scope === 'recurring' ? `Applies to paid invoices for ${form.duration_months} months from the first payment.` : 'Applies to the first paid invoice only.'"></p><p>Based on collected revenue excluding taxes. Refunds reverse the associated reward. No self-referrals or existing customers.</p><p x-text="`Rewards are held for ${form.hold_days} days and then require manual approval. Payments are not sent automatically.`"></p><p>Your program starts as invite-only. Website tracking is connected afterward.</p></div>
                <label class="flex items-start gap-2 text-sm"><input type="checkbox" x-model="confirmed" class="mt-1 rounded"><span>I’ve checked the pricing, reward, and program terms.</span></label>
                <div class="flex justify-between"><button @click="step = 2" :disabled="busy" class="text-slate-600">Back</button><button @click="publish()" :disabled="!confirmed || busy" class="rounded-xl bg-purple-700 px-5 py-3 text-white font-semibold disabled:opacity-40" x-text="busy ? 'Creating program…' : 'Create referral program'"></button></div>
            </div>
            <p class="mt-5 text-xs text-slate-400" role="status" x-text="saveLabel"></p>
        </div>
    </dialog>
</div>
