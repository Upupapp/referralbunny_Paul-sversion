@extends('layouts.app')
@section('title', 'Messages')

@section('nav')
    @include('tenant._nav')
@endsection

@section('topbar-actions')
    <button x-data @click="$dispatch('open-compose')"
            class="btn-primary">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        <span class="hidden sm:inline">New Message</span>
    </button>
@endsection

@section('content')
<div
    x-data="messaging()"
    x-init="init()"
    x-ref="root"
    @open-compose.window="openCompose = true"
    class="flex flex-col"
    style="height: calc(100dvh - 88px); min-height: 400px;"
>

    {{-- ── Error banner ─────────────────────────────────────── --}}
    <div x-show="globalError"
         class="flex items-center gap-3 mb-3 px-4 py-3 rounded-2xl border bg-red-50 border-red-200 text-red-700 text-sm shrink-0">
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
        </svg>
        <span x-text="globalError" class="flex-1"></span>
        <button @click="globalError = null" class="shrink-0 text-red-400 hover:text-red-600">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    {{-- ── Chat Panel ───────────────────────────────────────── --}}
    <div class="card p-0 overflow-hidden flex flex-1 min-h-0">

        {{-- ── Left: Thread List ────────────────────────────── --}}
        <div :class="mobilePane === 'list' ? 'flex' : 'hidden lg:flex'"
             class="w-full lg:w-72 border-r border-gray-100 flex-col shrink-0 min-h-0">

            {{-- Left header --}}
            <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between shrink-0">
                <div>
                    <h2 class="text-sm font-bold text-[#1E1B4B]">Messages</h2>
                    <p class="text-[10px] text-gray-400 mt-0.5">
                        <span x-text="threads.length">0</span>
                        conversation<span x-show="threads.length !== 1">s</span>
                        <template x-if="totalUnread > 0">
                            <span> · <strong x-text="totalUnread"></strong> unread</span>
                        </template>
                    </p>
                </div>
                <div class="flex items-center gap-1.5 shrink-0">
                    {{-- Mark all read (only when unread > 0) --}}
                    <template x-if="totalUnread > 0">
                        <button x-data="{ busy: false }"
                                @click="if(busy) return; busy=true;
                                    fetch('{{ route('tenant.messages.mark-all-read', $tenant->id) }}', {
                                        method:'POST', credentials:'same-origin',
                                        headers:{'X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content,'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}
                                    }).then(()=>{
                                        threads.forEach(t=>t.admin_unread=0);
                                        partnerThreads.forEach(t=>t.admin_unread=0);
                                        busy=false;
                                    }).catch(()=>busy=false)"
                                class="w-7 h-7 rounded-full flex items-center justify-center text-gray-400 hover:text-violet-600 hover:bg-violet-50 transition-colors"
                                :disabled="busy" title="Mark all as read">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                        </button>
                    </template>
                    <button @click="openCompose = true"
                            class="w-7 h-7 rounded-full flex items-center justify-center text-white transition-all hover:shadow-md"
                            style="background:linear-gradient(135deg,#7B61FF,#9B8BFF)"
                            title="New Message">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                        </svg>
                    </button>
                </div>
            </div>

            {{-- Tabs --}}
            <div class="flex border-b border-gray-100 shrink-0">
                <button @click="setInbox('referrers')"
                        :class="inbox==='referrers' ? 'text-[#7B61FF] border-b-2 border-[#7B61FF] font-semibold' : 'text-gray-400 hover:text-gray-600'"
                        class="flex-1 text-[11px] py-2.5 transition-colors">Referrers</button>
                <button @click="setInbox('partners')"
                        :class="inbox==='partners' ? 'text-[#7B61FF] border-b-2 border-[#7B61FF] font-semibold' : 'text-gray-400 hover:text-gray-600'"
                        class="flex-1 text-[11px] py-2.5 transition-colors relative">
                    Partners
                    <span x-show="partnerTotalUnread > 0"
                          class="absolute top-1.5 right-1 min-w-[14px] h-3.5 px-0.5 rounded-full bg-blue-500 text-white text-[8px] font-bold flex items-center justify-center"
                          x-text="partnerTotalUnread > 9 ? '9+' : partnerTotalUnread"></span>
                </button>
            </div>

            {{-- Sub-tabs (only for referrers inbox) --}}
            <div x-show="inbox==='referrers'" class="flex border-b border-gray-100 shrink-0">
                <button @click="setTab('all')"
                        :class="activeTab==='all' ? 'text-[#7B61FF] border-b-2 border-[#7B61FF] font-semibold' : 'text-gray-400 hover:text-gray-600'"
                        class="flex-1 text-[10px] py-2 transition-colors">All</button>
                <button @click="setTab('needs_reply')"
                        :class="activeTab==='needs_reply' ? 'text-[#7B61FF] border-b-2 border-[#7B61FF] font-semibold' : 'text-gray-400 hover:text-gray-600'"
                        class="flex-1 text-[10px] py-2 transition-colors relative">
                    Needs Reply
                    <span x-show="needsReplyCount > 0"
                          class="absolute top-1 right-1 w-3 h-3 rounded-full bg-orange-400 text-white text-[7px] font-bold flex items-center justify-center"
                          x-text="needsReplyCount > 9 ? '9+' : needsReplyCount"></span>
                </button>
                <button @click="setTab('unread')"
                        :class="activeTab==='unread' ? 'text-[#7B61FF] border-b-2 border-[#7B61FF] font-semibold' : 'text-gray-400 hover:text-gray-600'"
                        class="flex-1 text-[10px] py-2 transition-colors">Unread</button>
            </div>

            {{-- Search --}}
            <div class="p-2.5 border-b border-gray-100 shrink-0">
                <div class="relative">
                    <svg class="w-3.5 h-3.5 text-gray-400 absolute left-2.5 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input x-model="search" type="text" placeholder="Search conversations…"
                           class="w-full text-xs py-2 pl-8 pr-8 bg-gray-50 rounded-lg border-0 outline-none focus:ring-1 focus:ring-[#7B61FF] focus:bg-white transition-all">
                    <button x-show="search" @click="search = ''"
                            class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-300 hover:text-gray-500">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </div>

            {{-- Thread list --}}
            <div class="flex-1 overflow-y-auto">

                {{-- Loading state --}}
                <template x-if="loading">
                    <div class="flex flex-col items-center justify-center py-10 gap-3 text-gray-400">
                        <svg class="w-5 h-5 animate-spin text-[#7B61FF]" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 22 6.477 22 12h-4z"/>
                        </svg>
                        <span class="text-xs">Loading conversations…</span>
                    </div>
                </template>

                {{-- Error state --}}
                <template x-if="!loading && loadError">
                    <div class="flex flex-col items-center justify-center py-10 px-4 text-center">
                        <img src="/images/mascots/r-bunny-warning-error.webp" alt="" class="w-12 h-12 object-contain mb-2 opacity-80">
                        <p class="text-xs text-red-500 font-medium">Failed to load conversations</p>
                        <button @click="loadThreads()" class="mt-2 text-xs text-[#7B61FF] hover:underline">Try again</button>
                    </div>
                </template>

                {{-- Referrers: empty state --}}
                <template x-if="inbox==='referrers' && !loading && !loadError && filteredThreads.length === 0">
                    <div class="flex flex-col items-center justify-center py-10 px-4 text-center">
                        <img src="/images/mascots/r-bunny-sleeping.webp" alt="" class="w-12 h-12 object-contain mb-3 opacity-60">
                        <p class="text-xs font-medium text-gray-500" x-show="search">No results for "<span x-text="search"></span>"</p>
                        <p class="text-xs font-medium text-gray-500" x-show="!search && activeTab === 'needs_reply'">No messages waiting for a reply</p>
                        <p class="text-xs font-medium text-gray-500" x-show="!search && activeTab === 'unread'">All caught up — no unread messages</p>
                        <p class="text-xs font-medium text-gray-500" x-show="!search && activeTab === 'all'">No conversations yet</p>
                        <p class="text-xs text-gray-400 mt-1" x-show="!search && activeTab === 'all'">Start a conversation with a Referrer.</p>
                        <button x-show="!search && activeTab === 'all'"
                                @click="openCompose = true"
                                class="mt-3 btn-primary text-xs">Start a Conversation</button>
                    </div>
                </template>

                {{-- Referrers: thread list --}}
                <template x-if="inbox==='referrers' && !loading && !loadError">
                    <div>
                        <template x-for="thread in filteredThreads" :key="thread.id">
                            <button
                                @click="selectThread(thread)"
                                class="w-full text-left px-3 py-3 hover:bg-gray-50 transition-colors border-b border-gray-50/70"
                                :class="activeThread?.id === thread.id && !activePartnerThread
                                    ? 'bg-[#F5F3FF] border-l-[3px] border-l-[#7B61FF]'
                                    : 'border-l-[3px] border-l-transparent'"
                            >
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-full bg-[#EDE9FE] flex items-center justify-center shrink-0 text-[#7B61FF] font-bold text-xs"
                                         x-text="(thread.reseller_name || '?').charAt(0).toUpperCase()"></div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center justify-between gap-1">
                                            <span class="text-xs truncate"
                                                  :class="thread.admin_unread > 0 ? 'font-bold text-[#1E1B4B]' : 'font-semibold text-[#1E1B4B]'"
                                                  x-text="thread.reseller_name || 'Unknown'"></span>
                                            <span class="text-[9px] text-gray-400 shrink-0 ml-1" x-text="thread.last_message_at ?? ''"></span>
                                        </div>
                                        <div class="flex items-center justify-between gap-1 mt-0.5">
                                            <p class="text-[11px] text-gray-400 truncate flex-1"
                                               x-text="thread.last_message_preview || 'No messages yet'"></p>
                                            <span x-show="thread.admin_unread > 0"
                                                  class="shrink-0 min-w-[1rem] h-4 px-1 rounded-full bg-[#7B61FF] flex items-center justify-center ml-1">
                                                <span class="text-[8px] font-bold text-white"
                                                      x-text="thread.admin_unread > 9 ? '9+' : thread.admin_unread"></span>
                                            </span>
                                            <svg class="lg:hidden w-3.5 h-3.5 text-gray-300 shrink-0 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                            </svg>
                                        </div>
                                    </div>
                                </div>
                            </button>
                        </template>
                    </div>
                </template>

                {{-- Partners: loading --}}
                <template x-if="inbox==='partners' && partnerLoading">
                    <div class="flex flex-col items-center justify-center py-10 gap-3 text-gray-400">
                        <svg class="w-5 h-5 animate-spin text-blue-500" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 22 6.477 22 12h-4z"/>
                        </svg>
                        <span class="text-xs">Loading partner messages…</span>
                    </div>
                </template>

                {{-- Partners: empty --}}
                <template x-if="inbox==='partners' && !partnerLoading && filteredPartnerThreads.length === 0">
                    <div class="flex flex-col items-center justify-center py-10 px-4 text-center">
                        <img src="/images/mascots/r-bunny-sleeping.webp" alt="" class="w-12 h-12 object-contain mb-3 opacity-60">
                        <p class="text-xs font-medium text-gray-500">No partner messages yet</p>
                        <p class="text-xs text-gray-400 mt-1">Partners can message you via their portal.</p>
                    </div>
                </template>

                {{-- Partners: thread list --}}
                <template x-if="inbox==='partners' && !partnerLoading && filteredPartnerThreads.length > 0">
                    <div>
                        <template x-for="thread in filteredPartnerThreads" :key="thread.id">
                            <button
                                @click="selectPartnerThread(thread)"
                                class="w-full text-left px-3 py-3 hover:bg-gray-50 transition-colors border-b border-gray-50/70"
                                :class="activePartnerThread?.id === thread.id
                                    ? 'bg-blue-50 border-l-[3px] border-l-blue-500'
                                    : 'border-l-[3px] border-l-transparent'"
                            >
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center shrink-0 text-blue-700 font-bold text-xs"
                                         x-text="(thread.partner_name || '?').charAt(0).toUpperCase()"></div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center justify-between gap-1">
                                            <span class="text-xs truncate"
                                                  :class="thread.admin_unread > 0 ? 'font-bold text-[#1E1B4B]' : 'font-semibold text-[#1E1B4B]'"
                                                  x-text="thread.partner_name || 'Partner'"></span>
                                            <span class="text-[9px] text-gray-400 shrink-0 ml-1" x-text="thread.last_at ?? ''"></span>
                                        </div>
                                        <div class="flex items-center justify-between gap-1 mt-0.5">
                                            <p class="text-[11px] truncate flex-1"
                                               :class="thread.admin_unread > 0 ? 'text-[#1E1B4B] font-medium' : 'text-gray-400'"
                                               x-text="thread.last_preview || 'No messages yet'"></p>
                                            <span x-show="thread.admin_unread > 0"
                                                  class="shrink-0 min-w-[1rem] h-4 px-1 rounded-full bg-blue-500 flex items-center justify-center ml-1">
                                                <span class="text-[8px] font-bold text-white"
                                                      x-text="thread.admin_unread > 9 ? '9+' : thread.admin_unread"></span>
                                            </span>
                                        </div>
                                        <p class="text-[10px] text-gray-400 mt-0.5 truncate"
                                           x-text="thread.thread_type === 'direct' ? 'Direct message' : 'Deal thread'"></p>
                                    </div>
                                </div>
                            </button>
                        </template>
                    </div>
                </template>

            </div>
        </div>

        {{-- ── Right: Active Thread ─────────────────────────── --}}
        <div :class="mobilePane === 'chat' ? 'flex' : 'hidden lg:flex'"
             class="flex-1 flex-col min-w-0">

            {{-- No thread selected --}}
            <template x-if="!activeThread && !activePartnerThread">
                <div class="flex-1 flex flex-col items-center justify-center text-center p-8">
                    <img src="/images/mascots/r-bunny-helper-question.webp" alt="" class="w-16 h-16 object-contain mb-3 opacity-70">
                    <h3 class="text-[#1E1B4B] font-semibold text-sm">Select a conversation</h3>
                    <p class="text-gray-400 text-xs mt-1 max-w-xs">Choose from the list on the left, or tap <strong>+</strong> to message a Referrer, Partner, Admin, or Contact.</p>
                    <button @click="openCompose = true"
                            class="mt-4 btn-secondary text-xs hidden lg:inline-flex">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        New Message
                    </button>
                </div>
            </template>

            {{-- Active referrer thread --}}
            <template x-if="activeThread && !activePartnerThread">
                <div class="flex flex-col h-full min-h-0">

                    {{-- Thread header --}}
                    <div class="px-3 sm:px-4 py-3 border-b border-gray-100 flex items-center gap-2 shrink-0">
                        <button @click="mobilePane = 'list'; activeThread = null"
                                class="lg:hidden p-1.5 -ml-0.5 rounded-lg text-gray-400 hover:bg-gray-100 transition-colors shrink-0">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                            </svg>
                        </button>
                        <div class="w-8 h-8 rounded-full bg-[#EDE9FE] flex items-center justify-center text-[#7B61FF] font-bold text-xs shrink-0"
                             x-text="(activeThread.reseller_name || '?').charAt(0).toUpperCase()"></div>
                        <div class="flex-1 min-w-0">
                            <p class="font-semibold text-sm text-[#1E1B4B] truncate" x-text="activeThread.reseller_name || 'Referrer'"></p>
                            <p class="text-[10px] text-gray-400 truncate" x-text="activeThread.reseller_email || ''"></p>
                        </div>
                        <span class="hidden sm:inline-flex items-center gap-1 text-[10px] text-gray-400 bg-gray-50 rounded-full px-2.5 py-1 shrink-0">
                            <span class="w-1.5 h-1.5 rounded-full bg-green-400"></span>
                            In-app
                        </span>
                    </div>

                    {{-- Reminder banner --}}
                    <template x-if="activeThreadReminder">
                        <div class="mx-3 mt-2 flex items-center gap-2 bg-amber-50 border border-amber-200 rounded-xl px-3 py-2 shrink-0">
                            <svg class="w-3.5 h-3.5 text-amber-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </svg>
                            <p class="text-[11px] text-amber-700 flex-1" x-text="activeThreadReminder?.body"></p>
                            <button @click="resolveThreadReminder()" class="text-[10px] text-amber-500 hover:text-amber-700 font-medium shrink-0 transition-colors">✕</button>
                        </div>
                    </template>

                    {{-- Messages area --}}
                    <div class="flex-1 overflow-y-auto p-3 sm:p-4 space-y-3 min-h-0" x-ref="messageArea">
                        <template x-if="loadingMessages">
                            <div class="flex items-center justify-center gap-2 py-8 text-gray-400">
                                <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 22 6.477 22 12h-4z"/>
                                </svg>
                                <span class="text-xs">Loading messages…</span>
                            </div>
                        </template>
                        <template x-if="!loadingMessages && messageLoadError">
                            <div class="flex flex-col items-center justify-center py-8 text-center">
                                <img src="/images/mascots/r-bunny-warning-error.webp" alt="" class="w-12 h-12 object-contain mb-2 opacity-80">
                                <p class="text-xs text-red-500 font-medium">Failed to load messages</p>
                                <button @click="retryLoadMessages()" class="mt-2 text-xs text-[#7B61FF] hover:underline">Try again</button>
                            </div>
                        </template>
                        <template x-if="!loadingMessages && !messageLoadError && activeMessages.length === 0">
                            <div class="flex flex-col items-center justify-center py-10 text-center">
                                <img src="/images/mascots/r-bunny-waving.webp" alt="" class="w-12 h-12 object-contain mb-2 opacity-70">
                                <p class="text-xs text-gray-400">No messages yet — send the first one below.</p>
                            </div>
                        </template>
                        <template x-for="msg in activeMessages" :key="msg.id">
                            <div :class="msg.sender_type === 'admin' ? 'flex justify-end' : 'flex justify-start'">
                                <div :class="msg.sender_type === 'admin'
                                        ? 'text-white rounded-2xl rounded-br-sm'
                                        : 'bg-gray-100 text-[#1E1B4B] rounded-2xl rounded-bl-sm'"
                                     :style="msg.sender_type === 'admin' ? 'background:linear-gradient(135deg,#7B61FF,#9B8BFF)' : ''"
                                     class="max-w-[78%] sm:max-w-[70%] px-3.5 sm:px-4 py-2.5">
                                    <p class="text-sm leading-relaxed whitespace-pre-wrap break-words" x-text="msg.body"></p>
                                    <p :class="msg.sender_type === 'admin' ? 'text-white/50' : 'text-gray-400'"
                                       class="text-[10px] mt-1 text-right" :title="msg.created_at_full"
                                       x-text="msg.created_at"></p>
                                </div>
                            </div>
                        </template>
                    </div>

                    <div x-show="sendError"
                         class="mx-3 mb-1 px-3 py-2 rounded-lg bg-red-50 border border-red-200 text-red-600 text-xs flex items-center gap-2 shrink-0">
                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01"/>
                        </svg>
                        <span x-text="sendError"></span>
                        <button @click="sendError = null" class="ml-auto text-red-400 hover:text-red-600">✕</button>
                    </div>

                    <div class="p-2.5 sm:p-3 border-t border-gray-100 shrink-0">
                        <form @submit.prevent="sendReply()" class="flex gap-2 items-end">
                            <textarea x-model="replyBody" placeholder="Type a message…" rows="1"
                                      class="flex-1 text-sm bg-gray-50 border border-gray-200 rounded-xl px-3 sm:px-3.5 py-2.5 resize-none outline-none focus:ring-2 focus:ring-[#7B61FF]/20 focus:border-[#7B61FF] transition-all"
                                      style="min-height:40px; max-height:120px;"
                                      @input="$el.style.height='40px'; $el.style.height=$el.scrollHeight+'px'"
                                      @keydown.enter.prevent.exact="sendReply()"
                                      @keydown.enter.shift.prevent="replyBody += '\n'"></textarea>
                            <button type="submit"
                                    class="shrink-0 w-10 h-10 rounded-xl flex items-center justify-center text-white transition-all hover:shadow-md disabled:opacity-40 disabled:cursor-not-allowed"
                                    style="background:linear-gradient(135deg,#7B61FF,#9B8BFF)"
                                    :disabled="!replyBody.trim() || sending">
                                <svg x-show="!sending" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                                </svg>
                                <svg x-show="sending" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 22 6.477 22 12h-4z"/>
                                </svg>
                            </button>
                        </form>
                        <p class="text-[9px] text-gray-400 mt-1.5 pl-1 hidden sm:block">Enter to send · Shift+Enter for new line</p>
                    </div>
                </div>
            </template>

            {{-- Active partner thread --}}
            <template x-if="activePartnerThread">
                <div class="flex flex-col h-full min-h-0">

                    {{-- Partner thread header --}}
                    <div class="px-3 sm:px-4 py-3 border-b border-gray-100 flex items-center gap-2 shrink-0">
                        <button @click="mobilePane = 'list'; activePartnerThread = null"
                                class="lg:hidden p-1.5 -ml-0.5 rounded-lg text-gray-400 hover:bg-gray-100 transition-colors shrink-0">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                            </svg>
                        </button>
                        <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center text-blue-700 font-bold text-xs shrink-0"
                             x-text="(activePartnerThread.partner_name || '?').charAt(0).toUpperCase()"></div>
                        <div class="flex-1 min-w-0">
                            <p class="font-semibold text-sm text-[#1E1B4B] truncate" x-text="activePartnerThread.partner_name || 'Partner'"></p>
                            <p class="text-[10px] text-gray-400 truncate"
                               x-text="activePartnerThread.thread_type === 'direct' ? 'Direct message · Partner' : 'Deal conversation · Partner'"></p>
                        </div>
                        <span class="hidden sm:inline-flex items-center gap-1 text-[10px] bg-blue-50 text-blue-500 rounded-full px-2.5 py-1 shrink-0">
                            <span class="w-1.5 h-1.5 rounded-full bg-blue-400"></span>
                            Partner
                        </span>
                    </div>

                    {{-- Partner messages area --}}
                    <div class="flex-1 overflow-y-auto p-3 sm:p-4 space-y-3 min-h-0" x-ref="partnerMessageArea">
                        <template x-if="loadingPartnerMessages">
                            <div class="flex items-center justify-center gap-2 py-8 text-gray-400">
                                <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 22 6.477 22 12h-4z"/>
                                </svg>
                                <span class="text-xs">Loading messages…</span>
                            </div>
                        </template>
                        <template x-if="!loadingPartnerMessages && partnerMessages.length === 0">
                            <div class="flex flex-col items-center justify-center py-10 text-center">
                                <img src="/images/mascots/r-bunny-waving.webp" alt="" class="w-12 h-12 object-contain mb-2 opacity-70">
                                <p class="text-xs text-gray-400">No messages yet — send the first one below.</p>
                            </div>
                        </template>
                        <template x-for="msg in partnerMessages" :key="msg.id">
                            <div :class="msg.sender_type === 'admin' ? 'flex justify-end' : 'flex justify-start'">
                                <div :class="msg.sender_type === 'admin'
                                        ? 'text-white rounded-2xl rounded-br-sm'
                                        : 'bg-blue-50 text-[#1E1B4B] rounded-2xl rounded-bl-sm'"
                                     :style="msg.sender_type === 'admin' ? 'background:linear-gradient(135deg,#7B61FF,#9B8BFF)' : ''"
                                     class="max-w-[78%] sm:max-w-[70%] px-3.5 sm:px-4 py-2.5">
                                    <p class="text-[10px] font-semibold mb-1"
                                       :class="msg.sender_type === 'admin' ? 'text-white/70' : 'text-blue-600'"
                                       x-text="msg.sender_name"></p>
                                    <p class="text-sm leading-relaxed whitespace-pre-wrap break-words" x-text="msg.body"></p>
                                    <p :class="msg.sender_type === 'admin' ? 'text-white/50' : 'text-gray-400'"
                                       class="text-[10px] mt-1 text-right" :title="msg.created_at_full"
                                       x-text="msg.created_at"></p>
                                </div>
                            </div>
                        </template>
                    </div>

                    <div x-show="partnerSendError"
                         class="mx-3 mb-1 px-3 py-2 rounded-lg bg-red-50 border border-red-200 text-red-600 text-xs flex items-center gap-2 shrink-0">
                        <span x-text="partnerSendError"></span>
                        <button @click="partnerSendError = null" class="ml-auto text-red-400 hover:text-red-600">✕</button>
                    </div>

                    <div class="p-2.5 sm:p-3 border-t border-gray-100 shrink-0">
                        <form @submit.prevent="sendPartnerReply()" class="flex gap-2 items-end">
                            <textarea x-model="partnerReplyBody" placeholder="Reply to partner…" rows="1"
                                      class="flex-1 text-sm bg-gray-50 border border-gray-200 rounded-xl px-3 sm:px-3.5 py-2.5 resize-none outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all"
                                      style="min-height:40px; max-height:120px;"
                                      @input="$el.style.height='40px'; $el.style.height=$el.scrollHeight+'px'"
                                      @keydown.enter.prevent.exact="sendPartnerReply()"
                                      @keydown.enter.shift.prevent="partnerReplyBody += '\n'"></textarea>
                            <button type="submit"
                                    class="shrink-0 w-10 h-10 rounded-xl flex items-center justify-center text-white transition-all hover:shadow-md disabled:opacity-40 disabled:cursor-not-allowed"
                                    style="background:linear-gradient(135deg,#3B82F6,#60A5FA)"
                                    :disabled="!partnerReplyBody.trim() || partnerSending">
                                <svg x-show="!partnerSending" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                                </svg>
                                <svg x-show="partnerSending" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 22 6.477 22 12h-4z"/>
                                </svg>
                            </button>
                        </form>
                        <p class="text-[9px] text-gray-400 mt-1.5 pl-1 hidden sm:block">Enter to send · Shift+Enter for new line</p>
                    </div>
                </div>
            </template>
        </div>
    </div>

    {{-- ── Compose Modal (send to anyone) — same messaging() scope, no nested x-data --}}
    <div x-show="openCompose" x-cloak
         class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-4"
         @keydown.escape.window="openCompose = false; resetCompose()">
        <div @click="openCompose = false; resetCompose()" class="absolute inset-0 bg-black/40 backdrop-blur-sm"></div>
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-xl flex flex-col max-h-[90dvh]" @click.stop
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 translate-y-4 sm:scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100">

            {{-- Header --}}
            <div class="flex items-center justify-between px-5 pt-5 pb-4 border-b border-gray-100 shrink-0">
                <div>
                    <h3 class="text-[#1E1B4B] font-bold text-base">New Message</h3>
                    <p class="text-gray-400 text-xs mt-0.5">Send to anyone in your workspace</p>
                </div>
                <button @click="openCompose = false; resetCompose()"
                        class="p-1.5 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Role filter chips --}}
            <div class="px-5 pt-4 pb-3 border-b border-gray-50 shrink-0">
                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">Filter by role</p>
                <div class="flex flex-wrap gap-1.5">
                    @php
                        $roleChips = [
                            ['key' => 'all',     'label' => 'All',      'count' => $resellers->count() + $partners->count() + $tenantUsers->count() + $contacts->count()],
                            ['key' => 'reseller','label' => 'Referrer', 'count' => $resellers->count()],
                            ['key' => 'partner', 'label' => 'Partner',  'count' => $partners->count()],
                            ['key' => 'admin',   'label' => 'Admin / Manager', 'count' => $tenantUsers->count()],
                            ['key' => 'contact', 'label' => 'Contact',  'count' => $contacts->count()],
                        ];
                    @endphp
                    @foreach($roleChips as $chip)
                    <button type="button"
                            @click="roleFilter = '{{ $chip['key'] }}'; recipientSearch = ''"
                            :class="roleFilter === '{{ $chip['key'] }}'
                                ? 'bg-[#7B61FF] text-white'
                                : 'bg-gray-100 text-gray-500 hover:bg-gray-200'"
                            class="px-3 py-1 rounded-full text-xs font-semibold transition-colors">
                        {{ $chip['label'] }}
                        <span class="ml-1 opacity-60">({{ $chip['count'] }})</span>
                    </button>
                    @endforeach
                </div>
            </div>

            <div class="flex-1 overflow-y-auto px-5 py-4 space-y-4 min-h-0">

                {{-- Recipient search + select --}}
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label class="text-xs font-semibold text-gray-600 uppercase tracking-wide">To</label>
                        <button type="button" @click="selectAll()" class="text-[10px] text-[#7B61FF] hover:underline">Select all visible</button>
                    </div>

                    <div class="relative mb-2">
                        <svg class="w-3.5 h-3.5 text-gray-400 absolute left-2.5 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <input x-model="recipientSearch" type="text" placeholder="Search by name or email…"
                               class="w-full text-xs py-2 pl-8 pr-3 bg-gray-50 rounded-lg border border-gray-200 outline-none focus:ring-1 focus:ring-[#7B61FF] transition-all">
                    </div>

                    {{-- Recipient list --}}
                    <div class="border border-gray-200 rounded-xl max-h-44 overflow-y-auto">
                        <template x-if="visibleRecipients.length === 0">
                            <p class="text-xs text-gray-400 text-center py-5">No recipients match your filter.</p>
                        </template>
                        <template x-for="r in visibleRecipients" :key="r.token">
                            <label class="flex items-center gap-3 px-3 py-2 hover:bg-gray-50 cursor-pointer border-b border-gray-50 last:border-0">
                                <input type="checkbox" :value="r.token" x-model="selectedTokens"
                                       class="rounded border-gray-300 text-[#7B61FF] focus:ring-[#7B61FF]/30">
                                <div class="w-7 h-7 rounded-full flex items-center justify-center text-white font-bold text-[10px] shrink-0"
                                     :style="'background:' + r.color">
                                    <span x-text="r.initials"></span>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-xs font-semibold text-[#1E1B4B] truncate" x-text="r.name"></p>
                                    <p class="text-[10px] text-gray-400 truncate" x-text="r.email + ' · ' + r.roleLabel"></p>
                                </div>
                                <span x-show="selectedTokens.includes(r.token)"
                                      class="w-4 h-4 rounded-full bg-[#7B61FF] flex items-center justify-center shrink-0">
                                    <svg class="w-2.5 h-2.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                                    </svg>
                                </span>
                            </label>
                        </template>
                    </div>

                    <p class="text-[10px] text-gray-400 mt-1">
                        <span x-text="selectedTokens.length"></span> selected
                    </p>
                </div>

                {{-- Message body --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">Message</label>
                    <textarea x-model="broadcastBody" rows="4"
                              placeholder="Write your message…"
                              class="form-input w-full text-sm resize-none" required></textarea>
                    <p class="text-[10px] text-gray-400 mt-1">
                        Referrers and Partners will see this in their Messages inbox. Admins and Contacts receive an in-app notification.
                    </p>
                </div>

                <div x-show="broadcastResult" class="text-xs px-3 py-2 rounded-lg"
                     :class="broadcastOk ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-600 border border-red-200'"
                     x-text="broadcastResult"></div>
            </div>

            {{-- Footer --}}
            <div class="px-5 py-4 border-t border-gray-100 flex gap-3 justify-end shrink-0">
                <button type="button" @click="openCompose = false; resetCompose()" class="btn-secondary text-sm">Cancel</button>
                <button type="button" @click="sendBroadcast()"
                        :disabled="broadcastSending || selectedTokens.length === 0 || !broadcastBody.trim()"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold text-white transition-all disabled:opacity-50"
                        style="background:linear-gradient(135deg,#7B61FF,#9B8BFF)">
                    <svg x-show="broadcastSending" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 22 6.477 22 12h-4z"/>
                    </svg>
                    <span x-text="broadcastSending ? 'Sending…' : 'Send to ' + selectedTokens.length + (selectedTokens.length === 1 ? ' person' : ' people')">Send</span>
                </button>
            </div>
        </div>
    </div>

</div>

@push('scripts')
<script>
function messaging() {
    const BASE = '{{ url("tenant/" . $tenant->id . "/messages") }}';
    const CSRF = document.querySelector('meta[name=csrf-token]')?.content ?? '';
    const hdrs     = (extra = {}) => ({ 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', ...extra });
    const postHdrs = () => ({ ...hdrs(), 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF });

    return {
        // State
        threads:              [],
        activeThread:         null,
        activeMessages:       [],
        openCompose:          false,
        search:               '',
        replyBody:            '',
        sending:              false,
        loading:              false,
        loadingMessages:      false,
        compose:              { reseller_id: '', body: '' },
        activeTab:            'all',
        needsReplyCount:      0,
        activeThreadReminder: null,
        mobilePane:           'list',
        // Error / UX state
        globalError:          null,
        loadError:            false,
        messageLoadError:     false,
        sendError:            null,
        composeError:         null,
        _retryThread:         null,
        // Partner inbox state
        inbox:                   'referrers',
        partnerThreads:          [],
        partnerLoading:          false,
        activePartnerThread:     null,
        partnerMessages:         [],
        loadingPartnerMessages:  false,
        partnerReplyBody:        '',
        partnerSending:          false,
        partnerSendError:        null,

        get filteredThreads() {
            const q    = this.search.toLowerCase().trim();
            let   list = this.threads;
            if (this.activeTab === 'unread')       list = list.filter(t => (t.admin_unread ?? 0) > 0);
            if (this.activeTab === 'needs_reply')  return list; // already pre-filtered from API
            if (!q) return list;
            return list.filter(t =>
                (t.reseller_name  ?? '').toLowerCase().includes(q) ||
                (t.reseller_email ?? '').toLowerCase().includes(q) ||
                (t.last_message_preview ?? '').toLowerCase().includes(q)
            );
        },

        get filteredPartnerThreads() {
            const q = this.search.toLowerCase().trim();
            if (!q) return this.partnerThreads;
            return this.partnerThreads.filter(t =>
                (t.partner_name  ?? '').toLowerCase().includes(q) ||
                (t.partner_email ?? '').toLowerCase().includes(q) ||
                (t.last_preview  ?? '').toLowerCase().includes(q)
            );
        },

        get totalUnread() {
            return this.threads.reduce((s, t) => s + (t.admin_unread ?? 0), 0);
        },

        get partnerTotalUnread() {
            return this.partnerThreads.reduce((s, t) => s + (t.admin_unread ?? 0), 0);
        },

        async init() {
            window.__messaging = this;
            this._allRecipients = window.__allRecipients ?? [];
            // Check URL for tab param
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('tab') === 'partners') {
                this.inbox = 'partners';
                await this.loadPartnerThreads();
                const threadId = urlParams.get('thread');
                if (threadId) {
                    const t = this.partnerThreads.find(x => x.id === threadId);
                    if (t) this.selectPartnerThread(t);
                }
            } else {
                await this.loadThreads();
                await this.loadNeedsReplyCount();
            }
        },

        _allRecipients: [],

        async setInbox(mode) {
            this.inbox               = mode;
            this.activeThread        = null;
            this.activePartnerThread = null;
            this.activeMessages      = [];
            this.partnerMessages     = [];
            this.mobilePane          = 'list';
            if (mode === 'partners') {
                await this.loadPartnerThreads();
            } else {
                await this.loadThreads(false);
            }
        },

        async loadPartnerThreads() {
            this.partnerLoading = true;
            try {
                const r = await fetch(`${BASE}/partner-threads`, { headers: hdrs() });
                if (!r.ok) throw new Error();
                this.partnerThreads = await r.json();
            } catch(e) {
                this.globalError = 'Failed to load partner messages. Please refresh.';
            } finally {
                this.partnerLoading = false;
            }
        },

        async selectPartnerThread(thread) {
            this.activePartnerThread     = { ...thread };
            this.activeThread            = null;
            this.partnerMessages         = [];
            this.loadingPartnerMessages  = true;
            this.mobilePane              = 'chat';
            try {
                const r = await fetch(`${BASE}/partner-threads/${thread.id}`, { headers: hdrs() });
                if (!r.ok) throw new Error();
                const d = await r.json();
                this.activePartnerThread = { ...this.activePartnerThread, ...d.thread };
                this.partnerMessages     = d.messages ?? [];
                const t = this.partnerThreads.find(x => x.id === thread.id);
                if (t) t.admin_unread = 0;
            } catch(e) {
                this.partnerSendError = 'Failed to load messages.';
            } finally {
                this.loadingPartnerMessages = false;
                this.$nextTick(() => {
                    const el = this.$refs.partnerMessageArea;
                    if (el) el.scrollTop = el.scrollHeight;
                });
            }
        },

        async sendPartnerReply() {
            if (!this.partnerReplyBody.trim() || this.partnerSending || !this.activePartnerThread) return;
            this.partnerSending   = true;
            this.partnerSendError = null;
            const body = this.partnerReplyBody;
            this.partnerReplyBody = '';
            try {
                const r = await fetch(`${BASE}/partner-threads/${this.activePartnerThread.id}/reply`, {
                    method: 'POST', headers: postHdrs(),
                    body: JSON.stringify({ body }),
                });
                if (!r.ok) {
                    const d = await r.json().catch(() => ({}));
                    throw new Error(d.message || 'Failed to send');
                }
                const msg = await r.json();
                this.partnerMessages.push(msg);
                const t = this.partnerThreads.find(x => x.id === this.activePartnerThread.id);
                if (t) t.last_preview = body.substring(0, 60);
                this.$nextTick(() => {
                    const el = this.$refs.partnerMessageArea;
                    if (el) el.scrollTop = el.scrollHeight;
                    this.$el.querySelector('textarea[x-model="partnerReplyBody"]')?.dispatchEvent(new Event('input'));
                });
                this.$dispatch('show-toast', { type: 'success', message: 'Reply sent.' });
            } catch(e) {
                this.partnerReplyBody = body;
                this.partnerSendError = e.message || 'Failed to send. Please try again.';
            } finally {
                this.partnerSending = false;
            }
        },

        setTab(tab) {
            this.activeTab = tab;
            if (tab === 'needs_reply') {
                this.loadNeedsReply();
            } else {
                this.loadThreads(false);
            }
        },

        async loadNeedsReplyCount() {
            try {
                const r = await fetch('/api/message-reminders/active', { headers: hdrs() });
                if (!r.ok) return;
                const d = await r.json();
                this.needsReplyCount = d.count ?? 0;
            } catch(e) {}
        },

        async loadNeedsReply() {
            this.loading   = true;
            this.loadError = false;
            try {
                const r = await fetch('/api/message-reminders/needs-reply', { headers: hdrs() });
                if (!r.ok) throw new Error('Failed');
                const d  = await r.json();
                this.threads = d.threads ?? [];
            } catch(e) {
                this.loadError = true;
            } finally {
                this.loading = false;
            }
        },

        async loadThreads(resetTab = true) {
            if (resetTab) this.activeTab = 'all';
            this.loading   = true;
            this.loadError = false;
            try {
                const r = await fetch(`${BASE}/threads`, { headers: hdrs() });
                if (!r.ok) throw new Error(`HTTP ${r.status}`);
                this.threads = await r.json();
            } catch(e) {
                this.loadError = true;
            } finally {
                this.loading = false;
            }
        },

        async selectThread(thread) {
            this.activeThread         = { ...thread };
            this.activeMessages       = [];
            this.activeThreadReminder = null;
            this.messageLoadError     = false;
            this.loadingMessages      = true;
            this.mobilePane           = 'chat';
            this._retryThread         = thread;

            // Fetch reminder for this thread
            try {
                const r = await fetch('/api/message-reminders/active', { headers: hdrs() });
                if (r.ok) {
                    const d = await r.json();
                    const m = (d.reminders ?? []).find(rem => rem.thread_id === thread.id);
                    if (m) this.activeThreadReminder = m;
                }
            } catch(e) {}

            // Fetch messages
            try {
                const r = await fetch(`${BASE}/threads/${thread.id}`, { headers: hdrs() });
                if (!r.ok) throw new Error(`HTTP ${r.status}`);
                const d = await r.json();
                this.activeThread   = { ...this.activeThread, ...d.thread };
                this.activeMessages = d.messages ?? [];
                const t = this.threads.find(x => x.id === thread.id);
                if (t) t.admin_unread = 0;
            } catch(e) {
                this.messageLoadError = true;
            } finally {
                this.loadingMessages = false;
                this.$nextTick(() => this.scrollToBottom());
            }
        },

        async retryLoadMessages() {
            if (this._retryThread) await this.selectThread(this._retryThread);
        },

        async resolveThreadReminder() {
            if (!this.activeThreadReminder) return;
            try {
                await fetch('/api/message-reminders/dismiss', {
                    method: 'POST', headers: postHdrs(),
                    body: JSON.stringify({ deduplication_key: this.activeThreadReminder.deduplication_key }),
                });
            } catch(e) {}
            this.activeThreadReminder = null;
        },

        async sendReply() {
            if (!this.replyBody.trim() || this.sending) return;
            this.sending   = true;
            this.sendError = null;
            try {
                const r = await fetch(`${BASE}/threads/${this.activeThread.id}`, {
                    method: 'POST', headers: postHdrs(),
                    body: JSON.stringify({ body: this.replyBody }),
                });
                if (!r.ok) {
                    const d = await r.json().catch(() => ({}));
                    throw new Error(d.message || `Failed to send (HTTP ${r.status})`);
                }
                const msg = await r.json();
                this.activeMessages.push(msg);
                this.updateThreadPreview(this.activeThread.id, msg);
                this.replyBody            = '';
                this.activeThreadReminder = null;
                this.needsReplyCount      = Math.max(0, this.needsReplyCount - 1);
                this.$nextTick(() => {
                    this.scrollToBottom();
                    this.$el.querySelector('textarea')?.dispatchEvent(new Event('input'));
                });
            } catch(e) {
                this.sendError = e.message || 'Failed to send message. Please try again.';
            } finally {
                this.sending = false;
            }
        },

        async startThread() {
            if (this.sending || !this.compose.reseller_id || !this.compose.body.trim()) return;
            this.sending      = true;
            this.composeError = null;
            try {
                const r = await fetch(`${BASE}/threads`, {
                    method: 'POST', headers: postHdrs(),
                    body: JSON.stringify(this.compose),
                });
                if (!r.ok) {
                    const d = await r.json().catch(() => ({}));
                    throw new Error(d.message || `Failed to send (HTTP ${r.status})`);
                }
                const d = await r.json();
                this.openCompose = false;
                this.compose     = { reseller_id: '', body: '' };
                await this.loadThreads(false);
                const thread = this.threads.find(t => t.id === d.thread_id);
                if (thread) this.selectThread(thread);
            } catch(e) {
                this.composeError = e.message || 'Failed to send message. Please try again.';
            } finally {
                this.sending = false;
            }
        },

        updateThreadPreview(threadId, msg) {
            const t = this.threads.find(x => x.id === threadId);
            if (t) {
                t.last_message_preview = (msg.body ?? '').substring(0, 80);
                t.last_message_at      = msg.created_at;
            }
        },

        scrollToBottom() {
            const el = this.$refs.messageArea;
            if (el) el.scrollTop = el.scrollHeight;
        },

        // ── Compose-to-anyone (broadcast) ─────────────────────────────────────
        // State lives HERE in messaging() — no nested x-data on the modal element
        // so openCompose reads/writes always go to this scope.

        roleFilter:       'all',
        recipientSearch:  '',
        selectedTokens:   [],
        broadcastBody:    '',
        broadcastSending: false,
        broadcastResult:  null,
        broadcastOk:      true,

        get visibleRecipients() {
            const q = this.recipientSearch.toLowerCase().trim();
            return this._allRecipients.filter(r => {
                const roleMatch = this.roleFilter === 'all'
                    || r.role === this.roleFilter
                    || (this.roleFilter === 'admin' && ['admin','manager','owner'].includes(r.role));
                const searchMatch = !q
                    || r.name.toLowerCase().includes(q)
                    || r.email.toLowerCase().includes(q);
                return roleMatch && searchMatch;
            });
        },

        selectAll() {
            const tokens = this.visibleRecipients.map(r => r.token);
            const allSelected = tokens.every(t => this.selectedTokens.includes(t));
            if (allSelected) {
                this.selectedTokens = this.selectedTokens.filter(t => !tokens.includes(t));
            } else {
                this.selectedTokens = [...new Set([...this.selectedTokens, ...tokens])];
            }
        },

        async sendBroadcast() {
            if (this.broadcastSending || this.selectedTokens.length === 0 || !this.broadcastBody.trim()) return;
            this.broadcastSending = true;
            this.broadcastResult  = null;
            try {
                const r = await fetch('{{ url("tenant/" . $tenant->id . "/messages/broadcast") }}', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                    body:    JSON.stringify({ recipients: this.selectedTokens, body: this.broadcastBody }),
                });
                const d = await r.json();
                if (r.ok) {
                    this.broadcastOk     = true;
                    this.broadcastResult = d.message || 'Message sent!';
                    this.selectedTokens  = [];
                    this.broadcastBody   = '';
                    setTimeout(() => this.loadThreads(), 1500);
                } else {
                    this.broadcastOk     = false;
                    this.broadcastResult = d.error || 'Failed to send. Please try again.';
                }
            } catch (e) {
                this.broadcastOk     = false;
                this.broadcastResult = 'Network error. Please try again.';
            } finally {
                this.broadcastSending = false;
            }
        },

        resetCompose() {
            this.roleFilter      = 'all';
            this.recipientSearch = '';
            this.selectedTokens  = [];
            this.broadcastBody   = '';
            this.broadcastResult = null;
        },
    };
}
</script>
{{-- Recipient data injected separately so it doesn't break the messaging() function parse --}}
<script>
// Build recipient list from server data — defined AFTER messaging() so errors here
// don't prevent the main Alpine component from initializing.
document.addEventListener('alpine:init', () => {
    window.__allRecipients = [
        @foreach($resellers as $r)
        { token: 'reseller:{{ $r->id }}', name: @json($r->name ?: $r->email), email: @json($r->email), role: 'reseller', roleLabel: 'Referrer',
          initials: @json(strtoupper(substr($r->name ?: $r->email ?: 'R', 0, 2))), color: '#7B61FF' },
        @endforeach
        @foreach($partners as $p)
        { token: 'partner:{{ $p->id }}', name: @json($p->name ?: $p->email), email: @json($p->email), role: 'partner', roleLabel: 'Partner',
          initials: @json(strtoupper(substr($p->name ?: $p->email ?: 'P', 0, 2))), color: '#0891b2' },
        @endforeach
        @foreach($tenantUsers as $u)
        { token: '{{ $u->role }}:{{ $u->id }}', name: @json($u->name ?: $u->email), email: @json($u->email), role: '{{ $u->role }}', roleLabel: '{{ ucfirst($u->role) }}',
          initials: @json(strtoupper(substr($u->name ?: $u->email ?: 'A', 0, 2))), color: '#059669' },
        @endforeach
        @foreach($contacts as $c)
        { token: 'contact:{{ $c->id }}', name: @json($c->name ?: $c->email), email: @json($c->email), role: 'contact', roleLabel: 'Contact',
          initials: @json(strtoupper(substr($c->name ?: $c->email ?: 'C', 0, 2))), color: '#d97706' },
        @endforeach
    ];
});
</script>
@endpush
@endsection
