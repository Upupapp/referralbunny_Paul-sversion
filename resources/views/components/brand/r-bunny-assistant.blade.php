{{--
    R Bunny — unified on-site assistant (bottom-left)
    Replaces onboarding-bot. Separate from messaging reminder (bottom-right).
    Three modes: Home | Setup | Help
    Usage: <x-brand.r-bunny-assistant />
--}}
@php
    $rBunnyTenantId = isset($tenant) ? $tenant->id : null;
@endphp

<div
    x-data="rBunnyAssistant(@json($rBunnyTenantId))"
    x-init="init()"
    @onboarding-walkthrough-done.window="onWalkthroughDone()"
    @keydown.escape.window="if(open) open = false"
    class="fixed bottom-5 left-5 z-[140]"
    style="pointer-events:none"
    role="complementary"
    aria-label="R Bunny assistant"
    x-cloak
>
    {{-- ══ OPEN PANEL ════════════════════════════════════════════════ --}}
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-250"
        x-transition:enter-start="opacity-0 translate-y-3 scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
        x-transition:leave-end="opacity-0 translate-y-2 scale-95"
        class="bg-white rounded-2xl shadow-2xl border border-gray-100 mb-2 flex flex-col overflow-hidden"
        style="pointer-events:all; width:320px; max-height:calc(100vh - 8rem)"
        role="dialog"
        aria-label="R Bunny assistant panel"
    >
        {{-- ── Header ─────────────────────────────────────────────── --}}
        <div class="bg-gradient-to-r from-[#1E1B4B] to-[#4C3FA0] px-4 py-3 flex items-center gap-3 shrink-0">
            <img :src="`/images/mascots/r-bunny-${headerMascot}.webp`"
                 alt="R Bunny" class="w-8 h-8 object-contain shrink-0" loading="lazy">
            <div class="flex-1 min-w-0">
                <p class="text-white text-xs font-bold leading-none">R Bunny</p>
                <p class="text-white/50 text-[10px] mt-0.5" x-text="headerSubtitle"></p>
            </div>
            <div class="flex items-center gap-1.5 shrink-0">
                {{-- Settings button --}}
                <button @click="showPrefs = !showPrefs"
                        class="text-white/40 hover:text-white/70 transition-colors p-1 rounded-lg"
                        :class="showPrefs ? 'bg-white/10' : ''"
                        aria-label="R Bunny preferences">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </button>
                {{-- Close --}}
                <button @click="open = false"
                        class="text-white/40 hover:text-white transition-colors p-1 rounded-lg"
                        aria-label="Close R Bunny">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
            </div>
        </div>

        {{-- ── Mode tabs ───────────────────────────────────────────── --}}
        <div class="flex border-b border-gray-100 shrink-0 bg-gray-50" role="tablist">
            <template x-for="tab in tabs" :key="tab.id">
                <button
                    @click="mode = tab.id"
                    :class="mode === tab.id ? 'border-b-2 border-[#7B61FF] text-[#7B61FF] bg-white' : 'text-gray-400 hover:text-gray-600'"
                    class="flex-1 py-2.5 text-[11px] font-semibold flex items-center justify-center gap-1.5 transition-colors"
                    :aria-selected="mode === tab.id"
                    role="tab"
                    :id="`tab-${tab.id}`"
                    :aria-controls="`panel-${tab.id}`"
                >
                    <span x-text="tab.label"></span>
                    <span x-show="tab.badge > 0"
                          x-text="tab.badge > 9 ? '9+' : tab.badge"
                          class="min-w-[1rem] h-4 px-0.5 rounded-full bg-[#7B61FF] text-white text-[9px] font-bold flex items-center justify-center"
                          aria-hidden="true"></span>
                </button>
            </template>
        </div>

        {{-- ── Preferences panel ───────────────────────────────────── --}}
        <div x-show="showPrefs" class="px-4 py-3 bg-[#F8F7FF] border-b border-violet-100 shrink-0">
            <p class="text-[10px] font-bold text-[#7B61FF] uppercase tracking-widest mb-2">Preferences</p>
            <div class="space-y-1.5">
                <template x-for="pref in prefItems" :key="pref.key">
                    <label class="flex items-center gap-2.5 cursor-pointer group">
                        <input type="checkbox" :checked="prefs[pref.key]"
                               @change="togglePref(pref.key)"
                               class="w-3.5 h-3.5 rounded accent-violet-600">
                        <span class="text-xs text-gray-600 group-hover:text-gray-800 transition-colors" x-text="pref.label"></span>
                    </label>
                </template>
            </div>
        </div>

        {{-- ── Scrollable content area ─────────────────────────────── --}}
        <div class="flex-1 overflow-y-auto" aria-live="polite" aria-atomic="false">

            {{-- Loading state --}}
            <div x-show="loading" class="flex items-center justify-center py-10 text-gray-400 gap-2">
                <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
                <span class="text-xs">R Bunny is loading…</span>
            </div>

            {{-- ══ HOME mode ═══════════════════════════════════════════ --}}
            <div x-show="!loading && mode === 'home'" id="panel-home" role="tabpanel" aria-labelledby="tab-home">

                {{-- Top suggestion card --}}
                <template x-if="suggestions.length > 0">
                    <div class="p-4 border-b border-gray-50">
                        <div class="bg-[#F8F7FF] rounded-xl p-3.5 border border-violet-100">
                            <div class="flex items-start gap-2.5">
                                <img :src="`/images/mascots/r-bunny-${suggestions[0].mascot}.webp`"
                                     alt="R Bunny" class="w-7 h-7 object-contain shrink-0 mt-0.5" loading="lazy">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-1.5 mb-0.5">
                                        <span class="text-[9px] font-bold uppercase tracking-widest"
                                              :class="{
                                                  'text-red-500':    suggestions[0].priority === 'urgent',
                                                  'text-orange-500': suggestions[0].priority === 'high',
                                                  'text-[#7B61FF]':  suggestions[0].priority === 'normal',
                                                  'text-gray-400':   suggestions[0].priority === 'low',
                                              }"
                                              x-text="suggestions[0].priority === 'high' ? '⚠ Needs attention' : suggestions[0].priority === 'urgent' ? '🔴 Urgent' : 'R Bunny says'">
                                        </span>
                                    </div>
                                    <p class="text-sm font-semibold text-[#1E1B4B] leading-snug" x-text="suggestions[0].title"></p>
                                    <p class="text-xs text-gray-500 mt-0.5 leading-relaxed" x-text="suggestions[0].body"></p>
                                </div>
                            </div>
                            <div class="flex flex-wrap gap-2 mt-3">
                                <template x-if="suggestions[0].action">
                                    <a :href="suggestions[0].action.url"
                                       class="px-3 py-1.5 bg-[#7B61FF] hover:bg-[#6D4FE8] text-white text-xs font-semibold rounded-lg transition-colors"
                                       x-text="suggestions[0].action.label">
                                    </a>
                                </template>
                                <button @click="dismissSuggestion(suggestions[0].key)"
                                        class="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-500 text-xs font-medium rounded-lg transition-colors">
                                    Not now
                                </button>
                                <button @click="snoozeSuggestion(suggestions[0].key)"
                                        class="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-500 text-xs font-medium rounded-lg transition-colors">
                                    Snooze 24h
                                </button>
                            </div>
                        </div>
                        {{-- More suggestions indicator --}}
                        <p x-show="suggestions.length > 1"
                           class="text-[10px] text-gray-400 text-center mt-2"
                           x-text="`+${suggestions.length - 1} more item${suggestions.length > 2 ? 's' : ''} need attention`">
                        </p>
                    </div>
                </template>

                {{-- All-clear state --}}
                <template x-if="suggestions.length === 0">
                    <div class="p-5 text-center">
                        <img src="/images/mascots/r-bunny-thumbs-up.webp"
                             alt="R Bunny" class="w-14 h-14 object-contain mx-auto mb-2" loading="lazy">
                        <p class="text-sm font-semibold text-[#1E1B4B]">All clear!</p>
                        <p class="text-xs text-gray-400 mt-1">No urgent items. R Bunny will let you know if something needs attention.</p>
                    </div>
                </template>

                {{-- Quick actions grid --}}
                <div x-show="quickActions.length > 0" class="p-4">
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2.5">Quick Actions</p>
                    <div class="grid grid-cols-3 gap-2">
                        <template x-for="action in quickActions.slice(0, 9)" :key="action.key">
                            <a :href="action.url"
                               class="flex flex-col items-center gap-1.5 py-2.5 px-1 rounded-xl bg-gray-50 hover:bg-[#F0EFFA] hover:text-[#7B61FF] text-gray-600 transition-colors text-center group">
                                <div class="w-7 h-7 flex items-center justify-center">
                                    <template x-if="action.icon === 'home'">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                                    </template>
                                    <template x-if="action.icon === 'deals'">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                                    </template>
                                    <template x-if="action.icon === 'contacts'">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                    </template>
                                    <template x-if="action.icon === 'message'">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                                    </template>
                                    <template x-if="action.icon === 'chart'">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                                    </template>
                                    <template x-if="action.icon === 'users'">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                                    </template>
                                    <template x-if="action.icon === 'upload'">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                                    </template>
                                    <template x-if="action.icon === 'team'">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    </template>
                                    <template x-if="action.icon === 'user'">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                    </template>
                                    <template x-if="!['home','deals','contacts','message','chart','users','upload','team','user','building','credit'].includes(action.icon)">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                    </template>
                                </div>
                                <span class="text-[10px] font-medium leading-none" x-text="action.label"></span>
                            </a>
                        </template>
                    </div>
                </div>
            </div>

            {{-- ══ SETUP mode (onboarding) ══════════════════════════════ --}}
            <div x-show="!loading && mode === 'setup'" id="panel-setup" role="tabpanel" aria-labelledby="tab-setup">

                {{-- Progress bar --}}
                <div class="h-1.5 bg-gray-100 shrink-0">
                    <div class="h-1.5 bg-gradient-to-r from-[#7B61FF] to-[#EC4899] transition-all duration-500 rounded-r-full"
                         :style="`width: ${onboardingPercent}%`"
                         role="progressbar"
                         :aria-valuenow="onboardingPercent"
                         aria-valuemin="0" aria-valuemax="100"
                         :aria-label="`Onboarding ${onboardingPercent}% complete`">
                    </div>
                </div>

                {{-- Fully ready celebration --}}
                <template x-if="fullyReady">
                    <div class="p-5 text-center">
                        <img src="/images/mascots/r-bunny-celebration.webp"
                             alt="R Bunny celebrating" class="w-14 h-14 object-contain mx-auto mb-3" loading="lazy">
                        <p class="text-[#1E1B4B] font-bold text-sm">I think you're fully ready now! 🎉</p>
                        <p class="text-gray-500 text-xs mt-2 leading-relaxed">
                            You've completed the important first steps. Come back here anytime if you have
                            questions or need bunny-ful assistance. Happy referring!
                        </p>
                    </div>
                </template>

                {{-- Next task suggestion --}}
                <template x-if="!fullyReady && nextTask">
                    <div class="p-4 border-b border-gray-50">
                        <p class="text-[10px] font-bold text-[#7B61FF] uppercase tracking-widest mb-2">Next Step</p>
                        <div class="bg-[#F8F7FF] rounded-xl p-3 border border-violet-100">
                            <p class="text-sm font-semibold text-[#1E1B4B]" x-text="nextTask.label"></p>
                            <p class="text-xs text-gray-500 mt-0.5" x-text="nextTask.message"></p>
                            <div class="flex flex-wrap gap-2 mt-2.5">
                                <template x-if="nextTask.action">
                                    <a :href="nextTask.action"
                                       @click="completeOnboardingTask(nextTask.key)"
                                       class="px-3 py-1.5 bg-[#7B61FF] hover:bg-[#6D4FE8] text-white text-xs font-semibold rounded-lg transition-colors">
                                        Go there →
                                    </a>
                                </template>
                                <template x-if="!nextTask.action">
                                    <button @click="completeOnboardingTask(nextTask.key)"
                                            class="px-3 py-1.5 bg-[#7B61FF] hover:bg-[#6D4FE8] text-white text-xs font-semibold rounded-lg transition-colors">
                                        Mark done ✓
                                    </button>
                                </template>
                                <button @click="dismissOnboardingTask(nextTask.key)"
                                        class="px-3 py-1.5 bg-gray-100 text-gray-500 text-xs font-medium rounded-lg hover:bg-gray-200 transition-colors">
                                    Skip for now
                                </button>
                            </div>
                        </div>
                    </div>
                </template>

                {{-- Task checklist --}}
                <div class="p-4">
                    <button @click="showTasks = !showTasks"
                            class="flex items-center justify-between w-full text-[10px] font-bold text-gray-400 uppercase tracking-widest py-1 mb-1"
                            :aria-expanded="showTasks">
                        <span>All steps (<span x-text="`${onboardingDone}/${onboardingTotal}`"></span>)</span>
                        <svg class="w-3 h-3 transition-transform duration-200" :class="showTasks ? 'rotate-180' : ''"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div x-show="showTasks"
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 -translate-y-1"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         class="space-y-1.5">
                        <template x-for="task in onboardingTasks" :key="task.key">
                            <div class="flex items-center gap-2.5 py-1.5">
                                <div class="shrink-0 w-4 h-4 rounded-full flex items-center justify-center"
                                     :class="task.completed ? 'bg-emerald-500' : 'border-2 border-gray-200'">
                                    <template x-if="task.completed">
                                        <svg class="w-2.5 h-2.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                                        </svg>
                                    </template>
                                </div>
                                <span class="text-xs flex-1 leading-tight"
                                      :class="task.completed ? 'text-gray-400 line-through' : 'text-gray-700'"
                                      x-text="task.label"></span>
                                <span x-show="task.required && !task.completed"
                                      class="text-[9px] font-semibold text-amber-500 uppercase shrink-0">Required</span>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- Profile progress footer --}}
                <div class="px-4 pb-4 flex items-center justify-between">
                    <span class="text-[10px] text-gray-400">Profile: <span class="font-semibold text-[#7B61FF]" x-text="`${profilePercent}%`"></span> complete</span>
                    <button @click="snoozeOnboarding()"
                            class="text-[10px] text-gray-400 hover:text-gray-600 transition-colors">
                        Snooze 24h
                    </button>
                </div>
            </div>

            {{-- ══ HELP mode ════════════════════════════════════════════ --}}
            <div x-show="!loading && mode === 'help'" id="panel-help" role="tabpanel" aria-labelledby="tab-help">
                <template x-if="pageHelp">
                    <div class="p-4 space-y-3">
                        <div class="flex items-center gap-3 mb-1">
                            <img :src="`/images/mascots/r-bunny-${pageHelp.mascot}.webp`"
                                 alt="R Bunny" class="w-10 h-10 object-contain shrink-0" loading="lazy">
                            <div>
                                <p class="text-xs font-bold text-[#1E1B4B]" x-text="`About: ${pageHelp.title}`"></p>
                                <p class="text-xs text-gray-500 leading-snug mt-0.5" x-text="pageHelp.summary"></p>
                            </div>
                        </div>
                        <div x-show="pageHelp.tips && pageHelp.tips.length > 0" class="space-y-2">
                            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Tips</p>
                            <template x-for="(tip, i) in pageHelp.tips" :key="i">
                                <div class="flex items-start gap-2">
                                    <span class="text-[#7B61FF] font-bold text-xs shrink-0 mt-0.5">·</span>
                                    <p class="text-xs text-gray-600 leading-relaxed" x-text="tip"></p>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>

                <template x-if="!pageHelp">
                    <div class="p-5 text-center">
                        <img src="/images/mascots/r-bunny-helper-question.webp"
                             alt="R Bunny" class="w-12 h-12 object-contain mx-auto mb-2" loading="lazy">
                        <p class="text-sm font-semibold text-[#1E1B4B]">Need help?</p>
                        <p class="text-xs text-gray-400 mt-1 leading-relaxed">I don't have specific help for this page yet, but I can point you in the right direction.</p>
                    </div>
                </template>

                {{-- Handoff section --}}
                <div class="px-4 pb-4 border-t border-gray-50 pt-3">
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">Still stuck?</p>
                    <div x-show="!handoffDone" class="space-y-2">
                        <textarea
                            x-model="handoffIssue"
                            class="w-full text-xs border border-gray-200 rounded-xl px-3 py-2 bg-gray-50 outline-none focus:border-violet-400 focus:ring-2 focus:ring-violet-100 transition-all resize-none"
                            rows="2"
                            placeholder="Briefly describe the issue…"
                            maxlength="300"
                            aria-label="Describe your issue"
                        ></textarea>
                        <button @click="requestHandoff()"
                                :disabled="!handoffIssue.trim()"
                                class="w-full py-2 rounded-xl text-xs font-semibold transition-colors"
                                :class="handoffIssue.trim() ? 'bg-[#1E1B4B] hover:bg-[#2D2A6E] text-white' : 'bg-gray-100 text-gray-400 cursor-not-allowed'">
                            Get human help
                        </button>
                    </div>
                    <div x-show="handoffDone" class="bg-emerald-50 rounded-xl p-3 text-center border border-emerald-100">
                        <p class="text-xs font-semibold text-emerald-700">Summary prepared!</p>
                        <p class="text-xs text-emerald-600 mt-0.5" x-text="handoffRef ? `Reference: ${handoffRef}` : 'Share this with your admin or support.'"></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ══ COLLAPSED BUTTON ══════════════════════════════════════════ --}}
    <button
        x-show="!open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 scale-90"
        x-transition:enter-end="opacity-100 scale-100"
        @click="openPanel()"
        class="flex items-center gap-2 bg-white rounded-full shadow-lg border border-gray-100 pl-2.5 pr-3.5 py-2 hover:shadow-xl transition-all"
        style="pointer-events:all"
        aria-label="Open R Bunny assistant"
        aria-haspopup="dialog"
    >
        <img src="/images/mascots/r-bunny-waving.webp"
             alt="R Bunny" class="w-6 h-6 object-contain shrink-0" loading="lazy">
        <span class="text-xs font-semibold"
              :class="fullyReady && onboardingPercent >= 100 ? 'text-emerald-600' : 'text-[#1E1B4B]'"
              x-text="fullyReady && onboardingPercent >= 100 ? 'R Bunny ✓' : 'R Bunny'">
        </span>
        {{-- Badge --}}
        <span x-show="badgeCount > 0"
              x-text="badgeCount > 9 ? '9+' : badgeCount"
              class="min-w-[1.1rem] h-[1.1rem] px-0.5 bg-[#FF5733] rounded-full text-white text-[9px] font-bold flex items-center justify-center leading-none"
              aria-label="Pending items" aria-live="polite">
        </span>
        <span x-show="badgeCount === 0"
              class="w-2 h-2 rounded-full bg-[#7B61FF] animate-pulse shrink-0" aria-hidden="true"></span>
    </button>
</div>

@push('scripts')
<script>
function rBunnyAssistant(tenantId) {
    const CSRF    = () => document.querySelector('meta[name=csrf-token]')?.content ?? '';
    const hdrs    = () => ({ 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' });
    const pHdrs   = () => ({ ...hdrs(), 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF() });
    const apiQ    = (path) => {
        const qs = tenantId ? `?tenant_id=${encodeURIComponent(tenantId)}&page=${encodeURIComponent(window.location.pathname)}` : `?page=${encodeURIComponent(window.location.pathname)}`;
        return `/api/${path}${qs}`;
    };
    const post = (path, body = {}) => fetch(`/api/${path}`, {
        method: 'POST', headers: pHdrs(),
        body: JSON.stringify(tenantId ? { ...body, tenant_id: tenantId } : body),
    });

    return {
        open:         false,
        mode:         'home',
        loading:      true,
        showPrefs:    false,
        showTasks:    false,

        // Home mode
        suggestions:  [],
        quickActions: [],
        badgeCount:   0,

        // Setup mode (onboarding)
        onboardingTasks:   [],
        nextTask:          null,
        onboardingDone:    0,
        onboardingTotal:   0,
        onboardingPercent: 0,
        profilePercent:    0,
        fullyReady:        false,
        onboardingSnoozed: false,

        // Help mode
        pageHelp:     null,
        handoffIssue: '',
        handoffDone:  false,
        handoffRef:   null,

        // Prefs
        prefs: {
            show_proactive_tips: true, show_onboarding_tips: true,
            show_task_reminders: true, show_message_reminders: true,
            collapsed_by_default: false,
        },
        prefItems: [
            { key: 'show_proactive_tips',    label: 'Proactive suggestions' },
            { key: 'show_onboarding_tips',   label: 'Onboarding tips' },
            { key: 'show_task_reminders',    label: 'Task reminders' },
            { key: 'show_message_reminders', label: 'Message reminders' },
            { key: 'collapsed_by_default',   label: 'Start collapsed' },
        ],

        get tabs() {
            return [
                { id: 'home',  label: 'Home',  badge: this.badgeCount },
                { id: 'setup', label: 'Setup', badge: this.fullyReady ? 0 : (this.onboardingTotal - this.onboardingDone) },
                { id: 'help',  label: 'Help',  badge: 0 },
            ];
        },

        get headerMascot() {
            if (this.mode === 'help')  return 'helper-question';
            if (this.mode === 'setup') return this.fullyReady ? 'celebration' : 'waving';
            if (this.suggestions.length > 0 && ['urgent','high'].includes(this.suggestions[0]?.priority)) return 'warning';
            return 'waving';
        },

        get headerSubtitle() {
            if (this.mode === 'help')  return 'Page help';
            if (this.mode === 'setup') return this.fullyReady ? 'You\'re ready!' : `${this.onboardingPercent}% complete`;
            if (this.badgeCount > 0)  return `${this.badgeCount} item${this.badgeCount > 1 ? 's' : ''} need attention`;
            return 'Your referral assistant';
        },

        async init() {
            await this.load();
            if (this.prefs.collapsed_by_default) this.open = false;
        },

        async load() {
            this.loading = true;
            try {
                const [bunnyRes, onboardRes] = await Promise.all([
                    fetch(apiQ('r-bunny/status'), { headers: hdrs() }),
                    fetch(apiQ('onboarding/status'), { headers: hdrs() }),
                ]);

                const bunny = await bunnyRes.json();
                if (bunny.enabled) {
                    this.suggestions  = bunny.suggestions  ?? [];
                    this.quickActions = bunny.quick_actions ?? [];
                    this.badgeCount   = bunny.badge_count   ?? 0;
                    this.pageHelp     = bunny.page_help     ?? null;
                    this.prefs        = bunny.prefs         ?? this.prefs;
                }

                const ob = await onboardRes.json();
                if (ob.enabled) {
                    this.onboardingTasks   = ob.tasks           ?? [];
                    this.nextTask          = ob.next_task        ?? null;
                    this.onboardingDone    = ob.progress?.done   ?? 0;
                    this.onboardingTotal   = ob.progress?.total  ?? 0;
                    this.onboardingPercent = ob.progress?.percent ?? 0;
                    this.profilePercent    = ob.profile_progress ?? 0;
                    this.fullyReady        = ob.progress?.fully_ready ?? false;
                    this.onboardingSnoozed = ob.is_snoozed ?? false;
                }
            } catch(e) {}
            this.loading = false;
        },

        openPanel() {
            this.open = true;
            // Record last_opened
            post('r-bunny/preferences', { ...this.prefs }).catch(() => {});
        },

        async onWalkthroughDone() {
            await this.load();
            setTimeout(() => { this.open = true; this.mode = 'setup'; }, 400);
        },

        // ── Home mode actions ──────────────────────────────────────
        async dismissSuggestion(key) {
            this.suggestions = this.suggestions.filter(s => s.key !== key);
            this.badgeCount  = this.suggestions.filter(s => ['urgent','high'].includes(s.priority)).length;
            try { await post('r-bunny/dismiss', { key }); } catch(e) {}
        },

        async snoozeSuggestion(key) {
            this.suggestions = this.suggestions.filter(s => s.key !== key);
            this.badgeCount  = this.suggestions.filter(s => ['urgent','high'].includes(s.priority)).length;
            try { await post('r-bunny/snooze', { key, hours: 24 }); } catch(e) {}
        },

        // ── Setup mode actions ─────────────────────────────────────
        async completeOnboardingTask(key) {
            try {
                const res  = await post('onboarding/task/complete', { task_key: key });
                const data = await res.json();
                this._applyOnboarding(data);
                this.$dispatch('show-toast', { type: 'success', message: 'Nice hop! Task marked as done. ✓' });
            } catch(e) {}
        },

        async dismissOnboardingTask(key) {
            try {
                const res  = await post('onboarding/task/dismiss', { task_key: key });
                const data = await res.json();
                this._applyOnboarding(data);
            } catch(e) {}
        },

        async snoozeOnboarding() {
            try {
                await post('onboarding/snooze', { hours: 24 });
                this.onboardingSnoozed = true;
                this.open = false;
                this.$dispatch('show-toast', { type: 'info', message: "R Bunny will rest for 24 hours. You can always wake me up." });
            } catch(e) {}
        },

        _applyOnboarding(data) {
            if (!data.enabled) return;
            this.onboardingTasks   = data.tasks           ?? [];
            this.nextTask          = data.next_task        ?? null;
            this.onboardingDone    = data.progress?.done   ?? 0;
            this.onboardingTotal   = data.progress?.total  ?? 0;
            this.onboardingPercent = data.progress?.percent ?? 0;
            this.profilePercent    = data.profile_progress ?? 0;
            this.fullyReady        = data.progress?.fully_ready ?? false;
        },

        // ── Help mode actions ──────────────────────────────────────
        async requestHandoff() {
            if (!this.handoffIssue.trim()) return;
            try {
                const res  = await post('r-bunny/handoff', { issue: this.handoffIssue });
                const data = await res.json();
                this.handoffDone = true;
                this.handoffRef  = data.ref ?? null;
            } catch(e) {
                this.handoffDone = true;
            }
        },

        // ── Preferences ───────────────────────────────────────────
        async togglePref(key) {
            this.prefs[key] = !this.prefs[key];
            try { await post('r-bunny/preferences', this.prefs); } catch(e) {}
        },
    };
}
</script>
@endpush
