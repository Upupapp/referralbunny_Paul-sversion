<div class="space-y-4">

    @if($protected)
    <div class="flex gap-3 p-4 rounded-xl bg-orange-50 border border-orange-100">
        <x-r-bunny variant="warning" size="sm" :decorative="true" class="shrink-0 mt-0.5" />
        <div class="flex-1 min-w-0">
            <p class="text-sm font-semibold text-[#1E1B4B] mb-0.5">Protected Workspace</p>
            <p class="text-xs text-gray-500 leading-relaxed">
                Import templates and pricing rules for this workspace are managed separately by your administrator
                and can't be changed from this wizard.
            </p>
        </div>
    </div>

    <div class="card">
        <h3 class="font-semibold text-[#1E1B4B] text-sm mb-2">Current import setup</h3>
        <p class="text-xs text-gray-500 leading-relaxed">
            This workspace uses a dedicated import template and pricing service maintained by your administrator.
            Existing import templates won't be changed when this program is published.
        </p>
    </div>
    @else

    <p class="text-sm text-gray-500 mb-2">
        Pick a starting template that matches your industry. You can fine-tune column mappings later when you import your first file.
    </p>

    <div x-data="{ templates: @js($options['importTemplates']) }" class="space-y-4">

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <template x-for="[key, tpl] in Object.entries(templates)" :key="key">
                <label class="flex flex-col gap-1 p-3 rounded-xl border cursor-pointer transition-colors"
                       :class="data.template_key === key ? 'border-[#7B61FF] bg-purple-50' : 'border-gray-200 hover:border-purple-200'">
                    <div class="flex items-center gap-2">
                        <input type="radio" x-model="data.template_key" :value="key" class="accent-[#7B61FF]">
                        <span class="text-sm font-medium text-[#1E1B4B]" x-text="tpl.label"></span>
                    </div>
                    <p class="text-xs text-gray-400 pl-5" x-text="tpl.description"></p>
                </label>
            </template>
        </div>
        <p x-show="errors.template_key" x-cloak class="text-xs text-red-600 mt-1" x-text="errors.template_key?.[0]"></p>

        <label class="flex items-start gap-3 p-3 rounded-xl border border-gray-200 cursor-pointer">
            <input type="checkbox" x-model="data.enable_imports" class="mt-0.5 accent-[#7B61FF]">
            <div>
                <p class="text-sm font-medium text-[#1E1B4B]">Allow bulk CSV imports for this program</p>
                <p class="text-xs text-gray-400 mt-0.5">Tenant Admins can import deals and referrers from a spreadsheet using this template.</p>
            </div>
        </label>

        <label class="flex items-start gap-3 p-3 rounded-xl border border-gray-200 cursor-pointer">
            <input type="checkbox" x-model="data.notify_on_import_complete" class="mt-0.5 accent-[#7B61FF]">
            <div>
                <p class="text-sm font-medium text-[#1E1B4B]">Notify me when an import finishes</p>
                <p class="text-xs text-gray-400 mt-0.5">Sends an in-app notification (and email, if enabled) when an import completes or fails.</p>
            </div>
        </label>

        {{-- Preview of selected template --}}
        <div class="card bg-gray-50/50">
            <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">
                Template preview — <span x-text="templates[data.template_key]?.label"></span>
            </h4>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                <div>
                    <p class="text-gray-400 mb-1">Required columns</p>
                    <ul class="space-y-0.5">
                        <template x-for="f in (templates[data.template_key]?.required_fields || [])" :key="f">
                            <li class="font-mono text-[#1E1B4B]" x-text="f"></li>
                        </template>
                    </ul>
                </div>
                <div>
                    <p class="text-gray-400 mb-1">Optional columns</p>
                    <ul class="space-y-0.5">
                        <template x-for="f in (templates[data.template_key]?.optional_fields || [])" :key="f">
                            <li class="font-mono text-[#1E1B4B]" x-text="f"></li>
                        </template>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
