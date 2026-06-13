<div class="space-y-4"
     x-data="{
        addDocument() {
            if (data.required_documents.length >= 10) return;
            data.required_documents.push({
                doc_key: 'doc_' + Math.random().toString(36).slice(2, 8),
                label: '',
                document_type: 'other',
                is_required: true,
            });
        },
        removeDocument(i) { data.required_documents.splice(i, 1); },
     }">

    <p class="text-sm text-gray-500 mb-2">
        Decide whether Referrers or Partners need to accept an agreement, and list any documents they should upload
        before they can start referring (e.g. a government ID or business permit).
    </p>

    <label class="flex items-start gap-3 p-3 rounded-xl border cursor-pointer transition-colors"
           :class="data.require_referrer_agreement ? 'border-[#7B61FF] bg-purple-50' : 'border-gray-200 hover:border-purple-200'">
        <input type="checkbox" x-model="data.require_referrer_agreement" class="mt-0.5 accent-[#7B61FF]">
        <div class="flex-1 min-w-0">
            <p class="text-sm font-medium text-[#1E1B4B]">Require Referrers to accept a Referrer Agreement</p>
            <p class="text-xs text-gray-400 mt-0.5">Shown the first time someone joins this program as a Referrer.</p>
        </div>
    </label>
    <div class="pl-1" x-show="data.require_referrer_agreement" x-cloak>
        <label class="form-label">Referrer Agreement text</label>
        <textarea x-model="data.referrer_agreement_text" rows="4" maxlength="5000"
                  placeholder="By joining as a Referrer, you agree to..." class="form-input"></textarea>
        <p x-show="errors.referrer_agreement_text" x-cloak class="text-xs text-red-600 mt-1" x-text="errors.referrer_agreement_text?.[0]"></p>
    </div>

    <label class="flex items-start gap-3 p-3 rounded-xl border cursor-pointer transition-colors"
           :class="data.require_partner_agreement ? 'border-[#7B61FF] bg-purple-50' : 'border-gray-200 hover:border-purple-200'">
        <input type="checkbox" x-model="data.require_partner_agreement" class="mt-0.5 accent-[#7B61FF]">
        <div class="flex-1 min-w-0">
            <p class="text-sm font-medium text-[#1E1B4B]">Require Partners to accept a Partner Agreement</p>
            <p class="text-xs text-gray-400 mt-0.5">Only relevant if Partners are part of this program (see Step 8).</p>
        </div>
    </label>
    <div class="pl-1" x-show="data.require_partner_agreement" x-cloak>
        <label class="form-label">Partner Agreement text</label>
        <textarea x-model="data.partner_agreement_text" rows="4" maxlength="5000"
                  placeholder="By joining as a Partner, you agree to..." class="form-input"></textarea>
        <p x-show="errors.partner_agreement_text" x-cloak class="text-xs text-red-600 mt-1" x-text="errors.partner_agreement_text?.[0]"></p>
    </div>

    <div class="h-px bg-gray-100 my-2"></div>

    <div>
        <label class="form-label">Required Documents</label>
        <p class="text-xs text-gray-400 mb-2">Documents Referrers or Partners must upload before they can start. You'll be able to review submissions later.</p>

        <div class="space-y-2">
            <template x-for="(doc, i) in data.required_documents" :key="doc.doc_key">
                <div class="flex flex-wrap items-center gap-2 p-3 rounded-xl border border-gray-200">
                    <input type="text" x-model="doc.label" maxlength="100" placeholder="e.g. Valid Government ID"
                           class="form-input flex-1 min-w-[160px]">

                    <select x-model="doc.document_type" class="form-input w-44">
                        @foreach($options['documentTypes'] as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>

                    <label class="flex items-center gap-1 text-xs text-gray-500 shrink-0">
                        <input type="checkbox" x-model="doc.is_required" class="accent-[#7B61FF]">
                        Required
                    </label>

                    <button type="button" @click="removeDocument(i)" aria-label="Remove document"
                            class="text-gray-400 hover:text-red-600 ml-auto shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
            </template>

            <p x-show="data.required_documents.length === 0" class="text-sm text-gray-400 italic py-4 text-center border border-dashed border-gray-200 rounded-xl">
                No required documents yet.
            </p>
        </div>

        <button type="button" @click="addDocument()" :disabled="data.required_documents.length >= 10" class="btn-secondary text-sm disabled:opacity-50 mt-2">
            + Add Document
        </button>
        <p class="text-xs text-gray-400 mt-1">Up to 10 document requirements.</p>
        <p x-show="errors.required_documents" x-cloak class="text-xs text-red-600 mt-1" x-text="errors.required_documents?.[0]"></p>
    </div>
</div>
