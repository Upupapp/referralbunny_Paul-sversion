@extends('layouts.app')
@section('title', 'Industry Templates')
@section('nav') @include('platform._nav') @endsection

@section('content')
<div class="space-y-6">

    {{-- Banner --}}
    <div class="gradient-banner rounded-2xl p-6 text-white relative overflow-hidden">
        <div class="relative z-10">
            <h2 class="text-xl font-bold">Industry Templates</h2>
            <p class="text-white/80 text-sm mt-1">Smart defaults for pipeline stages, lead fields, and commission rules — per industry.</p>
        </div>
        <div class="absolute -right-10 -top-10 w-48 h-48 bg-white/10 rounded-full"></div>
        <div class="absolute -right-4 top-10 w-24 h-24 bg-white/10 rounded-full"></div>
    </div>

    {{-- Template Grid --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        @php
        $templates = [
            ['name' => 'Real Estate',           'icon' => '🏠', 'fields' => 8, 'stages' => 6, 'color' => '#7B61FF'],
            ['name' => 'Insurance',             'icon' => '🛡️', 'fields' => 7, 'stages' => 5, 'color' => '#3B82F6'],
            ['name' => 'Healthcare',            'icon' => '🏥', 'fields' => 9, 'stages' => 5, 'color' => '#10B981'],
            ['name' => 'Financial Services',    'icon' => '💰', 'fields' => 8, 'stages' => 6, 'color' => '#F59E0B'],
            ['name' => 'Legal Services',        'icon' => '⚖️', 'fields' => 7, 'stages' => 5, 'color' => '#8B5CF6'],
            ['name' => 'Automotive',            'icon' => '🚗', 'fields' => 7, 'stages' => 5, 'color' => '#EF4444'],
            ['name' => 'Technology / SaaS',     'icon' => '💻', 'fields' => 8, 'stages' => 7, 'color' => '#06B6D4'],
            ['name' => 'Education',             'icon' => '🎓', 'fields' => 7, 'stages' => 5, 'color' => '#F97316'],
            ['name' => 'E-commerce / Retail',   'icon' => '🛍️', 'fields' => 6, 'stages' => 4, 'color' => '#EC4899'],
            ['name' => 'Travel & Hospitality',  'icon' => '✈️', 'fields' => 8, 'stages' => 5, 'color' => '#14B8A6'],
            ['name' => 'Food & Beverage',       'icon' => '🍽️', 'fields' => 6, 'stages' => 4, 'color' => '#84CC16'],
            ['name' => 'Beauty & Wellness',     'icon' => '💅', 'fields' => 6, 'stages' => 4, 'color' => '#F472B6'],
            ['name' => 'Events & Entertainment','icon' => '🎉', 'fields' => 7, 'stages' => 5, 'color' => '#A855F7'],
            ['name' => 'Logistics & Transport', 'icon' => '🚛', 'fields' => 7, 'stages' => 5, 'color' => '#6366F1'],
            ['name' => 'Construction',          'icon' => '🏗️', 'fields' => 8, 'stages' => 6, 'color' => '#D97706'],
            ['name' => 'Non-Profit & NGO',      'icon' => '💚', 'fields' => 6, 'stages' => 4, 'color' => '#059669'],
            ['name' => 'Government / LGU',      'icon' => '🏛️', 'fields' => 10, 'stages' => 7, 'color' => '#2563EB'],
            ['name' => 'Media & Advertising',   'icon' => '📺', 'fields' => 7, 'stages' => 5, 'color' => '#7C3AED'],
            ['name' => 'Manufacturing',         'icon' => '🏭', 'fields' => 8, 'stages' => 6, 'color' => '#64748B'],
            ['name' => 'Agriculture',           'icon' => '🌾', 'fields' => 7, 'stages' => 5, 'color' => '#65A30D'],
            ['name' => 'Telecommunications',    'icon' => '📡', 'fields' => 7, 'stages' => 5, 'color' => '#0284C7'],
            ['name' => 'Pet Industry',          'icon' => '🐾', 'fields' => 6, 'stages' => 4, 'color' => '#EA580C'],
        ];
        @endphp

        @foreach($templates as $tmpl)
        <div class="card hover:shadow-md transition-shadow">
            <div class="flex items-start gap-3">
                <div class="w-11 h-11 rounded-xl flex items-center justify-center text-xl shrink-0"
                     style="background-color: {{ $tmpl['color'] }}20">
                    {{ $tmpl['icon'] }}
                </div>
                <div class="flex-1 min-w-0">
                    <h3 class="font-semibold text-[#1E1B4B] text-sm">{{ $tmpl['name'] }}</h3>
                    <div class="flex gap-3 mt-1.5">
                        <span class="text-xs text-gray-400">{{ $tmpl['fields'] }} fields</span>
                        <span class="text-xs text-gray-400">{{ $tmpl['stages'] }} stages</span>
                    </div>
                </div>
                <span class="badge badge-green text-xs">Active</span>
            </div>
            <div class="mt-4 pt-3 border-t border-gray-100 flex gap-2">
                <button class="flex-1 text-xs text-center py-1.5 rounded-lg bg-[#F0EFFA] text-purple-700 font-medium hover:bg-purple-100 transition-colors">
                    View Fields
                </button>
                <button class="flex-1 text-xs text-center py-1.5 rounded-lg bg-[#F0EFFA] text-purple-700 font-medium hover:bg-purple-100 transition-colors">
                    View Stages
                </button>
            </div>
        </div>
        @endforeach
    </div>

    <p class="text-center text-gray-400 text-sm">Template field and stage editing coming soon.</p>
</div>
@endsection
