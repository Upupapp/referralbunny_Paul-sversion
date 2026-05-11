@extends('layouts.app')
@section('title', 'My Commission')
@section('nav') @include('tenant._nav') @endsection

@section('content')
@php
    $fmt = fn(float $v): string => '&#8369;' . number_format((int) round($v));
    $totalEarned = 0;
    $totalPaid   = 0;
    $totalPending = 0;
    foreach ($splits as $s) {
        $amount = $s->split_share_type === 'fixed_amount'
            ? (float) $s->split_share_value
            : 0; // percentage splits shown differently
        if ($s->commission_status === 'paid')   $totalPaid    += $amount;
        elseif ($amount > 0)                    $totalPending += $amount;
        $totalEarned += $amount;
    }
@endphp

<div class="space-y-5">

    {{-- Header --}}
    <div>
        <h1 class="text-xl font-bold text-[#1E1B4B]">My Commission</h1>
        <p class="text-gray-400 text-sm mt-0.5">Your earnings from deals you are a partner on in <strong>{{ $tenant->name }}</strong>.</p>
    </div>

    {{-- KPI Cards --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
        <div class="kpi-card">
            <div class="flex-1 min-w-0">
                <span class="text-gray-400 text-xs font-medium uppercase tracking-wide">Total Deals</span>
                <p class="text-2xl font-bold text-[#1E1B4B] mt-1.5">{{ $splits->total() }}</p>
            </div>
            <div class="kpi-icon bg-purple-100 ml-3 shrink-0">
                <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
            </div>
        </div>
        <div class="kpi-card">
            <div class="flex-1 min-w-0">
                <span class="text-gray-400 text-xs font-medium uppercase tracking-wide">Paid</span>
                <p class="text-2xl font-bold text-emerald-600 mt-1.5">
                    {{ $splits->where('commission_status', 'paid')->count() }}
                    <span class="text-sm font-medium">deals</span>
                </p>
            </div>
            <div class="kpi-icon bg-emerald-100 ml-3 shrink-0">
                <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
        </div>
        <div class="kpi-card col-span-2 sm:col-span-1">
            <div class="flex-1 min-w-0">
                <span class="text-gray-400 text-xs font-medium uppercase tracking-wide">Pending</span>
                <p class="text-2xl font-bold text-orange-500 mt-1.5">
                    {{ $splits->whereNotIn('commission_status', ['paid'])->count() }}
                    <span class="text-sm font-medium">deals</span>
                </p>
            </div>
            <div class="kpi-icon bg-orange-100 ml-3 shrink-0">
                <svg class="w-5 h-5 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
        </div>
    </div>

    {{-- Note about percentage splits --}}
    @if($splits->where('split_share_type', 'percentage')->count() > 0)
    <div class="flex items-start gap-3 p-4 rounded-xl bg-blue-50 border border-blue-100 text-sm text-blue-800">
        <svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <p>Some of your commission splits are percentage-based. The exact amount depends on the deal's commission pool, which is confirmed by your administrator when deals are locked and paid.</p>
    </div>
    @endif

    {{-- Deals table --}}
    <div class="card p-0 overflow-hidden">
        <div class="px-5 py-3.5 border-b border-gray-100 flex items-center justify-between">
            <p class="text-sm font-semibold text-[#1E1B4B]">{{ $splits->total() }} deal{{ $splits->total() === 1 ? '' : 's' }}</p>
        </div>

        @if($splits->isEmpty())
        <div class="text-center py-16">
            <div class="flex justify-center text-gray-200 mb-3">
                <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <p class="text-gray-400 text-sm">No commission splits found for your account.</p>
            <p class="text-gray-300 text-xs mt-1">Contact your administrator if you believe this is incorrect.</p>
        </div>
        @else
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="table-head">
                        <th>Deal</th>
                        <th class="hidden sm:table-cell">Stage</th>
                        <th class="hidden md:table-cell text-right">Deal Value</th>
                        <th class="text-right">Your Split</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($splits as $split)
                    @php
                        $statusStyle = match($split->commission_status ?? 'pending') {
                            'paid'    => 'badge badge-green',
                            'locked'  => 'badge badge-orange',
                            default   => 'badge badge-gray',
                        };
                        $statusLabel = match($split->commission_status ?? 'pending') {
                            'paid'    => 'Paid',
                            'locked'  => 'Locked',
                            default   => 'Pending',
                        };
                        $splitDisplay = $split->split_share_type === 'fixed_amount'
                            ? '₱' . number_format((float) $split->split_share_value, 2)
                            : number_format((float) $split->split_share_value, 4) . '%';
                    @endphp
                    <tr class="table-row">
                        <td>
                            <p class="font-medium text-[#1E1B4B]">{{ $split->deal_name }}</p>
                            <p class="text-xs text-gray-400 mt-0.5">
                                {{ $split->created_at ? \Carbon\Carbon::parse($split->created_at)->format('M j, Y') : '—' }}
                            </p>
                        </td>
                        <td class="hidden sm:table-cell">
                            <span class="badge badge-gray text-xs capitalize">{{ str_replace('_', ' ', $split->stage ?? '—') }}</span>
                        </td>
                        <td class="hidden md:table-cell text-right tabular-nums text-[#1E1B4B] font-medium">
                            ₱{{ number_format((float) $split->deal_value, 0) }}
                        </td>
                        <td class="text-right">
                            <span class="font-bold text-[#7B61FF] tabular-nums">{{ $splitDisplay }}</span>
                            @if($split->split_share_type === 'percentage')
                            <p class="text-[10px] text-gray-400 mt-0.5">of commission pool</p>
                            @endif
                        </td>
                        <td class="text-center">
                            <span class="{{ $statusStyle }}">{{ $statusLabel }}</span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($splits->hasPages())
        <div class="px-5 py-4 border-t border-gray-100">
            {{ $splits->links() }}
        </div>
        @endif
        @endif
    </div>

</div>
@endsection
