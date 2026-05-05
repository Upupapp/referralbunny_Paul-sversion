@extends('layouts.reseller')
@section('title', 'Profile')
@section('nav') @include('reseller._nav') @endsection

@section('content')
<div class="max-w-xl space-y-5">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
        <div class="flex items-center gap-4 mb-6">
            <div class="w-14 h-14 rounded-2xl flex items-center justify-center text-white text-lg font-bold shrink-0"
                 style="background: linear-gradient(135deg, #14B8A6, #0D9488)">
                {{ strtoupper(collect(explode(' ', $reseller->name ?? 'R'))->map(fn($w) => $w[0] ?? '')->take(2)->implode('')) }}
            </div>
            <div>
                <h2 class="text-lg font-bold" style="color:#1E1B4B">{{ $reseller->name }}</h2>
                <p class="text-sm text-gray-400">{{ $reseller->email }}</p>
                <span class="inline-block mt-1 text-xs px-2.5 py-0.5 rounded-full font-medium"
                      style="background:#D1FAE5;color:#065F46">{{ ucfirst($reseller->status) }}</span>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4 pt-4 border-t border-gray-100">
            @foreach([['Phone', $reseller->phone ?? '—'],['Territory', $reseller->territory ?? '—'],['Joined', $reseller->joined_date?->format('M j, Y') ?? '—'],['Program', $tenant->name]] as [$label, $value])
            <div>
                <p class="text-xs text-gray-400 font-medium uppercase tracking-wide mb-0.5">{{ $label }}</p>
                <p class="text-sm font-semibold" style="color:#1E1B4B">{{ $value }}</p>
            </div>
            @endforeach
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
        <h3 class="text-sm font-semibold mb-4" style="color:#1E1B4B">Performance</h3>
        <div class="grid grid-cols-3 gap-4">
            @foreach([['Deals', $reseller->assigned_leads ?? 0],['Closed (₱)', number_format($reseller->closed_value ?? 0)],['Score', ($reseller->performance_score ?? 0).'%']] as [$label, $val])
            <div class="text-center p-3 rounded-xl" style="background:#F0FDFA">
                <p class="text-xl font-bold" style="color:#0D9488">{{ $val }}</p>
                <p class="text-xs text-gray-500 mt-0.5">{{ $label }}</p>
            </div>
            @endforeach
        </div>
    </div>
</div>
@endsection
