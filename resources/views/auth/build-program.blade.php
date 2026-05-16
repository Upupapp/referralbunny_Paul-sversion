<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @include('partials.google-analytics')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Build a Referral Program — ReferralBunny.ai</title>
    <link rel="icon" type="image/webp" href="/images/logos/referralbunny-favicon.webp">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0; padding: 0; min-height: 100vh; font-family: 'Inter', sans-serif;
            background: linear-gradient(145deg, #0f0b1f 0%, #1e1347 50%, #0a0620 100%);
            background-attachment: fixed;
        }
        .wi { width:100%;border:1.5px solid #e5e7eb;border-radius:12px;padding:10px 14px;font-size:14px;outline:none;transition:all .15s;background:#fff;color:#1e1b4b; }
        .wi:focus { border-color:#7B61FF;box-shadow:0 0 0 3px rgba(123,97,255,.12); }
        .wi::placeholder { color:#9ca3af; }
        select.wi { appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%236b7280'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 12px center;background-size:16px;padding-right:36px; }
        .ind-card { border:1.5px solid #e5e7eb;border-radius:14px;padding:12px 14px;cursor:pointer;transition:all .15s;display:flex;align-items:center;gap:10px;text-align:left;width:100%;background:#fff; }
        .ind-card:hover { border-color:#7B61FF;background:#f5f3ff; }
        .ind-card.sel { border-color:#7B61FF;background:#ede9fe; }
        .bp { background:linear-gradient(135deg,#7B61FF,#5b4cdb);color:white;border:none;border-radius:12px;padding:12px 24px;font-size:14px;font-weight:600;cursor:pointer;transition:all .15s;display:inline-flex;align-items:center;gap:8px; }
        .bp:hover { opacity:.9;box-shadow:0 4px 16px rgba(123,97,255,.4); }
        .bp:disabled { opacity:.5;cursor:not-allowed; }
        .bs { background:white;color:#374151;border:1.5px solid #e5e7eb;border-radius:12px;padding:11px 20px;font-size:14px;font-weight:500;cursor:pointer;transition:all .15s; }
        .bs:hover { border-color:#7B61FF;color:#7B61FF; }
        .err { color:#ef4444;font-size:12px;margin-top:4px; }
    </style>
</head>
<body>
<div x-data="buildWizard()" x-init="init()" class="min-h-screen py-8 px-4">

    {{-- Logo --}}
    <div class="text-center mb-8">
        <a href="/"><img src="/images/logos/referralbunny-logo-white-horizontal.webp" alt="Referral Bunny" class="h-9 object-contain mx-auto"></a>
    </div>

    <div class="max-w-3xl mx-auto">

        {{-- Progress --}}
        <div class="mb-8 px-2">
            <div class="flex items-end">
                @php $steps = [1=>'Account',2=>'Industry',3=>'Pipeline',4=>'Commission',5=>'Done']; @endphp
                @foreach($steps as $n => $label)
                <div class="flex flex-col items-center" style="flex:{{ $n<5?'1':'0' }}">
                    <div class="flex items-center w-full">
                        <div class="w-7 h-7 rounded-full flex items-center justify-center text-[11px] font-bold flex-shrink-0 transition-all"
                             :class="step > {{ $n }} ? 'bg-[#7B61FF] text-white' : (step === {{ $n }} ? 'bg-white text-[#7B61FF] ring-2 ring-[#7B61FF]' : 'bg-white/10 text-white/30')">
                            <template x-if="step > {{ $n }}">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                            </template>
                            <template x-if="step <= {{ $n }}"><span>{{ $n }}</span></template>
                        </div>
                        @if($n < 5)<div class="flex-1 h-0.5 transition-all" :class="step > {{ $n }} ? 'bg-[#7B61FF]' : 'bg-white/15'"></div>@endif
                    </div>
                    <p class="text-[10px] mt-1.5 font-medium transition-colors" :class="step === {{ $n }} ? 'text-white' : 'text-white/30'">{{ $label }}</p>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Card --}}
        <div class="bg-white rounded-2xl shadow-2xl overflow-hidden">

            {{-- STEP 1: Account --}}
            <div x-show="step === 1" class="p-8 space-y-5">
                <div>
                    <h1 class="text-2xl font-bold text-[#1E1B4B]">Create your account</h1>
                    <p class="text-gray-400 text-sm mt-1">You'll be the owner of your referral program workspace.</p>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">First Name *</label>
                        <input x-model="form.first_name" type="text" placeholder="First name" class="wi">
                        <p x-show="errors.first_name" class="err" x-text="errors.first_name"></p>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Last Name *</label>
                        <input x-model="form.last_name" type="text" placeholder="Last name" class="wi">
                        <p x-show="errors.last_name" class="err" x-text="errors.last_name"></p>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Work Email *</label>
                    <input x-model="form.email" type="email" placeholder="you@company.com" class="wi">
                    <p x-show="errors.email" class="err" x-text="errors.email"></p>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Password *</label>
                        <input x-model="form.password" type="password" placeholder="Min 8 characters" class="wi">
                        <p x-show="errors.password" class="err" x-text="errors.password"></p>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Confirm Password *</label>
                        <input x-model="form.password_confirmation" type="password" placeholder="Repeat password" class="wi">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Program / Workspace Name *</label>
                    <input x-model="form.workspace_name" type="text" placeholder="e.g. Acme Corp Referral Program" class="wi">
                    <p class="text-xs text-gray-400 mt-1">This is what referrers will see when they join your program.</p>
                    <p x-show="errors.workspace_name" class="err" x-text="errors.workspace_name"></p>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Country *</label>
                        <select x-model="form.country" class="wi">
                            <option value="">Select</option>
                            <option value="Philippines">Philippines</option>
                            <option value="United States">United States</option>
                            <option value="United Kingdom">United Kingdom</option>
                            <option value="Australia">Australia</option>
                            <option value="Singapore">Singapore</option>
                            <option value="Canada">Canada</option>
                            <option value="India">India</option>
                            <option value="Other">Other</option>
                        </select>
                        <p x-show="errors.country" class="err" x-text="errors.country"></p>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Timezone *</label>
                        <select x-model="form.timezone" class="wi">
                            <option value="Asia/Manila">Asia/Manila (PH)</option>
                            <option value="Asia/Singapore">Asia/Singapore</option>
                            <option value="Asia/Kolkata">Asia/Kolkata</option>
                            <option value="Australia/Sydney">Australia/Sydney</option>
                            <option value="America/New_York">America/New_York</option>
                            <option value="America/Los_Angeles">America/LA</option>
                            <option value="Europe/London">Europe/London</option>
                            <option value="UTC">UTC</option>
                        </select>
                        <p x-show="errors.timezone" class="err" x-text="errors.timezone"></p>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Currency *</label>
                        <select x-model="form.preferred_currency" class="wi">
                            <option value="PHP">PHP — Peso</option>
                            <option value="USD">USD — Dollar</option>
                            <option value="GBP">GBP — Pound</option>
                            <option value="AUD">AUD — Dollar</option>
                            <option value="SGD">SGD — Dollar</option>
                            <option value="INR">INR — Rupee</option>
                        </select>
                        <p x-show="errors.preferred_currency" class="err" x-text="errors.preferred_currency"></p>
                    </div>
                </div>
                <label class="flex items-start gap-2.5 cursor-pointer">
                    <input x-model="form.terms" type="checkbox" class="mt-0.5 w-4 h-4 accent-purple-600 flex-shrink-0">
                    <span class="text-xs text-gray-500 leading-relaxed">
                        I agree to the <a href="#" class="text-[#7B61FF] hover:underline">Terms of Service</a> and <a href="#" class="text-[#7B61FF] hover:underline">Privacy Policy</a>.
                    </span>
                </label>
                <p x-show="errors.terms" class="err" x-text="errors.terms"></p>
                <div class="flex justify-end pt-1">
                    <button @click="nextStep()" class="bp">
                        Continue <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </button>
                </div>
            </div>

            {{-- STEP 2: Industry --}}
            <div x-show="step === 2" class="p-8 space-y-5">
                <div>
                    <h1 class="text-2xl font-bold text-[#1E1B4B]">What's your industry?</h1>
                    <p class="text-gray-400 text-sm mt-1">We'll pre-fill your pipeline and commission settings. Everything is editable.</p>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                    <template x-for="ind in industries" :key="ind.key">
                        <button @click="form.industry = ind.key"
                                :class="form.industry === ind.key ? 'sel' : ''"
                                class="ind-card">
                            <span class="text-2xl" x-text="ind.emoji"></span>
                            <span class="text-xs font-semibold text-[#1E1B4B]" x-text="ind.label"></span>
                        </button>
                    </template>
                </div>
                <template x-if="form.industry && currentTemplate">
                    <div class="bg-purple-50 border border-purple-200 rounded-xl p-4">
                        <p class="text-xs font-bold text-purple-700 uppercase tracking-wide mb-2">Smart Template Preview</p>
                        <div class="flex flex-wrap gap-3 text-xs text-purple-700">
                            <span class="bg-white rounded-full px-2.5 py-1">📋 Lead: <strong x-text="currentTemplate.leadLabel"></strong></span>
                            <span class="bg-white rounded-full px-2.5 py-1">💰 <strong x-text="currentTemplate.referrerShare + '% to Referrers'"></strong></span>
                            <span class="bg-white rounded-full px-2.5 py-1">🔢 <strong x-text="currentTemplate.stages.length + ' pipeline stages'"></strong></span>
                        </div>
                    </div>
                </template>
                <div class="flex justify-between pt-1">
                    <button @click="step = 1" class="bs">Back</button>
                    <button @click="applyTemplateAndNext()" :disabled="!form.industry" class="bp">
                        Continue <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </button>
                </div>
            </div>

            {{-- STEP 3: Pipeline --}}
            <div x-show="step === 3" class="p-8 space-y-5">
                <div>
                    <h1 class="text-2xl font-bold text-[#1E1B4B]">Your deal pipeline</h1>
                    <p class="text-gray-400 text-sm mt-1">Define the stages deals go through. Set a time limit per stage, and mark the "Won" stage.</p>
                </div>
                <div class="overflow-x-auto -mx-1">
                <div class="min-w-[380px] space-y-0 divide-y divide-gray-100">
                    <div class="grid text-[10px] font-bold text-gray-400 uppercase tracking-wide pb-2" style="grid-template-columns:1fr 80px 60px 36px">
                        <span class="pl-5">Stage name</span><span class="text-center">Days limit</span><span class="text-center">Won?</span><span></span>
                    </div>
                    <template x-for="(stage, idx) in form.pipeline_stages" :key="idx">
                        <div class="py-2.5" style="display:grid;grid-template-columns:1fr 80px 60px 36px;align-items:center;gap:8px">
                            <div class="flex items-center gap-2">
                                <div class="w-3 h-3 rounded-full flex-shrink-0" :style="'background:' + stage.color"></div>
                                <input x-model="stage.name" type="text" placeholder="Stage name"
                                       class="wi py-1.5 text-sm"
                                       @input="stage.key = stage.name.toLowerCase().replace(/\s+/g,'-').replace(/[^a-z0-9-]/g,'')">
                            </div>
                            <input x-model.number="stage.days" type="number" placeholder="—" min="1" max="365"
                                   class="wi py-1.5 text-sm text-center">
                            <div class="flex justify-center">
                                <input type="checkbox" x-model="stage.is_won" class="w-4 h-4 accent-green-500">
                            </div>
                            <button @click="form.pipeline_stages.splice(idx, 1)"
                                    class="flex justify-center text-gray-300 hover:text-red-400 transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                    </template>
                </div>
                </div>
                <button @click="addStage()" class="flex items-center gap-2 text-sm text-[#7B61FF] font-semibold hover:opacity-75 transition-opacity">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                    Add stage
                </button>
                <div class="bg-blue-50 rounded-xl p-3.5 text-xs text-blue-700 leading-relaxed">
                    <strong>Days limit</strong> — how many days a deal can sit in this stage before being flagged as expiring. Leave blank for no limit. Check <strong>Won</strong> for the final success stage.
                </div>
                <div class="flex justify-between pt-1">
                    <button @click="step = 2" class="bs">Back</button>
                    <button @click="step = 4" :disabled="form.pipeline_stages.length === 0" class="bp">
                        Continue <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </button>
                </div>
            </div>

            {{-- STEP 4: Commission --}}
            <div x-show="step === 4" class="p-8 space-y-5">
                <div>
                    <h1 class="text-2xl font-bold text-[#1E1B4B]">Commission structure</h1>
                    <p class="text-gray-400 text-sm mt-1">How do referrers earn from each deal they bring in?</p>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">What do you call a deal? *</label>
                        <input x-model="form.lead_label" type="text" placeholder="e.g. Deal, Application, Property" class="wi">
                        <p class="text-xs text-gray-400 mt-1">Used in buttons, labels, and headings.</p>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">What does deal value mean? *</label>
                        <input x-model="form.value_label" type="text" placeholder="e.g. Contract Value, Sale Price" class="wi">
                        <p class="text-xs text-gray-400 mt-1">Shown as the value field label.</p>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-2">Commission type *</label>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <button @click="form.commission_type = 'percentage_of_value'"
                                :class="form.commission_type === 'percentage_of_value' ? 'border-[#7B61FF] bg-purple-50' : 'border-gray-200 bg-white'"
                                class="border-2 rounded-xl p-3 text-left transition-all">
                            <div class="text-xl mb-1">%</div>
                            <p class="text-xs font-bold text-[#1E1B4B]">% of Value</p>
                            <p class="text-[11px] text-gray-400 mt-0.5">Percentage of the deal value</p>
                        </button>
                        <button @click="form.commission_type = 'fixed_amount'"
                                :class="form.commission_type === 'fixed_amount' ? 'border-[#7B61FF] bg-purple-50' : 'border-gray-200 bg-white'"
                                class="border-2 rounded-xl p-3 text-left transition-all">
                            <div class="text-xl mb-1">💵</div>
                            <p class="text-xs font-bold text-[#1E1B4B]">Fixed Amount</p>
                            <p class="text-[11px] text-gray-400 mt-0.5">Fixed payout per closed deal</p>
                        </button>
                        <button @click="form.commission_type = 'placement_fee'"
                                :class="form.commission_type === 'placement_fee' ? 'border-[#7B61FF] bg-purple-50' : 'border-gray-200 bg-white'"
                                class="border-2 rounded-xl p-3 text-left transition-all">
                            <div class="text-xl mb-1">💼</div>
                            <p class="text-xs font-bold text-[#1E1B4B]">Placement Fee</p>
                            <p class="text-[11px] text-gray-400 mt-0.5">% of salary / recurring</p>
                        </button>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-3">Commission split *</label>
                    <div class="bg-gray-50 rounded-xl p-4 space-y-3">
                        <div class="flex justify-between text-xs">
                            <span class="text-gray-500">Company keeps <strong class="text-[#1E1B4B]" x-text="form.company_share_pct + '%'"></strong></span>
                            <span class="text-[#7B61FF] font-semibold">Referrers receive <strong x-text="form.referrer_share_pct + '%'"></strong></span>
                        </div>
                        <input type="range" x-model.number="form.company_share_pct"
                               @input="form.referrer_share_pct = 100 - form.company_share_pct"
                               min="0" max="100" step="5" class="w-full accent-purple-600">
                        <div class="flex rounded-lg overflow-hidden h-7 text-[11px] font-bold">
                            <div class="flex items-center justify-center text-white transition-all"
                                 :style="'background:#1E1B4B;width:' + form.company_share_pct + '%'"
                                 x-show="form.company_share_pct > 8"
                                 x-text="form.company_share_pct + '% Co.'"></div>
                            <div class="flex items-center justify-center text-white transition-all"
                                 :style="'background:linear-gradient(135deg,#7B61FF,#9B8BFF);width:' + form.referrer_share_pct + '%'"
                                 x-show="form.referrer_share_pct > 8"
                                 x-text="form.referrer_share_pct + '% Referrers'"></div>
                        </div>
                        <p class="text-xs text-gray-400">These are defaults — individual deals can override the split.</p>
                    </div>
                </div>
                <div class="flex justify-between pt-1">
                    <button @click="step = 3" class="bs">Back</button>
                    <button @click="submitForm()" :disabled="submitting" class="bp">
                        <svg x-show="submitting" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 22 6.477 22 12h-4z"/>
                        </svg>
                        <span x-text="submitting ? 'Creating program…' : 'Launch my program 🚀'"></span>
                    </button>
                </div>
                <p x-show="submitError" class="text-center text-sm text-red-600 font-medium" x-text="submitError"></p>
            </div>

            {{-- STEP 5: Done --}}
            <div x-show="step === 5" class="p-10 text-center space-y-6">
                <img src="/images/mascots/r-rocket.webp" alt="" class="w-24 h-24 object-contain mx-auto" onerror="this.style.display='none'">
                <div>
                    <h1 class="text-2xl font-bold text-[#1E1B4B]">Your program is live! 🎉</h1>
                    <p class="text-gray-500 text-sm mt-2 max-w-sm mx-auto" x-text="'Welcome, ' + form.first_name + '! ' + form.workspace_name + ' is ready. Start by inviting your first Referrers.'"></p>
                </div>
                <div class="grid grid-cols-3 gap-3 max-w-xs mx-auto">
                    <div class="bg-purple-50 rounded-xl p-3 text-center"><div class="text-2xl mb-1">👥</div><p class="text-[11px] font-semibold text-[#1E1B4B]">Invite Referrers</p></div>
                    <div class="bg-purple-50 rounded-xl p-3 text-center"><div class="text-2xl mb-1">📋</div><p class="text-[11px] font-semibold text-[#1E1B4B]">Add Deals</p></div>
                    <div class="bg-purple-50 rounded-xl p-3 text-center"><div class="text-2xl mb-1">💰</div><p class="text-[11px] font-semibold text-[#1E1B4B]">Track Commissions</p></div>
                </div>
                <a :href="dashboardUrl" class="bp mx-auto">
                    Go to Dashboard
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </a>
            </div>

        </div>

        <p class="text-center text-white/30 text-xs mt-6">
            Already have an account? <a href="{{ route('tenant.login') }}" class="text-white/60 hover:text-white underline">Sign in</a>
        </p>
    </div>
</div>

@push('scripts')
<script>
const S_COLORS = ['#9CA3AF','#3B82F6','#F59E0B','#8B5CF6','#10B981','#EF4444','#F97316','#06B6D4','#84CC16','#EC4899'];
const TEMPLATES = {
    'Technology & SaaS':        { leadLabel:'Account',         valueLabel:'Contract Value', commissionType:'percentage_of_value', companyShare:20, referrerShare:80, stages:[{key:'demo',name:'Demo',days:7,color:'#9CA3AF',is_won:false},{key:'trial',name:'Trial',days:14,color:'#3B82F6',is_won:false},{key:'proposal',name:'Proposal',days:14,color:'#F59E0B',is_won:false},{key:'negotiation',name:'Negotiation',days:14,color:'#8B5CF6',is_won:false},{key:'closed-won',name:'Closed Won',days:null,color:'#10B981',is_won:true}] },
    'Real Estate':              { leadLabel:'Buyer Lead',       valueLabel:'Property Value', commissionType:'percentage_of_value', companyShare:40, referrerShare:60, stages:[{key:'inquiry',name:'Inquiry',days:7,color:'#9CA3AF',is_won:false},{key:'viewing',name:'Viewing',days:14,color:'#3B82F6',is_won:false},{key:'offer',name:'Offer',days:21,color:'#F59E0B',is_won:false},{key:'closing',name:'Closing',days:30,color:'#8B5CF6',is_won:false},{key:'sold',name:'Sold',days:null,color:'#10B981',is_won:true}] },
    'Recruitment & HR':         { leadLabel:'Candidate',        valueLabel:'Annual Salary',  commissionType:'placement_fee',       companyShare:30, referrerShare:70, stages:[{key:'screening',name:'Screening',days:7,color:'#9CA3AF',is_won:false},{key:'interview',name:'Interview',days:14,color:'#3B82F6',is_won:false},{key:'offer',name:'Offer',days:7,color:'#F59E0B',is_won:false},{key:'hired',name:'Hired',days:null,color:'#10B981',is_won:true}] },
    'Financial Services':       { leadLabel:'Client',           valueLabel:'Deal Value',     commissionType:'percentage_of_value', companyShare:35, referrerShare:65, stages:[{key:'introduction',name:'Introduction',days:14,color:'#9CA3AF',is_won:false},{key:'needs-analysis',name:'Needs Analysis',days:21,color:'#3B82F6',is_won:false},{key:'proposal',name:'Proposal',days:14,color:'#F59E0B',is_won:false},{key:'signed',name:'Signed',days:null,color:'#10B981',is_won:true}] },
    'Healthcare':               { leadLabel:'Patient',          valueLabel:'Service Value',  commissionType:'fixed_amount',        companyShare:50, referrerShare:50, stages:[{key:'inquiry',name:'Inquiry',days:3,color:'#9CA3AF',is_won:false},{key:'consultation',name:'Consultation',days:7,color:'#3B82F6',is_won:false},{key:'enrolled',name:'Enrolled',days:null,color:'#10B981',is_won:true}] },
    'Education':                { leadLabel:'Student',          valueLabel:'Enrollment Value',commissionType:'fixed_amount',        companyShare:40, referrerShare:60, stages:[{key:'inquiry',name:'Inquiry',days:7,color:'#9CA3AF',is_won:false},{key:'application',name:'Application',days:14,color:'#3B82F6',is_won:false},{key:'enrolled',name:'Enrolled',days:null,color:'#10B981',is_won:true}] },
    'Government & Public Sector':{ leadLabel:'Municipality',    valueLabel:'Contract Value', commissionType:'percentage_of_value', companyShare:30, referrerShare:70, stages:[{key:'introduction',name:'Introduction',days:14,color:'#9CA3AF',is_won:false},{key:'presentation',name:'Presentation',days:21,color:'#3B82F6',is_won:false},{key:'contract-sent',name:'Contract Sent',days:30,color:'#F59E0B',is_won:false},{key:'signed',name:'Signed',days:30,color:'#8B5CF6',is_won:false},{key:'paid',name:'Paid',days:null,color:'#10B981',is_won:true}] },
    'Retail & E-commerce':      { leadLabel:'Customer',         valueLabel:'Order Value',    commissionType:'percentage_of_value', companyShare:50, referrerShare:50, stages:[{key:'referred',name:'Referred',days:7,color:'#9CA3AF',is_won:false},{key:'contacted',name:'Contacted',days:7,color:'#3B82F6',is_won:false},{key:'purchased',name:'Purchased',days:null,color:'#10B981',is_won:true}] },
    'Other':                    { leadLabel:'Deal',             valueLabel:'Deal Value',     commissionType:'percentage_of_value', companyShare:30, referrerShare:70, stages:[{key:'introduction',name:'Introduction',days:14,color:'#9CA3AF',is_won:false},{key:'in-progress',name:'In Progress',days:21,color:'#3B82F6',is_won:false},{key:'won',name:'Won',days:null,color:'#10B981',is_won:true}] },
};

function buildWizard() {
    return {
        step: 1, submitting: false, submitError: '', dashboardUrl: '/', errors: {},
        form: {
            first_name:'', last_name:'', email:'', password:'', password_confirmation:'',
            workspace_name:'', industry:'', country:'Philippines', timezone:'Asia/Manila',
            preferred_currency:'PHP', lead_label:'Deal', value_label:'Deal Value',
            commission_type:'percentage_of_value', company_share_pct:30, referrer_share_pct:70,
            pipeline_stages:[], terms:false,
        },
        industries: [
            {key:'Technology & SaaS',emoji:'💻',label:'Technology & SaaS'},
            {key:'Real Estate',emoji:'🏠',label:'Real Estate'},
            {key:'Recruitment & HR',emoji:'👔',label:'Recruitment & HR'},
            {key:'Financial Services',emoji:'💰',label:'Financial Services'},
            {key:'Healthcare',emoji:'🏥',label:'Healthcare'},
            {key:'Education',emoji:'🎓',label:'Education'},
            {key:'Government & Public Sector',emoji:'🏛️',label:'Government / Public Sector'},
            {key:'Retail & E-commerce',emoji:'🛍️',label:'Retail & E-commerce'},
            {key:'Other',emoji:'⚙️',label:'Other / Custom'},
        ],
        get currentTemplate() { return TEMPLATES[this.form.industry] ?? null; },
        init() {},

        applyTemplateAndNext() {
            if (!this.form.industry) return;
            const t = TEMPLATES[this.form.industry];
            if (t) {
                this.form.lead_label = t.leadLabel; this.form.value_label = t.valueLabel;
                this.form.commission_type = t.commissionType;
                this.form.company_share_pct = t.companyShare; this.form.referrer_share_pct = t.referrerShare;
                this.form.pipeline_stages = t.stages.map(s => ({...s}));
            }
            this.step = 3;
        },

        addStage() {
            const i = this.form.pipeline_stages.length;
            this.form.pipeline_stages.push({key:'stage-'+(i+1),name:'Stage '+(i+1),days:14,color:S_COLORS[i%S_COLORS.length],is_won:false});
        },

        nextStep() {
            this.errors = {};
            if (this.step === 1) {
                if (!this.form.first_name)      this.errors.first_name = 'Required';
                if (!this.form.last_name)       this.errors.last_name  = 'Required';
                if (!this.form.email)           this.errors.email = 'Required';
                if (this.form.password.length < 8) this.errors.password = 'Minimum 8 characters';
                if (this.form.password !== this.form.password_confirmation) this.errors.password = 'Passwords do not match';
                if (!this.form.workspace_name)  this.errors.workspace_name = 'Required';
                if (!this.form.country)         this.errors.country = 'Required';
                if (!this.form.terms)           this.errors.terms = 'You must accept the terms';
                if (Object.keys(this.errors).length) return;
            }
            this.step++;
        },

        async submitForm() {
            if (this.submitting) return;
            this.submitting = true; this.submitError = '';
            try {
                const res = await fetch('{{ route("tenant.create.post") }}', {
                    method:'POST', credentials:'same-origin',
                    headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content,'X-Requested-With':'XMLHttpRequest'},
                    body: JSON.stringify(this.form),
                });
                const data = await res.json();
                if (!res.ok) {
                    this.submitError = data.errors ? Object.values(data.errors).flat().join(' ') : (data.message || 'Something went wrong.');
                    return;
                }
                this.dashboardUrl = data.redirect || '/';
                this.step = 5;
            } catch(e) {
                this.submitError = 'Network error. Please try again.';
            } finally { this.submitting = false; }
        },
    };
}
</script>
@endpush
</body>
</html>
