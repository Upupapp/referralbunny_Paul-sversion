@extends('layouts.app')
@section('title', 'Contacts')
@section('nav') @include('tenant._nav') @endsection

@section('topbar-actions')
    <a href="{{ route('tenant.imports.contacts', $tenant->id) }}"
       class="btn-secondary"
       title="Upload contacts and associate them with organizations, deals, Referrers, or Partner candidates.">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
        </svg>
        <span class="hidden sm:inline">Import Contacts</span>
    </a>
    <button type="button" x-data @click="$dispatch('open-add-contact')" class="btn-primary">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        <span class="hidden sm:inline">Add Contact</span>
    </button>
@endsection

@section('content')
<div class="space-y-5"
     x-data="contactsModule('{{ $tenant->id }}')"
     x-init="init()"
     @open-add-contact.window="openAdd()">

    {{-- KPIs --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
        <div class="kpi-card">
            <div class="flex-1 min-w-0">
                <span class="text-gray-400 text-xs font-medium uppercase tracking-wide">Total Contacts</span>
                <p class="text-2xl font-bold text-[#1E1B4B] mt-1.5" x-text="contacts.length"></p>
            </div>
            <div class="kpi-icon bg-purple-100 ml-3 shrink-0">
                <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
            </div>
        </div>
        <div class="kpi-card">
            <div class="flex-1 min-w-0">
                <span class="text-gray-400 text-xs font-medium uppercase tracking-wide">Active</span>
                <p class="text-2xl font-bold text-[#1E1B4B] mt-1.5" x-text="contacts.filter(c => c.status === 'active').length"></p>
            </div>
            <div class="kpi-icon bg-emerald-100 ml-3 shrink-0">
                <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
        </div>
        <div class="kpi-card">
            <div class="flex-1 min-w-0">
                <span class="text-gray-400 text-xs font-medium uppercase tracking-wide">Linked to Deals</span>
                <p class="text-2xl font-bold text-[#1E1B4B] mt-1.5" x-text="contacts.filter(c => c.deal_count > 0).length"></p>
            </div>
            <div class="kpi-icon bg-blue-100 ml-3 shrink-0">
                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
            </div>
        </div>
        <div class="kpi-card">
            <div class="flex-1 min-w-0">
                <span class="text-gray-400 text-xs font-medium uppercase tracking-wide">Organizations</span>
                <p class="text-2xl font-bold text-[#1E1B4B] mt-1.5" x-text="orgs.length"></p>
            </div>
            <div class="kpi-icon bg-orange-100 ml-3 shrink-0">
                <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
            </div>
        </div>
    </div>

    {{-- Filter bar --}}
    <div class="card space-y-3">
        <div class="search-group">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input type="text" x-model="search" @input.debounce.250ms="applyFilters()" placeholder="Search by name, email, or organization…">
            <button type="button" x-show="search.length > 0" @click="search = ''; applyFilters()"
                    class="text-gray-400 hover:text-gray-600 transition-colors shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="filter-bar">
            <label class="filter-pill" :class="filterStatus !== '' ? 'active' : ''">
                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                <select x-model="filterStatus" @change="applyFilters()">
                    <option value="">All Status</option>
                    <option value="active">Active</option>
                    <option value="prospect">Prospect</option>
                    <option value="inactive">Inactive</option>
                </select>
                <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </label>
            <label class="filter-pill" :class="filterOrg !== '' ? 'active' : ''">
                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5"/></svg>
                <select x-model="filterOrg" @change="applyFilters()">
                    <option value="">All Organizations</option>
                    <template x-for="o in orgs" :key="o.id">
                        <option :value="o.id" x-text="o.name"></option>
                    </template>
                </select>
                <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </label>
            <button type="button" x-show="filterStatus || filterOrg || search"
                    @click="filterStatus=''; filterOrg=''; search=''; applyFilters()"
                    class="filter-pill !border-red-200 !text-red-500 hover:!bg-red-50">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                Clear
            </button>
        </div>
    </div>

    {{-- Table --}}
    <div class="card p-0 overflow-hidden">
        <div class="flex items-center justify-between px-5 py-3.5 border-b border-gray-100">
            <p class="text-sm font-semibold text-[#1E1B4B]">
                <span x-text="filtered.length"></span> contacts
                <span x-show="filterStatus || filterOrg || search" class="text-gray-400 font-normal text-xs ml-1">— filtered</span>
            </p>
        </div>

        <div x-show="loading" class="flex items-center justify-center py-10 gap-3 text-gray-400">
            <svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
            <span class="text-sm">Loading contacts…</span>
        </div>

        <div x-show="!loading" class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="table-head">
                        <th>Contact</th>
                        <th class="hidden md:table-cell">Job Title</th>
                        <th class="hidden sm:table-cell">Organization</th>
                        <th>Deals</th>
                        <th class="hidden sm:table-cell">Status</th>
                        <th class="hidden lg:table-cell">Role</th>
                        <th class="hidden lg:table-cell">Added</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-if="filtered.length === 0 && !loading">
                        <tr>
                            <td colspan="7" class="py-16 text-center">
                                <div class="flex justify-center text-gray-300 mb-3">
                                    <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                </div>
                                <p class="text-gray-400 text-sm" x-text="contacts.length === 0 ? 'No contacts yet. Add your first contact.' : 'No contacts match the filters.'"></p>
                                <button type="button" x-show="contacts.length === 0" @click="openAdd()" class="btn-primary mt-3 text-sm">Add First Contact</button>
                            </td>
                        </tr>
                    </template>
                    <template x-for="c in filtered" :key="c.id">
                        <tr class="table-row">
                            <td>
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-purple-100 flex items-center justify-center text-purple-700 text-xs font-bold shrink-0"
                                         x-text="initials(c)"></div>
                                    <div class="min-w-0">
                                        <p class="font-medium text-[#1E1B4B] truncate" x-text="fullName(c)"></p>
                                        <p class="text-xs text-gray-400 truncate" x-text="c.email || '—'"></p>
                                    </div>
                                </div>
                            </td>
                            <td class="hidden md:table-cell text-gray-500 text-sm" x-text="c.job_title || '—'"></td>
                            <td class="hidden sm:table-cell">
                                <span x-show="c.org_name" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-gray-50 text-gray-700 text-xs font-medium border border-gray-100">
                                    <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                    <span x-text="c.org_name"></span>
                                </span>
                                <span x-show="!c.org_name" class="text-gray-300 text-sm">—</span>
                            </td>
                            <td>
                                <span x-show="c.deal_count > 0"
                                      class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-blue-50 text-blue-700 text-xs font-semibold">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                                    <span x-text="c.deal_count"></span>
                                </span>
                                <span x-show="!c.deal_count" class="text-gray-300 text-sm">—</span>
                            </td>
                            <td class="hidden sm:table-cell">
                                <span :class="{
                                    'badge badge-green':  c.status === 'active',
                                    'badge badge-gray':   c.status === 'inactive',
                                    'badge badge-blue':   c.status === 'prospect',
                                }" x-text="c.status ? c.status.charAt(0).toUpperCase() + c.status.slice(1) : '—'"></span>
                            </td>
                            {{-- Role assignment status --}}
                            <td class="hidden lg:table-cell">
                                <template x-if="c.role_invite_status === 'accepted' && c.role_invite_role">
                                    <span class="badge badge-green text-xs" x-text="roleLabel(c.role_invite_role)"></span>
                                </template>
                                <template x-if="c.role_invite_status === 'pending'">
                                    <span class="inline-flex items-center gap-1 badge badge-orange text-xs">
                                        <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        <span x-text="'Pending · ' + roleLabel(c.role_invite_role)"></span>
                                    </span>
                                </template>
                                <template x-if="!c.role_invite_status">
                                    <span class="text-gray-300 text-sm">—</span>
                                </template>
                            </td>
                            <td class="hidden lg:table-cell text-gray-400 text-sm tabular-nums"
                                x-text="c.created_at ? new Date(c.created_at).toLocaleDateString('en',{month:'short',day:'numeric',year:'numeric'}) : '—'"></td>
                            <td>
                                <div class="flex items-center gap-1.5 justify-end">
                                    {{-- Assign Role button --}}
                                    <button type="button" @click="openAssignRole(c)"
                                            class="px-2.5 py-1.5 rounded-lg text-xs font-medium bg-purple-50 text-purple-700 hover:bg-purple-100 transition-colors whitespace-nowrap"
                                            title="Assign a role to this contact">
                                        Assign Role
                                    </button>
                                    <button type="button" @click="openEdit(c)"
                                            class="p-1.5 rounded-lg hover:bg-gray-100 text-gray-400 hover:text-gray-700 transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </button>
                                    <button type="button" @click="deleteContact(c.id)"
                                            class="p-1.5 rounded-lg hover:bg-red-50 text-gray-400 hover:text-red-500 transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    {{-- Assign Role Modal --}}
    <div x-show="showRoleModal" x-cloak
         class="fixed inset-0 bg-black/50 z-50 flex items-end sm:items-center justify-center p-4"
         @keydown.escape.window="showRoleModal = false">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto" @click.stop>
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <div>
                    <h3 class="font-semibold text-[#1E1B4B]">Assign Role to Contact</h3>
                    <p class="text-xs text-gray-400 mt-0.5" x-text="roleContact ? (roleContact.first_name + ' ' + (roleContact.last_name || '')).trim() : ''"></p>
                </div>
                <button type="button" @click="showRoleModal = false" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <div class="p-6 space-y-5">
                {{-- Contact summary --}}
                <div class="bg-gray-50 rounded-xl p-4 space-y-1.5">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-full bg-purple-100 flex items-center justify-center text-purple-700 text-xs font-bold shrink-0"
                             x-text="roleContact ? initials(roleContact) : '?'"></div>
                        <div class="min-w-0">
                            <p class="font-medium text-[#1E1B4B] text-sm" x-text="roleContact ? fullName(roleContact) : ''"></p>
                            <p class="text-xs text-gray-400" x-text="roleContact?.email || 'No email'"></p>
                        </div>
                    </div>
                    {{-- Existing role badge --}}
                    <div x-show="roleContact?.role_invite_status" class="pt-1">
                        <span class="badge badge-orange text-xs"
                              x-text="roleContact?.role_invite_status === 'accepted' ? 'Active: ' + roleLabel(roleContact.role_invite_role) : 'Pending: ' + roleLabel(roleContact?.role_invite_role)"></span>
                    </div>
                    {{-- No email warning --}}
                    <div x-show="roleContact && !roleContact.email"
                         class="mt-2 flex items-start gap-2 bg-orange-50 rounded-lg px-3 py-2">
                        <svg class="w-4 h-4 text-orange-500 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                        <p class="text-xs text-orange-700">This contact needs an email before I can send an invitation. Edit the contact to add one first.</p>
                    </div>
                </div>

                {{-- Role selector --}}
                <div>
                    <label class="form-label mb-2">Select Role</label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <template x-for="opt in roleOptions" :key="opt.value">
                            <label class="flex items-start gap-3 p-3 rounded-xl border-2 cursor-pointer transition-colors"
                                   :class="roleForm.role === opt.value ? 'border-[#7B61FF] bg-purple-50' : 'border-gray-100 hover:border-purple-200'">
                                <input type="radio" :value="opt.value" x-model="roleForm.role" class="mt-0.5 accent-[#7B61FF]">
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-[#1E1B4B]" x-text="opt.label"></p>
                                    <p class="text-xs text-gray-400 mt-0.5 leading-tight" x-text="opt.description"></p>
                                </div>
                            </label>
                        </template>
                    </div>
                </div>

                {{-- Deal selector (required for Partner) --}}
                <div x-show="roleForm.role === 'partner'">
                    <label class="form-label">Select Deal <span class="text-red-500">*</span></label>
                    <select x-model="roleForm.associated_deal_id" class="form-input">
                        <option value="">Choose a deal…</option>
                        <template x-for="d in deals" :key="d.id">
                            <option :value="d.id" x-text="d.name || d.id"></option>
                        </template>
                    </select>
                    <p class="text-xs text-gray-400 mt-1">Partner access is limited to the selected deal only.</p>
                </div>

                {{-- Permission preview --}}
                <div x-show="roleForm.role" class="bg-blue-50 rounded-xl px-4 py-3">
                    <p class="text-xs font-semibold text-blue-700 mb-1.5">What they'll be able to access:</p>
                    <template x-if="roleForm.role === 'referrer'">
                        <ul class="text-xs text-blue-700 space-y-1 list-disc list-inside">
                            <li>Submit and manage their own referral deals</li>
                            <li>View commission and performance</li>
                            <li>Message the tenant team on active deals</li>
                        </ul>
                    </template>
                    <template x-if="roleForm.role === 'tenant_manager'">
                        <ul class="text-xs text-blue-700 space-y-1 list-disc list-inside">
                            <li>Manage deals, contacts, and referrers</li>
                            <li>View reports and analytics</li>
                            <li>Import and export data</li>
                        </ul>
                    </template>
                    <template x-if="roleForm.role === 'tenant_staff'">
                        <ul class="text-xs text-blue-700 space-y-1 list-disc list-inside">
                            <li>Operational access based on assigned permissions</li>
                            <li>View deals and contacts</li>
                        </ul>
                    </template>
                    <template x-if="roleForm.role === 'partner'">
                        <ul class="text-xs text-blue-700 space-y-1 list-disc list-inside">
                            <li>View the selected deal details only</li>
                            <li>Message Referrers connected to that deal</li>
                            <li>No access to other deals or tenant data</li>
                        </ul>
                    </template>
                </div>

                {{-- Tenant Manager confirmation --}}
                <div x-show="roleForm.role === 'tenant_manager'"
                     class="bg-orange-50 border border-orange-200 rounded-xl px-4 py-3">
                    <label class="flex items-start gap-2.5 cursor-pointer">
                        <input type="checkbox" x-model="roleForm.managerConfirmed" class="mt-0.5 accent-orange-500">
                        <span class="text-xs text-orange-700 leading-relaxed">I understand I am inviting this contact to become a Tenant Manager. They may access management tools based on the permissions assigned to their account.</span>
                    </label>
                </div>

                {{-- Optional message --}}
                <div>
                    <label class="form-label">Personal Message <span class="text-gray-400 font-normal">(optional)</span></label>
                    <textarea x-model="roleForm.message" class="form-input" rows="2"
                              placeholder="Add a personal note to the invitation email…"></textarea>
                </div>

                {{-- Error --}}
                <div x-show="roleError" class="flex items-start gap-2 bg-red-50 rounded-xl px-4 py-3">
                    <svg class="w-4 h-4 text-red-500 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                    <p class="text-sm text-red-600" x-text="roleError"></p>
                </div>

                {{-- Actions --}}
                <div class="flex justify-end gap-3 pt-1">
                    <button type="button" @click="showRoleModal = false" class="btn-secondary">Cancel</button>
                    <button type="button" @click="sendRoleInvitation()"
                            :disabled="roleSaving || !roleForm.role || !roleContact?.email || (roleForm.role === 'partner' && !roleForm.associated_deal_id) || (roleForm.role === 'tenant_manager' && !roleForm.managerConfirmed)"
                            class="btn-primary">
                        <svg x-show="roleSaving" class="w-4 h-4 animate-spin mr-1" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        <span x-text="roleSaving ? 'Sending…' : 'Send Invitation'"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Add / Edit Modal --}}
    <div x-show="showModal" x-cloak
         class="fixed inset-0 bg-black/50 z-50 flex items-end sm:items-center justify-center p-4"
         @keydown.escape.window="showModal = false">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg" @click.stop>
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <h3 class="font-semibold text-[#1E1B4B]" x-text="editId ? 'Edit Contact' : 'Add Contact'"></h3>
                <button type="button" @click="showModal = false" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="p-6 space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="form-label">First Name *</label>
                        <input type="text" x-model="form.first_name" class="form-input" placeholder="Juan">
                    </div>
                    <div>
                        <label class="form-label">Last Name</label>
                        <input type="text" x-model="form.last_name" class="form-input" placeholder="dela Cruz">
                    </div>
                    <div>
                        <label class="form-label">Email *</label>
                        <input type="email" x-model="form.email" class="form-input" placeholder="juan@email.com" required>
                        <p class="text-[11px] text-gray-400 mt-0.5">Required to send invitations.</p>
                    </div>
                    <div>
                        <label class="form-label">Phone</label>
                        <input type="text" x-model="form.phone" class="form-input" placeholder="+63 9XX XXX XXXX">
                    </div>
                    <div>
                        <label class="form-label">Function / Intended Role *</label>
                        <select x-model="form.intended_role" class="form-input">
                            <option value="general_contact">General Contact</option>
                            <option value="referrer">Referrer</option>
                            <option value="partner">Partner</option>
                            <option value="tenant_manager">Tenant Manager</option>
                            <option value="deal_contact">Deal Contact</option>
                            <option value="organization_contact">Organization Contact</option>
                        </select>
                        <p class="text-[11px] text-gray-400 mt-0.5">
                            Choose a function to bridge this contact into the right flow.
                        </p>
                    </div>
                    <div>
                        <label class="form-label">Job Title</label>
                        <input type="text" x-model="form.job_title" class="form-input" placeholder="e.g. IT Director, Mayor">
                    </div>
                </div>
                <div>
                    <label class="form-label">Organization</label>
                    <select x-model="form.organization_id" class="form-input">
                        <option value="">No organization</option>
                        <template x-for="o in orgs" :key="o.id">
                            <option :value="o.id" x-text="o.name"></option>
                        </template>
                    </select>
                </div>
                <div>
                    <label class="form-label">Notes</label>
                    <textarea x-model="form.notes" class="form-input" rows="2" placeholder="Any relevant notes about this contact…"></textarea>
                </div>
                <p x-show="formError" class="text-xs text-red-600 font-medium" x-text="formError"></p>
                <div class="flex justify-end gap-3 pt-1">
                    <button type="button" @click="showModal = false" class="btn-secondary">Cancel</button>
                    <button type="button" @click="saveContact()" :disabled="saving" class="btn-primary"
                            x-text="saving ? 'Saving…' : (editId ? 'Save Changes' : 'Add Contact')"></button>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
function contactsModule(tenantId) {
    return {
        contacts: [], orgs: [], deals: [], filtered: [],
        loading: true,
        search: '', filterStatus: '', filterOrg: '',
        showModal: false, saving: false, formError: '',
        editId: null,
        form: { first_name: '', last_name: '', email: '', phone: '', job_title: '', intended_role: 'general_contact', organization_id: '', status: 'active', notes: '' },

        // Role assignment
        showRoleModal: false,
        roleContact: null,
        roleSaving: false,
        roleError: '',
        roleForm: { role: '', associated_deal_id: '', message: '', managerConfirmed: false },
        roleOptions: [
            { value: 'referrer',       label: 'Referrer',        description: 'Can submit and manage referral deals.' },
            { value: 'tenant_manager', label: 'Tenant Manager',   description: 'Can help manage this workspace based on assigned permissions.' },
            { value: 'tenant_staff',   label: 'Tenant Staff',     description: 'Operational access based on assigned permissions.' },
            { value: 'partner',        label: 'Partner',          description: 'View-only access to one associated deal and its messages.' },
        ],

        async init() {
            try {
                const [cr, or, dr] = await Promise.all([
                    fetch(`/api/contacts?tenant_id=${tenantId}`, { credentials: 'same-origin', headers: { Accept: 'application/json' } }).then(r => r.json()),
                    fetch(`/api/organizations?tenant_id=${tenantId}`, { credentials: 'same-origin', headers: { Accept: 'application/json' } }).then(r => r.json()),
                    fetch(`/api/leads?tenant_id=${tenantId}&per_page=500`, { credentials: 'same-origin', headers: { Accept: 'application/json' } }).then(r => r.json()),
                ]);
                this.contacts = Array.isArray(cr) ? cr : (cr?.data || []);
                this.orgs     = Array.isArray(or) ? or : [];
                this.deals    = Array.isArray(dr) ? dr : (dr?.data || []);
            } catch(e) { this.contacts = []; this.orgs = []; this.deals = []; }
            this.applyFilters();
            this.loading = false;
        },

        applyFilters() {
            const q = this.search.toLowerCase();
            this.filtered = this.contacts.filter(c => {
                const name  = (this.fullName(c)).toLowerCase();
                const matchQ = !q || name.includes(q) || (c.email||'').toLowerCase().includes(q) || (c.org_name||'').toLowerCase().includes(q) || (c.job_title||'').toLowerCase().includes(q);
                const matchS = !this.filterStatus || c.status === this.filterStatus;
                const matchO = !this.filterOrg || c.organization_id === this.filterOrg;
                return matchQ && matchS && matchO;
            });
        },

        fullName(c) { return [c.first_name, c.last_name].filter(Boolean).join(' ') || '—'; },
        initials(c) {
            const parts = [c.first_name, c.last_name].filter(Boolean);
            return parts.length > 0 ? parts.map(n => n[0]).join('').toUpperCase() : '?';
        },

        openAdd() {
            this.editId = null;
            this.form = { first_name: '', last_name: '', email: '', phone: '', job_title: '', intended_role: 'general_contact', organization_id: '', status: 'active', notes: '' };
            this.formError = '';
            this.showModal = true;
        },

        openEdit(c) {
            this.editId = c.id;
            this.form = {
                first_name:      c.first_name || '',
                last_name:       c.last_name || '',
                email:           c.email || '',
                phone:           c.phone || '',
                job_title:       c.job_title || '',
                organization_id: c.organization_id || '',
                status:          c.status || 'active',
                notes:           c.notes || '',
            };
            this.formError = '';
            this.showModal = true;
        },

        async saveContact() {
            if (!this.form.first_name.trim()) { this.formError = 'First name is required.'; return; }
            if (!this.editId && !this.form.email.trim()) { this.formError = 'Email is required to add a contact.'; return; }
            this.saving = true; this.formError = '';
            try {
                const url    = this.editId ? `/api/contacts/${this.editId}` : '/api/contacts';
                const method = this.editId ? 'PUT' : 'POST';
                const body   = this.editId ? { ...this.form } : { ...this.form, tenant_id: tenantId };
                const res    = await fetch(url, {
                    method,
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify(body),
                });
                const data = await res.json();
                if (data.id) {
                    if (this.editId) {
                        const i = this.contacts.findIndex(c => c.id === this.editId);
                        if (i !== -1) this.contacts.splice(i, 1, data);
                    } else {
                        this.contacts.unshift(data);
                    }
                    this.applyFilters();
                    this.showModal = false;
                    const successMsg = this.editId ? 'Contact updated.' : 'Contact added successfully.';
                    this.$dispatch('show-toast', { type: 'success', message: successMsg });
                } else if (res.status === 409 && data.error_code === 'duplicate_contact') {
                    this.formError = data.message + (data.existing_contact?.name ? ' Existing: ' + data.existing_contact.name : '');
                } else {
                    this.formError = data.message || data.errors?.email?.[0] || 'Failed to save contact.';
                }
            } catch(e) { this.formError = 'Network error. Please try again.'; }
            finally { this.saving = false; }
        },

        async deleteContact(id) {
            if (!confirm('Delete this contact? They will also be unlinked from all deals.')) return;
            try {
                await fetch(`/api/contacts/${id}`, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                });
                this.contacts = this.contacts.filter(c => c.id !== id);
                this.applyFilters();
                this.$dispatch('show-toast', { type: 'success', message: 'Contact deleted.' });
            } catch(e) {
                this.$dispatch('show-toast', { type: 'error', message: 'Failed to delete contact.' });
            }
        },

        // ── Role assignment ───────────────────────────────────────────────

        roleLabel(role) {
            const map = { referrer: 'Referrer', tenant_manager: 'Manager', tenant_staff: 'Staff', partner: 'Partner' };
            return map[role] || (role ? role.replace('_', ' ') : '');
        },

        openAssignRole(contact) {
            this.roleContact = contact;
            this.roleForm = { role: '', associated_deal_id: '', message: '', managerConfirmed: false };
            this.roleError = '';
            this.showRoleModal = true;
        },

        async sendRoleInvitation() {
            this.roleError = '';
            if (!this.roleForm.role) { this.roleError = 'Please select a role.'; return; }
            if (!this.roleContact?.email) { this.roleError = 'This contact needs an email address first.'; return; }
            if (this.roleForm.role === 'partner' && !this.roleForm.associated_deal_id) { this.roleError = 'Please select a deal for the Partner role.'; return; }
            if (this.roleForm.role === 'tenant_manager' && !this.roleForm.managerConfirmed) { this.roleError = 'Please confirm the Tenant Manager assignment.'; return; }

            this.roleSaving = true;
            try {
                const res  = await fetch('/api/contact-role-assignments', {
                    method:  'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    body: JSON.stringify({
                        contact_id:          this.roleContact.id,
                        role:                this.roleForm.role,
                        associated_deal_id:  this.roleForm.associated_deal_id || null,
                        message:             this.roleForm.message || null,
                    }),
                });
                const data = await res.json();
                if (data.success) {
                    // Update local contact row with pending status
                    const idx = this.contacts.findIndex(c => c.id === this.roleContact.id);
                    if (idx !== -1) {
                        this.contacts[idx].role_invite_status = 'pending';
                        this.contacts[idx].role_invite_role   = this.roleForm.role;
                        this.contacts[idx].role_invite_id     = data.invitation?.id;
                    }
                    this.applyFilters();
                    this.showRoleModal = false;
                    this.$dispatch('show-toast', { type: 'success', message: data.message || 'Invitation sent.' });
                } else {
                    this.roleError = data.error || 'Failed to send invitation.';
                }
            } catch(e) {
                this.roleError = 'Network error. Please try again.';
            } finally {
                this.roleSaving = false;
            }
        },
    };
}
</script>
@endsection
