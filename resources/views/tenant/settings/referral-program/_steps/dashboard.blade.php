<div class="space-y-4"
     x-data="{ presets: @js($options['dashboardPresets']), widgets: @js($options['dashboardWidgets']) }">

    <p class="text-sm text-gray-500 mb-2">
        Pick a starting set of metrics for your team's dashboard. Choose "Custom" to build your own — everyone can
        still personalize their own view later.
    </p>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <template x-for="[key, preset] in Object.entries(presets)" :key="key">
            <label class="flex flex-col gap-1 p-3 rounded-xl border cursor-pointer transition-colors"
                   :class="data.dashboard_preset === key ? 'border-[#7B61FF] bg-purple-50' : 'border-gray-200 hover:border-purple-200'">
                <div class="flex items-center gap-2">
                    <input type="radio" x-model="data.dashboard_preset" :value="key" class="accent-[#7B61FF]"
                           @change="if (key !== 'custom') data.visible_widgets = [...preset.widgets]">
                    <span class="text-sm font-medium text-[#1E1B4B]" x-text="preset.label"></span>
                </div>
                <p class="text-xs text-gray-400 pl-5" x-text="preset.description"></p>
            </label>
        </template>
    </div>
    <p x-show="errors.dashboard_preset" x-cloak class="text-xs text-red-600 mt-1" x-text="errors.dashboard_preset?.[0]"></p>

    <div class="card bg-gray-50/50">
        <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">
            Widgets on this dashboard
            <span x-show="data.dashboard_preset !== 'custom'" x-cloak class="font-normal text-gray-400 normal-case">— choose "Custom" to edit individually</span>
        </h4>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
            <template x-for="[key, label] in Object.entries(widgets)" :key="key">
                <label class="flex items-center gap-2 text-sm" :class="data.dashboard_preset !== 'custom' ? 'opacity-60' : 'cursor-pointer'">
                    <input type="checkbox"
                           :checked="data.visible_widgets.includes(key)"
                           :disabled="data.dashboard_preset !== 'custom'"
                           @change="data.visible_widgets.includes(key) ? data.visible_widgets.splice(data.visible_widgets.indexOf(key), 1) : data.visible_widgets.push(key)"
                           class="accent-[#7B61FF]">
                    <span x-text="label" class="text-[#1E1B4B]"></span>
                </label>
            </template>
        </div>
        <p x-show="errors.visible_widgets" x-cloak class="text-xs text-red-600 mt-2" x-text="errors.visible_widgets?.[0]"></p>
    </div>

    <div>
        <label class="form-label">Default date range</label>
        <select x-model="data.default_date_range" class="form-input max-w-xs">
            <option value="7d">Last 7 days</option>
            <option value="30d">Last 30 days</option>
            <option value="90d">Last 90 days</option>
            <option value="ytd">Year to date</option>
        </select>
        <p class="text-xs text-gray-400 mt-1">The default time window for dashboard charts. Anyone can change it for their own view.</p>
        <p x-show="errors.default_date_range" x-cloak class="text-xs text-red-600 mt-1" x-text="errors.default_date_range?.[0]"></p>
    </div>
</div>
