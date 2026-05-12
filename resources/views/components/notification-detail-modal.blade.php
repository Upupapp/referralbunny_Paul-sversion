{{--
  Notification Detail Modal
  Shows when a user arrives on a page by clicking a notification.
  Data is bridged via sessionStorage key "__notif_detail" (set before navigation).
  Works on ANY layout — just include this component once per page.
--}}
<div x-data="notifDetailModal()" x-init="init()" x-cloak>

    {{-- Backdrop --}}
    <div x-show="show"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click="close()"
         class="fixed inset-0 bg-black/40 backdrop-blur-sm z-[200]">
    </div>

    {{-- Modal --}}
    <div x-show="show"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95 translate-y-2"
         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         @keydown.escape.window="close()"
         class="fixed inset-0 z-[201] flex items-end sm:items-center justify-center p-4 pointer-events-none">

        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg pointer-events-auto overflow-hidden"
             @click.stop>

            {{-- Priority accent bar --}}
            <div class="h-1 w-full"
                 :style="'background:' + priorityColor()"></div>

            {{-- Header --}}
            <div class="px-5 pt-4 pb-3 flex items-start gap-3">
                {{-- Icon --}}
                <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 mt-0.5"
                     :style="'background:' + priorityBg()">
                    <svg class="w-5 h-5" :style="'color:' + priorityColor()" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                    </svg>
                </div>

                <div class="flex-1 min-w-0">
                    {{-- Priority + Category badges --}}
                    <div class="flex flex-wrap gap-1.5 mb-2">
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wide"
                              :style="'background:' + priorityBg() + '; color:' + priorityColor()"
                              x-text="notif?.priority || 'notification'"></span>
                        <span x-show="notif?.category"
                              class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-gray-100 text-gray-500 capitalize"
                              x-text="(notif?.category || '').replace(/_/g, ' ')"></span>
                    </div>
                    {{-- Title --}}
                    <h3 class="text-base font-bold text-[#1E1B4B] leading-snug" x-text="notif?.title || 'Notification'"></h3>
                </div>

                {{-- Close --}}
                <button @click="close()"
                        class="p-1.5 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors shrink-0 -mt-0.5 -mr-0.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Body --}}
            <div class="px-5 pb-2 space-y-4 max-h-[55dvh] overflow-y-auto">

                {{-- Full message --}}
                <p class="text-sm text-gray-700 leading-relaxed" x-text="notif?.message || notif?.body || ''"></p>

                {{-- Metadata key facts --}}
                <template x-if="metaRows.length > 0">
                    <div class="rounded-xl border border-gray-100 bg-gray-50 overflow-hidden">
                        <div class="px-4 py-2 border-b border-gray-100 bg-gray-100/60">
                            <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400">Details</p>
                        </div>
                        <div class="divide-y divide-gray-100/80">
                            <template x-for="row in metaRows" :key="row.key">
                                <div class="flex items-start gap-3 px-4 py-2.5">
                                    <span class="text-[11px] font-semibold text-gray-400 w-28 shrink-0 pt-0.5" x-text="row.label"></span>
                                    <span class="text-xs text-[#1E1B4B] font-medium break-words min-w-0 flex-1" x-text="row.value"></span>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>

                {{-- Timestamp --}}
                <p x-show="notif?.created_ago" class="text-[10px] text-gray-400 pb-1"
                   x-text="'Sent ' + (notif?.created_ago || '')"></p>
            </div>

            {{-- Footer --}}
            <div class="px-5 py-4 border-t border-gray-100 flex items-center justify-between gap-3">
                <p class="text-[10px] text-gray-400">You are already viewing the relevant page.</p>
                <div class="flex gap-2 shrink-0">
                    <button @click="close()"
                            class="px-4 py-2 rounded-xl text-sm font-semibold text-white transition-all active:scale-95"
                            style="background:linear-gradient(135deg,#7B61FF,#9B8BFF)">
                        Got it
                    </button>
                </div>
            </div>

        </div>
    </div>
</div>

@once
@push('scripts')
<script>
function notifDetailModal() {
    return {
        show:  false,
        notif: null,

        get metaRows() {
            if (!this.notif?.metadata) return [];
            const meta = this.notif.metadata;

            // Fields to skip (internal/redundant/technical)
            const SKIP = new Set([
                'action_url','action_label','audit_event','tenant_id','invite_id',
                'invite_type','category','related_deal_id','accepted_at','source',
                'is_read','is_dismissed','deduplication_key','channel','frequency_type',
                'escalation_level','metadata_json','priority','type','notifiable_type',
                'notifiable_id','sent_at','archived_at','expires_at',
            ]);

            // Friendly labels for common keys
            const LABELS = {
                actor_name:          'Action by',
                actor_role:          'Role',
                reseller:            'Referrer',
                reseller_name:       'Referrer',
                partner:             'Partner',
                partner_name:        'Partner',
                partner_email:       'Partner email',
                deal_name:           'Deal',
                lead_name:           'Deal',
                related_deal_name:   'Deal',
                accepted_user_name:  'User',
                accepted_user_email: 'Email',
                accepted_role:       'Joined as',
                description:         'Summary',
                reason:              'Reason',
                stage:               'Stage',
                old_stage:           'Previous stage',
                new_stage:           'New stage',
                deal_count:          'Deals',
                max_percentage:      'Max available %',
                split:               'Split amount',
                type:                'Split type',
                category:            'Category',
                invite_sent:         'Invite sent',
                display_name:        'Name',
            };

            const rows = [];

            // Flatten old_values / new_values if present
            const flatten = (prefix, obj) => {
                if (!obj || typeof obj !== 'object') return;
                Object.entries(obj).forEach(([k, v]) => {
                    if (v === null || v === undefined || v === '' || SKIP.has(k)) return;
                    const label = LABELS[k] || (prefix + ' ' + k.replace(/_/g, ' '));
                    rows.push({ key: prefix + '_' + k, label: this.titleCase(label), value: String(v) });
                });
            };

            Object.entries(meta).forEach(([k, v]) => {
                if (SKIP.has(k) || v === null || v === undefined || v === '') return;
                if (k === 'old_values' && typeof v === 'object') { flatten('Previous', v); return; }
                if (k === 'new_values' && typeof v === 'object') { flatten('New', v); return; }
                if (k === 'metadata' && typeof v === 'object') return; // skip nested self-reference
                if (typeof v === 'object') return; // skip other nested objects
                const label = LABELS[k] || k.replace(/_/g, ' ');
                rows.push({ key: k, label: this.titleCase(label), value: String(v) });
            });

            return rows.slice(0, 10); // cap at 10 rows
        },

        titleCase(str) {
            return str.replace(/\b\w/g, c => c.toUpperCase());
        },

        priorityColor() {
            const p = this.notif?.priority || 'normal';
            return { critical:'#ef4444', urgent:'#ef4444', high:'#f97316', normal:'#7B61FF', medium:'#7B61FF', low:'#9ca3af' }[p] || '#7B61FF';
        },

        priorityBg() {
            const p = this.notif?.priority || 'normal';
            return { critical:'#fef2f2', urgent:'#fef2f2', high:'#fff7ed', normal:'#ede9fe', medium:'#ede9fe', low:'#f9fafb' }[p] || '#ede9fe';
        },

        init() {
            try {
                const raw = sessionStorage.getItem('__notif_detail');
                if (raw) {
                    this.notif = JSON.parse(raw);
                    sessionStorage.removeItem('__notif_detail');
                    // Small delay so the page has rendered before modal appears
                    setTimeout(() => { this.show = true; }, 300);
                }
            } catch (e) { /* sessionStorage unavailable or JSON parse error — silent */ }
        },

        close() {
            this.show = false;
        },
    };
}
</script>
@endpush
@endonce
