@extends('layouts.app')
@section('title', 'Import Center')
@section('nav') @include('platform._nav') @endsection

@section('content')
<div x-data="importCenter()" x-init="init()" class="space-y-5">

    {{-- Sub-tab nav --}}
    <div class="card p-1.5">
        <div class="flex gap-1 overflow-x-auto">
            @php $tabs = ['New Import','Templates','Import Jobs','Validation Errors','Import History','Duplicate Review','Rollback Center','Settings']; @endphp
            @foreach($tabs as $i => $tab)
            <button @click="activeTab = {{ $i }}"
                    :class="activeTab === {{ $i }} ? 'tab-active' : 'text-gray-600 hover:bg-gray-100'"
                    class="px-3 py-1.5 rounded-xl text-xs font-medium whitespace-nowrap transition-colors flex items-center gap-1.5">
                {{ $tab }}
                @if($i === 2)
                    <span x-show="{{ $stats['active_jobs'] }} > 0"
                          class="w-4 h-4 bg-blue-500 text-white rounded-full text-[10px] flex items-center justify-center">{{ $stats['active_jobs'] }}</span>
                @endif
                @if($i === 1 && $stats['pending_approval'] > 0)
                    <span class="w-4 h-4 bg-orange-500 text-white rounded-full text-[10px] flex items-center justify-center">{{ $stats['pending_approval'] }}</span>
                @endif
                @if($i === 5 && $stats['pending_duplicates'] > 0)
                    <span class="w-4 h-4 bg-red-500 text-white rounded-full text-[10px] flex items-center justify-center">{{ $stats['pending_duplicates'] }}</span>
                @endif
            </button>
            @endforeach
        </div>
    </div>

    {{-- ── TAB 0: New Import ── --}}
    <div x-show="activeTab === 0" class="space-y-5">

        {{-- Step indicator (5 steps) --}}
        <div class="card py-4">
            <div class="flex items-center gap-0">
                @foreach(['Choose Type','Upload','Map Columns','Validate','Import'] as $si => $stepLabel)
                <div class="flex-1 flex flex-col items-center gap-1">
                    <div :class="wizardStep >= {{ $si }} ? 'bg-[#7B61FF] text-white' : 'bg-gray-100 text-gray-400'"
                         class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold transition-colors">
                        <template x-if="wizardStep > {{ $si }}"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg></template>
                        <template x-if="wizardStep <= {{ $si }}"><span>{{ $si + 1 }}</span></template>
                    </div>
                    <span class="text-xs text-gray-500 text-center hidden sm:block">{{ $stepLabel }}</span>
                </div>
                @if($si < 4)<div class="flex-none w-4 h-px bg-gray-200 mt-3.5"></div>@endif
                @endforeach
            </div>
        </div>

        {{-- Step 0: Choose type --}}
        <div x-show="wizardStep === 0">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <template x-for="type in objectTypes" :key="type.type">
                    <button @click="selectObjectType(type.type)"
                            :class="newImport.object_type === type.type ? 'ring-2 ring-[#7B61FF] bg-purple-50' : 'hover:bg-gray-50'"
                            class="card text-left transition-all">
                        <div class="flex items-start gap-3">
                            <div class="w-10 h-10 rounded-xl bg-purple-100 flex items-center justify-center shrink-0 text-lg" x-text="typeEmoji(type.type)"></div>
                            <div>
                                <p class="font-semibold text-[#1E1B4B] text-sm" x-text="type.label"></p>
                                <p class="text-xs text-gray-400 mt-0.5" x-text="typeDescription(type.type)"></p>
                            </div>
                        </div>
                    </button>
                </template>
            </div>
            <div class="flex justify-between mt-4" x-show="newImport.object_type">
                <a :href="'/api/imports/templates/' + newImport.object_type + '/download?sample=1'" class="btn-secondary text-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Download Template
                </a>
                <button @click="wizardStep = 1" class="btn-primary">
                    Continue with <span x-text="selectedTypeLabel()"></span>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </button>
            </div>
        </div>

        {{-- Step 1: Upload --}}
        <div x-show="wizardStep === 1" class="space-y-4">
            <div class="card">
                <h3 class="font-semibold text-[#1E1B4B] mb-4">Upload Your File</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                    <button @click="uploadMode = 'file'" :class="uploadMode==='file' ? 'ring-2 ring-[#7B61FF] bg-purple-50' : 'bg-gray-50 hover:bg-gray-100'"
                            class="flex flex-col items-center gap-2 p-4 rounded-xl border-2 border-dashed border-gray-200 transition-all text-sm text-gray-600">
                        <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                        Upload CSV File
                    </button>
                    <button @click="uploadMode = 'paste'" :class="uploadMode==='paste' ? 'ring-2 ring-[#7B61FF] bg-purple-50' : 'bg-gray-50 hover:bg-gray-100'"
                            class="flex flex-col items-center gap-2 p-4 rounded-xl border-2 border-dashed border-gray-200 transition-all text-sm text-gray-600">
                        <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                        Paste from Spreadsheet
                    </button>
                </div>

                <div x-show="uploadMode === 'file'">
                    <label class="block p-8 border-2 border-dashed border-gray-200 rounded-xl text-center cursor-pointer hover:border-purple-400 hover:bg-purple-50 transition-all">
                        <input type="file" accept=".csv" class="hidden" x-ref="fileInput" @change="handleFileSelect($event)">
                        <svg class="w-10 h-10 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                        <p x-show="!selectedFile" class="text-sm text-gray-500">Click to upload or drag & drop</p>
                        <p x-show="!selectedFile" class="text-xs text-gray-400 mt-1">CSV files up to 25MB</p>
                        <p x-show="selectedFile" class="text-sm font-medium text-purple-700" x-text="selectedFile?.name"></p>
                    </label>
                </div>

                <div x-show="uploadMode === 'paste'">
                    <label class="form-label">Paste spreadsheet data (first row = headers)</label>
                    <textarea x-model="pastedContent" rows="8" class="form-input font-mono text-xs"
                              placeholder="tenant_name,industry,primary_admin_email&#10;Sunrise Realty,Real Estate,admin@sunrise.com"></textarea>
                </div>

                <p x-show="uploadError" class="mt-2 text-xs text-red-600" x-text="uploadError"></p>
            </div>

            {{-- Advanced Settings collapsible --}}
            <div class="card">
                <button @click="showAdvanced = !showAdvanced" type="button" class="w-full flex items-center justify-between text-sm font-medium text-gray-600 hover:text-[#1E1B4B]">
                    <span class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>
                        Advanced Settings
                    </span>
                    <svg :class="showAdvanced ? 'rotate-180' : ''" class="w-4 h-4 text-gray-400 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="showAdvanced" class="mt-4 space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="form-label">Import Name</label>
                            <input type="text" x-model="newImport.import_name" class="form-input" placeholder="e.g. May 2026 Tenant Import">
                        </div>
                        <div>
                            <label class="form-label">Import Mode</label>
                            <select x-model="newImport.import_mode" class="form-input">
                                <option value="upsert">Upsert — Create new + update existing</option>
                                <option value="create_only">Create new records only</option>
                                <option value="update_only">Update existing records only</option>
                                <option value="validate_only">Validate only (dry run)</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Overwrite Behavior</label>
                            <select x-model="newImport.overwrite_mode" class="form-input">
                                <option value="overwrite_mapped">Overwrite only mapped fields</option>
                                <option value="skip_existing">Skip existing records</option>
                                <option value="update_blank_only">Update blank fields only</option>
                                <option value="overwrite_all">Overwrite all included columns</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Date Format</label>
                            <select x-model="newImport.date_format" class="form-input">
                                <option value="YYYY-MM-DD">YYYY-MM-DD (ISO standard)</option>
                                <option value="MM/DD/YYYY">MM/DD/YYYY (US format)</option>
                                <option value="DD/MM/YYYY">DD/MM/YYYY (Philippine format)</option>
                            </select>
                        </div>
                    </div>
                    <div class="p-3 bg-amber-50 border border-amber-200 rounded-xl text-xs text-amber-800">
                        <strong>Safe by default:</strong> Blank cells will NOT overwrite existing data unless you choose "Overwrite all" or use the CLEAR keyword.
                    </div>
                </div>
            </div>

            <div class="flex justify-between">
                <button @click="wizardStep = 0" class="btn-secondary">Back</button>
                <button @click="uploadAndParse()" :disabled="uploading || (!selectedFile && !pastedContent)" class="btn-primary">
                    <svg x-show="!uploading" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                    <svg x-show="uploading" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    <span x-text="uploading ? 'Uploading...' : 'Upload & Validate'"></span>
                </button>
            </div>
        </div>

        {{-- Step 2: Column Mapping --}}
        <div x-show="wizardStep === 2" class="space-y-4">
            <div class="card p-0 overflow-hidden">
                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                    <div>
                        <h3 class="font-semibold text-[#1E1B4B]">Column Mapping</h3>
                        <p class="text-xs text-gray-400 mt-0.5">Verify that each column from your file maps to the correct system field.</p>
                    </div>
                    <span x-show="mappingComplete"  class="badge badge-green text-xs">All required columns mapped</span>
                    <span x-show="!mappingComplete" class="badge badge-red text-xs">Missing required columns</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead><tr class="table-head"><th>Your Column</th><th>System Field</th><th>Confidence</th><th>Required</th><th>Action</th></tr></thead>
                        <tbody>
                            <template x-for="(col, i) in columnMapping" :key="i">
                                <tr class="table-row">
                                    <td class="font-mono text-xs text-[#1E1B4B]" x-text="col.file_column"></td>
                                    <td>
                                        <select x-model="col.system_field" @change="checkMappingComplete()" class="form-input text-xs py-1">
                                            <option value="">— Skip this column —</option>
                                            <template x-for="field in allSystemFields" :key="field">
                                                <option :value="field" x-text="field"></option>
                                            </template>
                                        </select>
                                    </td>
                                    <td>
                                        <span :class="{
                                            'badge badge-green':  col.confidence === 'high',
                                            'badge badge-orange': col.confidence === 'medium',
                                            'badge badge-red':    col.confidence === 'low' || col.confidence === 'none',
                                            'badge badge-gray':   true,
                                        }" x-text="col.confidence || 'none'"></span>
                                    </td>
                                    <td>
                                        <span x-show="requiredFields.includes(col.system_field)" class="badge badge-red text-xs">Required</span>
                                        <span x-show="!requiredFields.includes(col.system_field)" class="text-gray-400 text-xs">Optional</span>
                                    </td>
                                    <td>
                                        <button @click="col.skip = !col.skip; col.system_field = col.skip ? '' : col.system_field"
                                                :class="col.skip ? 'badge-gray' : 'badge-blue'"
                                                class="badge text-xs">
                                            <span x-text="col.skip ? 'Skipped' : 'Include'"></span>
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
                <div x-show="missingRequiredCols.length > 0" class="px-5 py-3 bg-red-50 border-t border-red-100 text-xs text-red-700">
                    Missing required columns: <strong x-text="missingRequiredCols.join(', ')"></strong>
                </div>
            </div>

            <div class="flex justify-between">
                <button @click="wizardStep = 1" class="btn-secondary">Back</button>
                <button @click="saveMapping()" :disabled="!mappingComplete || savingMapping" class="btn-primary">
                    <span x-text="savingMapping ? 'Saving...' : 'Confirm Mapping'"></span>
                </button>
            </div>
        </div>

        {{-- Step 3: Validate + Preview --}}
        <div x-show="wizardStep === 3" class="space-y-4">
            <div class="card text-center py-10" x-show="validating">
                <svg class="w-8 h-8 mx-auto text-purple-400 animate-spin mb-3" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                <p class="text-gray-500 text-sm">Validating rows, detecting duplicates…</p>
            </div>

            <div x-show="!validating && validationResult" class="space-y-4">
                {{-- Quality score --}}
                <div class="card flex items-center gap-4">
                    <div class="relative w-16 h-16 shrink-0">
                        <svg class="w-16 h-16 -rotate-90" viewBox="0 0 64 64">
                            <circle cx="32" cy="32" r="26" fill="none" stroke="#EDE9FE" stroke-width="7"/>
                            <circle cx="32" cy="32" r="26" fill="none"
                                    :stroke="(validationResult?.quality_score || 0) >= 80 ? '#10B981' : (validationResult?.quality_score || 0) >= 60 ? '#F59E0B' : '#EF4444'"
                                    stroke-width="7"
                                    :stroke-dasharray="(validationResult?.quality_score || 0) * 1.6327 + ' 163.27'"
                                    stroke-linecap="round"/>
                        </svg>
                        <div class="absolute inset-0 flex items-center justify-center">
                            <span class="text-sm font-bold text-[#1E1B4B]" x-text="validationResult?.quality_score || 0"></span>
                        </div>
                    </div>
                    <div>
                        <p class="font-semibold text-[#1E1B4B]">Import Quality Score</p>
                        <p class="text-sm text-gray-500" x-text="(validationResult?.quality_score || 0) >= 80 ? 'Good to import' : (validationResult?.quality_score || 0) >= 60 ? 'Needs review' : 'High risk — fix errors first'"></p>
                    </div>
                    <div class="ml-auto shrink-0">
                        <span :class="{
                            'badge badge-green':  validationResult?.risk_level === 'low',
                            'badge badge-orange': validationResult?.risk_level === 'medium',
                            'badge badge-red':    validationResult?.risk_level === 'high' || validationResult?.risk_level === 'critical',
                        }" x-text="(validationResult?.risk_level || 'low').toUpperCase() + ' RISK'"></span>
                    </div>
                </div>

                {{-- Summary counts --}}
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                    @foreach([
                        ['total_rows','Total Rows','bg-gray-100','text-gray-700'],
                        ['valid_rows','Valid','bg-emerald-100','text-emerald-700'],
                        ['error_rows','Errors','bg-red-100','text-red-700'],
                        ['warning_rows','Warnings','bg-orange-100','text-orange-700'],
                        ['duplicate_rows','Duplicates','bg-yellow-100','text-yellow-700'],
                        ['skipped_rows','Skipped','bg-blue-100','text-blue-700'],
                    ] as [$key, $label, $bg, $color])
                    <div class="card py-3 px-4 text-center">
                        <p class="text-xs text-gray-500">{{ $label }}</p>
                        <p class="text-xl font-bold {{ $color }} mt-0.5" x-text="validationResult?.{{ $key }} ?? 0"></p>
                    </div>
                    @endforeach
                </div>

                {{-- Preview rows --}}
                <div class="card" x-show="previewRows.length > 0">
                    <h3 class="font-semibold text-[#1E1B4B] mb-3">Row Preview</h3>
                    <div class="space-y-2">
                        <template x-for="row in previewRows.slice(0,8)" :key="row.id">
                            <div :class="{
                                'border-l-4 border-emerald-400 bg-emerald-50': row.status === 'valid_new',
                                'border-l-4 border-yellow-400 bg-yellow-50':   row.status === 'valid_update',
                                'border-l-4 border-orange-400 bg-orange-50':   row.status === 'warning',
                                'border-l-4 border-red-400 bg-red-50':         row.status === 'error',
                                'border-l-4 border-blue-400 bg-blue-50':       row.status === 'skipped',
                            }" class="flex items-center gap-3 px-3 py-2 rounded-xl">
                                <span class="text-xs text-gray-400 shrink-0 w-12">Row <span x-text="row.row_number"></span></span>
                                <span :class="{
                                    'badge badge-green':  row.status === 'valid_new' || row.status === 'imported',
                                    'badge badge-orange': row.status === 'valid_update' || row.status === 'warning',
                                    'badge badge-red':    row.status === 'error',
                                    'badge badge-blue':   row.status === 'skipped',
                                }" class="text-xs shrink-0" x-text="statusLabel(row.status)"></span>
                                <p class="text-xs text-gray-600 truncate font-mono" x-text="JSON.stringify(row.mapped_data_json).slice(0,120)"></p>
                            </div>
                        </template>
                    </div>
                    <div class="mt-3 pt-3 border-t border-gray-100 flex flex-wrap gap-3 text-xs">
                        @foreach([['bg-emerald-400','New record'],['bg-yellow-400','Will update'],['bg-orange-400','Warning'],['bg-red-400','Error'],['bg-blue-400','Skipped']] as [$c,$l])
                        <div class="flex items-center gap-1.5"><div class="w-3 h-3 rounded-full {{ $c }}"></div><span class="text-gray-500">{{ $l }}</span></div>
                        @endforeach
                    </div>
                </div>

                {{-- Approval warning --}}
                <div x-show="currentJob?.approval_required" class="card bg-orange-50 border border-orange-200">
                    <div class="flex items-center gap-3">
                        <svg class="w-5 h-5 text-orange-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        <div>
                            <p class="font-semibold text-orange-800 text-sm">Approval Required</p>
                            <p class="text-xs text-orange-700 mt-0.5">This import has been marked as high-risk and requires approval before it can run.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex justify-between" x-show="!validating">
                <button @click="wizardStep = 2" class="btn-secondary">Back</button>
                <div class="flex gap-3">
                    <a :href="currentJob ? '/api/imports/jobs/' + currentJob.id + '/report/download' : '#'" class="btn-secondary" x-show="validationResult?.error_rows > 0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        Download Error File
                    </a>
                    <button @click="confirmImport()" :disabled="importing || !validationResult || (validationResult?.error_rows || 0) === validationResult?.total_rows" class="btn-primary">
                        <svg x-show="!importing" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                        <svg x-show="importing" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        <span x-text="importing ? 'Importing…' : 'Confirm Import'"></span>
                    </button>
                </div>
            </div>
        </div>

        {{-- Step 4: Done --}}
        <div x-show="wizardStep === 4" class="card text-center py-12 space-y-4">
            <div x-show="importResult?.status === 'completed'">
                <div class="w-16 h-16 rounded-full bg-emerald-100 flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                </div>
                <h3 class="text-xl font-bold text-[#1E1B4B]">Import Complete</h3>
                <p class="text-gray-500 text-sm mt-1">
                    <span x-text="importResult?.created"></span> created ·
                    <span x-text="importResult?.updated"></span> updated ·
                    <span x-text="importResult?.failed"></span> failed
                </p>
            </div>
            <div x-show="importResult?.status === 'completed_with_errors'" class="text-orange-700">
                <p class="font-semibold">Completed with errors</p>
            </div>
            <div class="flex justify-center gap-3 mt-4">
                <button @click="resetWizard()" class="btn-secondary">New Import</button>
                <button @click="activeTab = 2" class="btn-primary">View Import Jobs</button>
            </div>
        </div>
    </div>

    {{-- ── TAB 1: Templates ── --}}
    <div x-show="activeTab === 1" class="space-y-4">
        <div class="card">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="font-semibold text-[#1E1B4B]">Standard Import Templates</h3>
                    <p class="text-xs text-gray-400 mt-0.5">Download templates to prepare your import files.</p>
                </div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <template x-for="type in objectTypes" :key="type.type">
                    <div class="card hover:shadow-md transition-shadow space-y-3">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-purple-100 flex items-center justify-center text-lg shrink-0" x-text="typeEmoji(type.type)"></div>
                            <div>
                                <p class="font-semibold text-[#1E1B4B] text-sm" x-text="type.label + ' Import'"></p>
                                <p class="text-xs text-gray-400">v1.0 · CSV</p>
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <a :href="'/api/imports/templates/' + type.type + '/download'" class="btn-secondary text-xs flex-1 justify-center">
                                Blank
                            </a>
                            <a :href="'/api/imports/templates/' + type.type + '/download?sample=1'" class="btn-primary text-xs flex-1 justify-center">
                                With Sample
                            </a>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    {{-- ── TAB 2: Import Jobs ── --}}
    <div x-show="activeTab === 2" class="space-y-4">
        <div class="flex flex-col sm:flex-row gap-3 sm:items-center">
            <div class="search-group flex-1">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" x-model="jobSearch" @input.debounce="filterJobs()" placeholder="Search import jobs…">
            </div>
            <select x-model="jobStatusFilter" @change="loadJobs()" class="form-input sm:w-44">
                <option value="">All Status</option>
                <option value="importing">Importing</option>
                <option value="completed">Completed</option>
                <option value="completed_with_errors">With Errors</option>
                <option value="failed">Failed</option>
                <option value="waiting_for_approval">Awaiting Approval</option>
                <option value="canceled">Canceled</option>
            </select>
        </div>

        <div class="card p-0 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead><tr class="table-head"><th>Import Name</th><th>Type</th><th>Rows</th><th>Status</th><th>Risk</th><th>Date</th><th></th></tr></thead>
                    <tbody>
                        <template x-if="loadingJobs"><tr><td colspan="7" class="py-10 text-center text-gray-400">Loading…</td></tr></template>
                        <template x-if="!loadingJobs && filteredJobs.length === 0"><tr><td colspan="7" class="py-10 text-center text-gray-400">No import jobs found</td></tr></template>
                        <template x-for="job in filteredJobs" :key="job.id">
                            <tr class="table-row cursor-pointer" @click="viewJob(job.id)">
                                <td>
                                    <p class="font-medium text-[#1E1B4B] text-sm" x-text="job.import_name"></p>
                                    <p class="text-xs text-gray-400" x-text="job.file_name"></p>
                                </td>
                                <td><span class="badge badge-purple text-xs capitalize" x-text="job.object_type?.replace('_',' ')"></span></td>
                                <td class="text-sm">
                                    <span class="text-emerald-600" x-text="job.successful_rows"></span>
                                    <span class="text-gray-400">/<span x-text="job.total_rows"></span></span>
                                </td>
                                <td>
                                    <span :class="{
                                        'badge badge-green':  job.status === 'completed',
                                        'badge badge-orange': job.status === 'completed_with_errors' || job.status === 'waiting_for_approval',
                                        'badge badge-red':    job.status === 'failed',
                                        'badge badge-blue':   ['importing','validating_rows','parsing'].includes(job.status),
                                        'badge badge-gray':   ['canceled','rolled_back'].includes(job.status),
                                    }" x-text="statusLabel(job.status)"></span>
                                </td>
                                <td>
                                    <span :class="{
                                        'badge badge-green':  job.risk_level === 'low',
                                        'badge badge-orange': job.risk_level === 'medium',
                                        'badge badge-red':    job.risk_level === 'high' || job.risk_level === 'critical',
                                    }" x-text="(job.risk_level || 'low').toUpperCase()"></span>
                                </td>
                                <td class="text-xs text-gray-400" x-text="job.created_at ? new Date(job.created_at).toLocaleDateString() : '—'"></td>
                                <td class="text-right">
                                    <a :href="'/platform/import/' + job.id" class="text-xs text-purple-600 hover:text-purple-700 font-medium" @click.stop>View →</a>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ── TAB 3: Validation Errors ── --}}
    <div x-show="activeTab === 3" class="space-y-4">
        <div class="card p-0 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                <h3 class="font-semibold text-[#1E1B4B]">Recent Validation Errors</h3>
                <button @click="loadErrors()" class="text-xs text-purple-600 hover:text-purple-700">Refresh</button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead><tr class="table-head"><th>Job</th><th>Row</th><th>Column</th><th>Error</th><th>Severity</th><th>Fix</th></tr></thead>
                    <tbody>
                        <template x-if="recentErrors.length === 0"><tr><td colspan="6" class="py-10 text-center text-gray-400">No recent errors</td></tr></template>
                        <template x-for="err in recentErrors" :key="err.id">
                            <tr class="table-row">
                                <td class="text-xs text-gray-500" x-text="err.import_job_id?.slice(0,8) + '…'"></td>
                                <td class="text-sm" x-text="err.row_number"></td>
                                <td class="font-mono text-xs text-[#1E1B4B]" x-text="err.column_name || '—'"></td>
                                <td class="text-xs text-gray-600 max-w-xs truncate" x-text="err.error_message"></td>
                                <td><span :class="err.severity === 'blocking' ? 'badge badge-red' : 'badge badge-orange'" x-text="err.severity" class="text-xs"></span></td>
                                <td class="text-xs text-gray-400" x-text="err.suggested_fix || '—'"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ── TAB 4: Import History ── --}}
    <div x-show="activeTab === 4" class="space-y-4">
        <div class="card text-center py-10 text-gray-400 text-sm">
            Import history shows the same data as Import Jobs filtered to completed imports.<br>
            <button @click="jobStatusFilter='completed'; activeTab=2; loadJobs()" class="mt-2 text-purple-600 hover:text-purple-700 text-sm font-medium">View Completed Imports →</button>
        </div>
    </div>

    {{-- ── TAB 5: Duplicate Review ── --}}
    <div x-show="activeTab === 5" class="space-y-4">
        <div class="card p-0 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                <h3 class="font-semibold text-[#1E1B4B]">Pending Duplicate Review</h3>
                <span class="badge badge-orange" x-text="pendingDuplicates.length + ' pending'"></span>
            </div>
            <div class="space-y-0 divide-y divide-gray-100">
                <template x-if="pendingDuplicates.length === 0">
                    <div class="py-10 text-center text-gray-400 text-sm">No duplicates pending review</div>
                </template>
                <template x-for="dup in pendingDuplicates" :key="dup.id">
                    <div class="p-4 flex flex-col sm:flex-row sm:items-center gap-4">
                        <div class="flex-1">
                            <p class="text-sm font-medium text-[#1E1B4B] capitalize" x-text="dup.object_type + ' duplicate'"></p>
                            <p class="text-xs text-gray-400 mt-0.5">Match score: <span x-text="dup.match_score + '%'"></span></p>
                        </div>
                        <div class="flex gap-2 shrink-0">
                            <button @click="resolveDuplicate(dup.id, 'not_duplicate')" class="btn-secondary text-xs">Not a duplicate</button>
                            <button @click="resolveDuplicate(dup.id, 'confirmed_duplicate')" class="btn-danger text-xs">Skip row</button>
                            <button @click="resolveDuplicate(dup.id, 'merged')" class="btn-primary text-xs">Merge</button>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    {{-- ── TAB 6: Rollback Center ── --}}
    <div x-show="activeTab === 6" class="space-y-4">
        <div class="card p-0 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100">
                <h3 class="font-semibold text-[#1E1B4B]">Rollback-Eligible Imports</h3>
                <p class="text-xs text-gray-400 mt-0.5">Imports can be rolled back within 30 days of completion.</p>
            </div>
            <template x-for="job in rollbackJobs" :key="job.id">
                <div class="flex items-center gap-4 px-5 py-4 border-b border-gray-50 last:border-0">
                    <div class="flex-1">
                        <p class="font-medium text-[#1E1B4B] text-sm" x-text="job.import_name"></p>
                        <p class="text-xs text-gray-400 mt-0.5">
                            <span x-text="job.successful_rows"></span> records ·
                            <span x-text="job.completed_at ? new Date(job.completed_at).toLocaleDateString() : '—'"></span>
                        </p>
                    </div>
                    <span class="badge badge-green text-xs">Available</span>
                    <button @click="initiateRollback(job.id)" class="btn-danger text-xs">Rollback</button>
                </div>
            </template>
            <div x-show="rollbackJobs.length === 0" class="py-10 text-center text-gray-400 text-sm">No imports eligible for rollback</div>
        </div>
    </div>

    {{-- ── TAB 7: Settings ── --}}
    <div x-show="activeTab === 7" class="space-y-4">
        <div class="card space-y-4">
            <h3 class="font-semibold text-[#1E1B4B]">Import Settings</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Max file size (MB)</label>
                    <input type="number" value="25" class="form-input" min="1" max="100">
                </div>
                <div>
                    <label class="form-label">Max rows per import</label>
                    <input type="number" value="50000" class="form-input">
                </div>
                <div>
                    <label class="form-label">Rollback window (days)</label>
                    <input type="number" value="30" class="form-input" min="1" max="90">
                </div>
                <div>
                    <label class="form-label">File retention (days)</label>
                    <input type="number" value="30" class="form-input" min="7" max="365">
                </div>
            </div>
            <div class="flex justify-end">
                <button class="btn-primary">Save Settings</button>
            </div>
        </div>
    </div>

    {{-- Rollback confirmation modal --}}
    <div x-show="showRollbackModal" x-cloak class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6 space-y-4" @click.stop>
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-red-100 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                </div>
                <div>
                    <p class="font-semibold text-[#1E1B4B]">Confirm Rollback</p>
                    <p class="text-xs text-gray-500 mt-0.5">This will delete all records created by this import and restore any overwritten values. This cannot be undone.</p>
                </div>
            </div>
            <div class="flex justify-end gap-3">
                <button @click="showRollbackModal = false" class="btn-secondary">Cancel</button>
                <button @click="executeRollback()" :disabled="rollingBack" class="btn-danger" x-text="rollingBack ? 'Rolling back…' : 'Yes, Roll Back'"></button>
            </div>
        </div>
    </div>

</div>

<script>
function importCenter() {
    return {
        activeTab: 0,
        wizardStep: 0,
        showAdvanced: false,
        uploadMode: 'file',
        selectedFile: null,
        pastedContent: '',
        uploading: false,
        uploadError: '',
        validating: false,
        importing: false,
        savingMapping: false,
        loadingJobs: false,
        jobSearch: '',
        jobStatusFilter: '',
        newImport: { object_type: '', import_name: '', import_mode: 'upsert', overwrite_mode: 'overwrite_mapped', date_format: 'YYYY-MM-DD' },
        currentJob: null,
        columnMapping: [],
        allSystemFields: [],
        requiredFields: [],
        mappingComplete: false,
        missingRequiredCols: [],
        validationResult: null,
        importResult: null,
        previewRows: [],
        jobs: [],
        filteredJobs: [],
        recentErrors: [],
        pendingDuplicates: [],
        rollbackJobs: [],
        showRollbackModal: false,
        rollbackTargetId: null,
        rollingBack: false,
        objectTypes: [],

        async init() {
            const res = await fetch('/api/imports/templates');
            this.objectTypes = await res.json();
            await this.loadJobs();
            await this.loadErrors();
            await this.loadDuplicates();
            await this.loadRollbackJobs();
        },

        typeEmoji(type) {
            const m = { tenant:'🏢', reseller:'👥', lead:'👤', custom_field:'🔧', tag:'🏷️' };
            return m[type] || '📄';
        },

        typeDescription(type) {
            const m = { tenant:'Import organizations / companies', reseller:'Import referral partners', lead:'Import referral records', custom_field:'Import field definitions', tag:'Import tags and labels' };
            return m[type] || '';
        },

        selectedTypeLabel() {
            return this.objectTypes.find(t => t.type === this.newImport.object_type)?.label || this.newImport.object_type;
        },

        selectObjectType(type) {
            this.newImport.object_type = type;
            this.newImport.import_name = this.selectedTypeLabel() + ' Import — ' + new Date().toLocaleDateString('en', {month:'short',day:'numeric',year:'numeric'});
        },

        handleFileSelect(e) {
            this.selectedFile = e.target.files[0] || null;
            this.uploadError  = '';
        },

        async uploadAndParse() {
            this.uploading    = true;
            this.uploadError  = '';

            try {
                const fd = new FormData();
                fd.append('object_type',    this.newImport.object_type);
                fd.append('import_name',    this.newImport.import_name);
                fd.append('import_mode',    this.newImport.import_mode);
                fd.append('overwrite_mode', this.newImport.overwrite_mode);
                fd.append('date_format',    this.newImport.date_format);

                if (this.uploadMode === 'file' && this.selectedFile) {
                    fd.append('file', this.selectedFile);
                } else if (this.uploadMode === 'paste' && this.pastedContent) {
                    fd.append('pasted_content', this.pastedContent);
                }

                const createRes = await fetch('/api/imports/jobs', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    body: fd,
                });
                this.currentJob = await createRes.json();

                const parseRes = await fetch('/api/imports/jobs/' + this.currentJob.id + '/parse', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                });
                const parseResult = await parseRes.json();

                if (!parseResult.success) {
                    this.uploadError = parseResult.error || 'File could not be parsed.';
                    return;
                }

                const suggRes = await fetch('/api/imports/jobs/' + this.currentJob.id + '/mapping-suggestions');
                const suggData = await suggRes.json();
                this.columnMapping = suggData.suggestions || [];
                this.missingRequiredCols = suggData.completeness?.missing || [];
                this.mappingComplete     = suggData.completeness?.complete ?? false;

                // Load schema for this type
                const schemaRes = await fetch('/api/imports/templates/' + this.newImport.object_type);
                const schema = await schemaRes.json();
                this.requiredFields   = schema.required || [];
                this.allSystemFields  = [...(schema.required||[]), ...(schema.optional||[])];

                this.wizardStep = 2;
            } catch(e) {
                this.uploadError = 'Upload failed: ' + e.message;
            } finally {
                this.uploading = false;
            }
        },

        checkMappingComplete() {
            const mapped = this.columnMapping.filter(c => !c.skip && c.system_field).map(c => c.system_field);
            this.missingRequiredCols = this.requiredFields.filter(r => !mapped.includes(r));
            this.mappingComplete     = this.missingRequiredCols.length === 0;
        },

        async saveMapping() {
            this.savingMapping = true;
            try {
                await fetch('/api/imports/jobs/' + this.currentJob.id + '/mapping', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    body: JSON.stringify({ mapping: this.columnMapping }),
                });

                this.wizardStep = 3;
                await this.runValidation();
            } finally {
                this.savingMapping = false;
            }
        },

        async runValidation() {
            this.validating = true;
            try {
                const res = await fetch('/api/imports/jobs/' + this.currentJob.id + '/validate', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                });
                this.validationResult = await res.json();
                const jobRes = await fetch('/api/imports/jobs/' + this.currentJob.id);
                this.currentJob = await jobRes.json();

                // Load preview rows
                const prevRes = await fetch('/api/imports/jobs/' + this.currentJob.id + '/preview');
                const prevData = await prevRes.json();
                this.previewRows = prevData.rows || [];
            } finally {
                this.validating = false;
            }
        },

        async confirmImport() {
            this.importing = true;
            try {
                const res = await fetch('/api/imports/jobs/' + this.currentJob.id + '/execute', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                });
                this.importResult = await res.json();
                this.wizardStep   = 4;
                await this.loadJobs();
            } finally {
                this.importing = false;
            }
        },

        async loadJobs() {
            this.loadingJobs = true;
            const params = new URLSearchParams();
            if (this.jobStatusFilter) params.set('status', this.jobStatusFilter);
            const res  = await fetch('/api/imports/jobs?' + params);
            const data = await res.json();
            this.jobs         = data.data || [];
            this.filteredJobs = this.jobs;
            this.loadingJobs  = false;
        },

        filterJobs() {
            const q = this.jobSearch.toLowerCase();
            this.filteredJobs = this.jobs.filter(j =>
                j.import_name?.toLowerCase().includes(q) ||
                j.object_type?.toLowerCase().includes(q) ||
                j.file_name?.toLowerCase().includes(q)
            );
        },

        async loadErrors() {
            const res = await fetch('/api/imports/jobs?status=failed&status=completed_with_errors');
            const data = await res.json();
            const failedJobs = (data.data || []).slice(0, 3);
            this.recentErrors = [];
            for (const job of failedJobs) {
                const errRes = await fetch('/api/imports/jobs/' + job.id + '/errors');
                const errs   = await errRes.json();
                this.recentErrors.push(...errs.slice(0, 5));
            }
        },

        async loadDuplicates() {
            const res = await fetch('/api/imports/duplicates');
            const data = await res.json();
            this.pendingDuplicates = data.data || [];
        },

        async loadRollbackJobs() {
            const res = await fetch('/api/imports/jobs?status=completed');
            const data = await res.json();
            this.rollbackJobs = (data.data || []).filter(j => j.rollback_status === 'available');
        },

        async resolveDuplicate(id, action) {
            await fetch('/api/imports/duplicates/' + id + '/resolve', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                body: JSON.stringify({ action }),
            });
            await this.loadDuplicates();
        },

        initiateRollback(jobId) {
            this.rollbackTargetId = jobId;
            this.showRollbackModal = true;
        },

        async executeRollback() {
            this.rollingBack = true;
            try {
                await fetch('/api/imports/jobs/' + this.rollbackTargetId + '/rollback', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                });
                this.showRollbackModal = false;
                await this.loadJobs();
                await this.loadRollbackJobs();
            } finally {
                this.rollingBack = false;
            }
        },

        viewJob(id) { window.location.href = '/platform/import/' + id; },

        statusLabel(status) {
            const m = { uploaded:'Uploaded', parsing:'Reading', structure_validating:'Checking', mapping_required:'Mapping', validating_rows:'Validating', ready_for_review:'Ready', waiting_for_approval:'Awaiting Approval', approved:'Approved', importing:'Importing', completed:'Completed', completed_with_errors:'With Errors', failed:'Failed', canceled:'Canceled', rolled_back:'Rolled Back' };
            return m[status] || status;
        },

        resetWizard() {
            this.wizardStep = 0;
            this.showAdvanced = false;
            this.currentJob = null;
            this.selectedFile = null;
            this.pastedContent = '';
            this.columnMapping = [];
            this.validationResult = null;
            this.importResult = null;
            this.previewRows = [];
            this.uploadError = '';
            this.newImport = { object_type: '', import_name: '', import_mode: 'upsert', overwrite_mode: 'overwrite_mapped', date_format: 'YYYY-MM-DD' };
        },
    }
}
</script>
@endsection
