<div class="space-y-3"
     x-data="{
        colors: ['#60A5FA','#7B61FF','#FBBF24','#34D399','#F87171','#9CA3AF','#34D1C9','#F472B6'],
        addStage() {
            if (data.stages.length >= 10) return;
            data.stages.push({
                stage_key: 'stage_' + Math.random().toString(36).slice(2, 8),
                name: '',
                days: 7,
                color: this.colors[data.stages.length % this.colors.length],
                is_final: false,
                is_won: false,
            });
        },
        removeStage(i) {
            if (data.stages.length <= 2) return;
            data.stages.splice(i, 1);
        },
        moveStage(i, dir) {
            const j = i + dir;
            if (j < 0 || j >= data.stages.length) return;
            const tmp = data.stages[i];
            data.stages[i] = data.stages[j];
            data.stages[j] = tmp;
        },
        onFinalChange(stage, checked) {
            stage.is_final = checked;
            if (!stage.is_final) {
                stage.is_won = false;
                if (stage.days === null) stage.days = 7;
            } else {
                stage.days = null;
            }
        },
     }">

    @if($protected)
    <div class="flex gap-3 p-4 rounded-xl bg-orange-50 border border-orange-100">
        <x-r-bunny variant="warning" size="sm" :decorative="true" class="shrink-0 mt-0.5" />
        <div class="flex-1 min-w-0">
            <p class="text-sm font-semibold text-[#1E1B4B] mb-0.5">Protected Workspace</p>
            <p class="text-xs text-gray-500 leading-relaxed">
                Pipeline stages and stage time limits for this workspace are managed separately by your administrator
                and can't be changed from this wizard.
            </p>
        </div>
    </div>
    @else
    <p class="text-sm text-gray-500 mb-2">
        These stages define how a deal moves through your pipeline, from first contact to won or lost.
        We've pre-filled a starting point based on your program type — reorder, rename, or adjust them to fit your team.
    </p>

    <div class="space-y-2">
        <template x-for="(stage, i) in data.stages" :key="stage.stage_key">
            <div class="flex flex-wrap items-center gap-2 p-3 rounded-xl border border-gray-200">
                <div class="flex flex-col gap-0.5 shrink-0">
                    <button type="button" @click="moveStage(i, -1)" :disabled="i === 0"
                            class="text-gray-400 hover:text-[#7B61FF] disabled:opacity-20 disabled:hover:text-gray-400">
                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M9.47 6.47a.75.75 0 0 1 1.06 0l4.25 4.25a.75.75 0 0 1-1.06 1.06L10 8.06l-3.72 3.72a.75.75 0 0 1-1.06-1.06l4.25-4.25Z" clip-rule="evenodd"/></svg>
                    </button>
                    <button type="button" @click="moveStage(i, 1)" :disabled="i === data.stages.length - 1"
                            class="text-gray-400 hover:text-[#7B61FF] disabled:opacity-20 disabled:hover:text-gray-400">
                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10.53 13.53a.75.75 0 0 1-1.06 0l-4.25-4.25a.75.75 0 1 1 1.06-1.06L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25Z" clip-rule="evenodd"/></svg>
                    </button>
                </div>

                <input type="text" x-model="stage.name" maxlength="100" placeholder="Stage name"
                       class="form-input flex-1 min-w-[140px]">

                <template x-if="!stage.is_final">
                    <input type="number" min="1" max="365" :value="stage.days ?? ''"
                           @input="stage.days = $event.target.value === '' ? null : parseInt($event.target.value, 10)"
                           placeholder="Days" title="Days allowed in this stage" class="form-input w-20 text-sm">
                </template>
                <template x-if="stage.is_final">
                    <span class="text-xs text-gray-400 w-20 text-center shrink-0">No limit</span>
                </template>

                <div class="flex items-center gap-1 shrink-0">
                    <template x-for="c in colors" :key="c">
                        <button type="button" @click="stage.color = c"
                                class="w-5 h-5 rounded-full border border-gray-200"
                                :style="`background-color:${c}`"
                                :class="stage.color === c ? 'ring-2 ring-offset-1 ring-[#7B61FF]' : ''"></button>
                    </template>
                </div>

                <label class="flex items-center gap-1 text-xs text-gray-500 shrink-0">
                    <input type="checkbox" :checked="stage.is_final" @change="onFinalChange(stage, $event.target.checked)" class="accent-[#7B61FF]">
                    Final
                </label>
                <label class="flex items-center gap-1 text-xs text-gray-500 shrink-0" x-show="stage.is_final">
                    <input type="checkbox" x-model="stage.is_won" class="accent-[#7B61FF]">
                    Won
                </label>

                <button type="button" @click="removeStage(i)" :disabled="data.stages.length <= 2"
                        class="text-gray-400 hover:text-red-600 disabled:opacity-20 disabled:hover:text-gray-400 ml-auto shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>
        </template>
    </div>

    <button type="button" @click="addStage()" :disabled="data.stages.length >= 10" class="btn-secondary text-sm disabled:opacity-50">
        + Add Stage
    </button>
    <p class="text-xs text-gray-400">Between 2 and 10 stages. Mark a stage "Final" once it's a closing stage, and "Won" if reaching it means the deal succeeded.</p>

    <p x-show="errors.stages" x-cloak class="text-xs text-red-600 mt-1" x-text="errors.stages?.[0]"></p>
    @endif
</div>
