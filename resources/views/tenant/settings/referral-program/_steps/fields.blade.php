<div class="space-y-3"
     x-data="{
        placeholders: { text: 'Text input', number: '0', email: 'name@example.com', date: 'YYYY-MM-DD', boolean: 'Yes / No', url: 'https://example.com', phone: '+63 9XX XXX XXXX' },
        addField() {
            if (data.fields.length >= 20) return;
            data.fields.push({
                field_key: 'field_' + Math.random().toString(36).slice(2, 8),
                field_label: '',
                data_type: 'text',
                is_required: false,
            });
        },
        removeField(i) { data.fields.splice(i, 1); },
     }">

    <p class="text-sm text-gray-500 mb-2">
        Add any extra fields you want to capture on each deal, alongside the standard Deal Value and Notes fields.
        You can add more anytime — fields are never deleted once deals start using them, only hidden.
    </p>

    <div class="space-y-2">
        <template x-for="(field, i) in data.fields" :key="field.field_key">
            <div class="flex flex-wrap items-center gap-2 p-3 rounded-xl border border-gray-200">
                <input type="text" x-model="field.field_label" maxlength="100" placeholder="Field label, e.g. Lead Source"
                       class="form-input flex-1 min-w-[160px]">

                <select x-model="field.data_type" class="form-input w-36">
                    @foreach($options['fieldDataTypes'] as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>

                <label class="flex items-center gap-1 text-xs text-gray-500 shrink-0">
                    <input type="checkbox" x-model="field.is_required" class="accent-[#7B61FF]">
                    Required
                </label>

                <button type="button" @click="removeField(i)"
                        class="text-gray-400 hover:text-red-600 ml-auto shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>
        </template>

        <p x-show="data.fields.length === 0" class="text-sm text-gray-400 italic py-4 text-center border border-dashed border-gray-200 rounded-xl">
            No custom fields yet — your Deal form will use the standard fields only.
        </p>
    </div>

    <button type="button" @click="addField()" :disabled="data.fields.length >= 20" class="btn-secondary text-sm disabled:opacity-50">
        + Add Field
    </button>
    <p class="text-xs text-gray-400">Up to 20 custom fields.</p>

    <p x-show="errors.fields" x-cloak class="text-xs text-red-600 mt-1" x-text="errors.fields?.[0]"></p>

    <div class="card mt-2" x-show="data.fields.length > 0" x-cloak>
        <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Preview — Deal Form</h4>
        <div class="space-y-3">
            <template x-for="field in data.fields" :key="field.field_key">
                <div>
                    <label class="form-label text-xs" x-text="(field.field_label || 'Untitled field') + (field.is_required ? ' *' : '')"></label>
                    <div class="form-input bg-gray-50 text-gray-400 text-xs py-1.5" x-text="placeholders[field.data_type] || 'Text input'"></div>
                </div>
            </template>
        </div>
    </div>
</div>
