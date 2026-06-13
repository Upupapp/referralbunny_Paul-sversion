@php
    $config = $draft->config ?? [];

    // Resolves a label for an option key whether the options array is a flat
    // `key => label` map or a `key => ['label' => ..., ...]` map.
    $optLabel = function ($opts, $key) {
        if ($key === null || $key === '') return null;
        $opt = ($opts ?? [])[$key] ?? null;
        if (is_array($opt)) return $opt['label'] ?? $key;
        return $opt ?? $key;
    };

    $reviewStages = (count($config['pipeline']['stages'] ?? []) >= 2) ? $config['pipeline']['stages'] : $pipelineStages;
    $partnersInProgram = in_array('partners', $config['participants']['roles'] ?? ['tenant_admins', 'referrers'], true);

    $summaries = [
        'program-basics' => array_filter([
            $config['program_basics']['program_name'] ?? ($tenant->program_name ?: $tenant->name),
            ($config['program_basics']['currency'] ?? 'PHP') . ' · ' . ($config['program_basics']['timezone'] ?? 'Asia/Manila'),
        ]),
        'industry-goal' => array_filter([
            $optLabel($options['industries'], $config['industry_goal']['industry'] ?? null),
            !empty($config['industry_goal']['sub_industries']) ? implode(', ', $config['industry_goal']['sub_industries']) : null,
            $optLabel($options['goals'], $config['industry_goal']['referral_goal'] ?? null),
        ]),
        'program-type' => array_filter([
            $optLabel($options['programTypes'], $config['program_type']['program_type'] ?? ($recommendation['program_type'] ?? null)),
        ]),
        'participants' => array_filter([
            implode(', ', array_map(fn ($r) => $optLabel($options['roles'], $r), $config['participants']['roles'] ?? ['tenant_admins', 'referrers'])),
        ]),
        'pipeline' => array_filter([
            count($reviewStages) . ' stages: ' . implode(' → ', array_map(fn ($s) => $s['name'] ?? 'Unnamed', $reviewStages)),
        ]),
        'fields' => array_filter([
            empty($config['fields']['fields'])
                ? 'No custom fields — standard fields only.'
                : count($config['fields']['fields']) . ' custom field(s): ' . implode(', ', array_map(fn ($f) => $f['field_label'] ?? ($f['field_key'] ?? '?'), $config['fields']['fields'])),
        ]),
        'rewards' => $protected ? ['Managed separately by your administrator.'] : array_filter([
            ($config['rewards']['company_share_pct'] ?? 30) . '% company / ' . ($config['rewards']['referrer_share_pct'] ?? 70) . '% referrer',
            $optLabel($options['commissionTypes'], $config['rewards']['commission_type'] ?? null),
        ]),
        'partner-split' => !$partnersInProgram ? ['Partners are not part of this program.'] : array_filter([
            ($config['partner_split']['default_split_value'] ?? 50) . (($config['partner_split']['split_type'] ?? 'percentage') === 'percentage' ? '%' : '') . ' of the commission pool per deal',
            empty($config['partner_split']['allow_partners'] ?? true) ? 'Currently turned off on the Partner Split step.' : null,
        ]),
        'documents' => array_filter([
            !empty($config['documents']['require_referrer_agreement']) ? 'Referrer agreement required' : null,
            !empty($config['documents']['require_partner_agreement']) ? 'Partner agreement required' : null,
            !empty($config['documents']['required_documents']) ? count($config['documents']['required_documents']) . ' required document(s)' : null,
        ]) ?: ['No agreements or documents required.'],
        'approvals' => array_filter([
            !empty($config['approvals']['new_referral_review']) ? 'New referrals reviewed before entering the pipeline' : null,
            ($config['approvals']['deal_extension_approval'] ?? true) ? 'Expiry extensions reviewed' : null,
            !empty($config['approvals']['import_approval']) ? 'Imports reviewed before processing' : null,
        ]) ?: ['No approval steps configured.'],
        'forms' => !empty($config['forms']['enable_public_referral_form'])
            ? ['Public form: referralbunny.ai/r/' . ($config['forms']['referral_link_slug'] ?? '')]
            : ['Public referral form is disabled.'],
        'import' => $protected ? ['Managed separately by your administrator.'] : array_filter([
            $optLabel($options['importTemplates'], $config['import']['template_key'] ?? null),
        ]),
        'notifications' => array_filter([
            count(array_filter(array_keys($options['notificationEvents']), fn ($k) => $config['notifications'][$k] ?? true))
                . ' of ' . count($options['notificationEvents']) . ' notification events enabled',
            $optLabel($options['digestFrequencies'], $config['notifications']['digest_frequency'] ?? 'realtime') . ' admin summary',
        ]),
        'dashboard' => array_filter([
            $optLabel($options['dashboardPresets'], $config['dashboard']['dashboard_preset'] ?? 'balanced')
                . ' (' . count($config['dashboard']['visible_widgets'] ?? $options['dashboardPresets']['balanced']['widgets']) . ' widgets)',
        ]),
    ];
@endphp

<div class="space-y-4"
     x-data="{
        simulating: false,
        simulation: null,
        publishing: false,
        showPublishConfirm: false,
        publicFormEnabled: @js(!empty($config['forms']['enable_public_referral_form'])),
        publicFormSlug: @js($config['forms']['referral_link_slug'] ?? ''),

        async runSimulation() {
            this.simulating = true;
            try {
                const res = await fetch('{{ route('tenant.settings.referral-program.wizard.simulate', $tenantId) }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
                        'Accept': 'application/json',
                    },
                });
                this.simulation = await res.json();
            } catch (e) {
                window.dispatchEvent(new CustomEvent('show-toast', { detail: { type: 'error', message: 'Could not run the simulation. Please try again.' } }));
            } finally {
                this.simulating = false;
            }
        },

        startPublish() {
            if (this.publicFormEnabled) { this.showPublishConfirm = true; return; }
            this.publish();
        },

        async publish() {
            this.showPublishConfirm = false;
            this.publishing = true;
            try {
                const res = await fetch('{{ route('tenant.settings.referral-program.wizard.publish', $tenantId) }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
                        'Accept': 'application/json',
                    },
                });
                const json = await res.json();
                if (!res.ok) {
                    this.simulation = json;
                    this.publishing = false;
                    window.dispatchEvent(new CustomEvent('show-toast', { detail: { type: 'error', message: json.message || 'Could not publish. Please fix the issues below.' } }));
                    return;
                }
                window.dispatchEvent(new CustomEvent('show-toast', { detail: { type: 'success', message: json.message || 'Published!' } }));
                setTimeout(() => { window.location.href = json.overviewUrl; }, 1200);
            } catch (e) {
                this.publishing = false;
                window.dispatchEvent(new CustomEvent('show-toast', { detail: { type: 'error', message: 'Network error. Please try again.' } }));
            }
        },
     }"
     x-init="runSimulation()">

    <div class="flex gap-3 p-4 rounded-xl bg-[#F0EFFA] border border-purple-100">
        <x-r-bunny variant="celebration" size="sm" :decorative="true" class="shrink-0 mt-0.5" />
        <div class="flex-1 min-w-0">
            <p class="text-sm font-semibold text-[#1E1B4B] mb-0.5">You're on the last step!</p>
            <p class="text-xs text-gray-500 leading-relaxed">
                Here's everything you've set up. Review each section, run a quick check below, then publish to make
                it live for your team.
            </p>
        </div>
    </div>

    {{-- Summary grid --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        @foreach(array_slice($steps, 0, 14) as $i => $stepKey)
        <div class="card">
            <div class="flex items-center justify-between mb-1.5">
                <h4 class="text-sm font-semibold text-[#1E1B4B]">{{ $i + 1 }}. {{ $stepLabels[$stepKey] }}</h4>
                <a href="{{ route('tenant.settings.referral-program.wizard', ['tenantId' => $tenantId, 'step' => $stepKey]) }}" class="text-xs text-[#7B61FF] hover:underline shrink-0 ml-2">Edit</a>
            </div>
            <ul class="text-xs text-gray-500 space-y-0.5 list-disc list-inside">
                @forelse($summaries[$stepKey] as $line)
                <li>{{ $line }}</li>
                @empty
                <li class="text-gray-400 italic list-none">Not yet configured — defaults will be used.</li>
                @endforelse
            </ul>
        </div>
        @endforeach
    </div>

    {{-- Simulation --}}
    <div class="card">
        <div class="flex items-center justify-between mb-3">
            <div>
                <h3 class="font-semibold text-[#1E1B4B] text-sm">Quick check</h3>
                <p class="text-xs text-gray-400">We run this automatically — re-run it anytime after making changes.</p>
            </div>
            <button type="button" @click="runSimulation()" :disabled="simulating" class="btn-secondary text-xs shrink-0">
                <span x-show="!simulating">Re-check</span>
                <span x-show="simulating">Checking…</span>
            </button>
        </div>

        <p x-show="simulating" class="text-sm text-gray-400">Checking your setup against common scenarios…</p>

        <div x-show="simulation && !simulating" x-cloak class="space-y-2">
            <template x-for="scenario in (simulation?.scenarios || [])" :key="scenario.label">
                <div class="flex items-start gap-2 p-2.5 rounded-lg"
                     :class="{
                        'bg-green-50': scenario.status === 'pass',
                        'bg-yellow-50': scenario.status === 'warn',
                        'bg-red-50': scenario.status === 'fail',
                     }">
                    <span class="mt-0.5 shrink-0"
                          :class="{
                            'text-green-600': scenario.status === 'pass',
                            'text-yellow-600': scenario.status === 'warn',
                            'text-red-600': scenario.status === 'fail',
                          }">
                        <svg x-show="scenario.status === 'pass'" class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        <svg x-show="scenario.status !== 'pass'" class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>
                    </span>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-[#1E1B4B]" x-text="scenario.label"></p>
                        <p class="text-xs text-gray-500" x-text="scenario.message"></p>
                    </div>
                </div>
            </template>

            <p class="text-xs mt-2 font-medium" :class="simulation?.can_publish ? 'text-green-600' : 'text-red-600'">
                <span x-show="simulation?.can_publish">Everything looks good — you're ready to publish.</span>
                <span x-show="!simulation?.can_publish" x-text="(simulation?.blockers || 0) + ' issue(s) must be fixed before you can publish.'"></span>
            </p>
        </div>
    </div>

    {{-- Publish --}}
    <div class="card border-2 border-[#7B61FF]">
        <div class="flex items-center justify-between gap-3 flex-wrap">
            <div>
                <h3 class="font-semibold text-[#1E1B4B] text-sm mb-1">Ready to go live?</h3>
                <p class="text-xs text-gray-500">
                    Publishing applies these settings to your live workspace and saves a snapshot you can restore later.
                    @if($protected)
                    Rewards, Pipeline, and Import settings are managed separately and won't be changed.
                    @endif
                </p>
            </div>
            <button type="button" @click="startPublish()" :disabled="publishing || simulating" class="btn-primary shrink-0 disabled:opacity-50">
                <span x-show="!publishing">Publish Program</span>
                <span x-show="publishing">Publishing…</span>
            </button>
        </div>
    </div>

    {{-- Public form confirmation modal --}}
    <div x-show="showPublishConfirm" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/30 p-4"
         @keydown.escape.window="showPublishConfirm = false">
        <div class="bg-white rounded-2xl shadow-xl max-w-md w-full p-5 space-y-3" @click.outside="showPublishConfirm = false">
            <h3 class="font-semibold text-[#1E1B4B]">Your public referral form will go live</h3>
            <p class="text-sm text-gray-500">
                Anyone with the link <span class="font-mono text-[#1E1B4B]" x-text="'referralbunny.ai/r/' + publicFormSlug"></span>
                will be able to submit a referral once you publish.
            </p>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" @click="showPublishConfirm = false" class="btn-secondary">Cancel</button>
                <button type="button" @click="publish()" :disabled="publishing" class="btn-primary">
                    <span x-show="!publishing">Yes, publish</span>
                    <span x-show="publishing">Publishing…</span>
                </button>
            </div>
        </div>
    </div>
</div>
