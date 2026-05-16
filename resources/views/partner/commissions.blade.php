@extends('layouts.partner')
@section('title', 'My Commissions')
@section('nav') @include('partner._nav') @endsection

@section('content')
<div class="space-y-5 max-w-4xl">

    <div>
        <h1 class="text-xl font-bold text-[#1E1B4B]">My Commissions</h1>
        <p class="text-sm text-gray-400 mt-0.5">Your estimated commission across all deals you are associated with.</p>
    </div>

    {{-- Summary cards --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 text-center">
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Total Estimated</p>
            <p class="text-xl font-extrabold text-[#1E1B4B]">₱{{ number_format($totalAll, 0) }}</p>
        </div>
        <div class="bg-white rounded-2xl border border-violet-100 shadow-sm p-4 text-center">
            <p class="text-[10px] font-bold text-violet-400 uppercase tracking-widest mb-1">Provisional</p>
            <p class="text-xl font-extrabold text-violet-600">₱{{ number_format($totalProvisional, 0) }}</p>
        </div>
        <div class="bg-white rounded-2xl border border-amber-100 shadow-sm p-4 text-center">
            <p class="text-[10px] font-bold text-amber-500 uppercase tracking-widest mb-1">Locked</p>
            <p class="text-xl font-extrabold text-amber-600">₱{{ number_format($totalLocked, 0) }}</p>
        </div>
        <div class="bg-white rounded-2xl border border-emerald-100 shadow-sm p-4 text-center">
            <p class="text-[10px] font-bold text-emerald-500 uppercase tracking-widest mb-1">Paid</p>
            <p class="text-xl font-extrabold text-emerald-600">₱{{ number_format($totalPaid, 0) }}</p>
        </div>
    </div>

    {{-- Commission breakdown per deal --}}
    @if($commissions->isEmpty())
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-10 text-center">
        <div class="w-14 h-14 rounded-2xl bg-gray-50 flex items-center justify-center mx-auto mb-3">
            <svg class="w-7 h-7 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        </div>
        <p class="text-sm font-semibold text-[#1E1B4B] mb-1">No commissions yet</p>
        <p class="text-xs text-gray-400">You'll see your deal commissions here once you've been added to a partner split.</p>
    </div>
    @else
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100">
            <p class="text-sm font-semibold text-[#1E1B4B]">{{ $commissions->count() }} deal{{ $commissions->count() !== 1 ? 's' : '' }}</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="table-head">
                        <th>Deal</th>
                        <th class="hidden sm:table-cell">Stage</th>
                        <th class="hidden md:table-cell">
                            <span class="inline-flex items-center gap-1">
                                Commission Basis
                                <span title="The pool your share is calculated from: Added Amount × 70%. This is not the full contract value."
                                      class="cursor-help text-gray-300 hover:text-gray-400">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                </span>
                            </span>
                        </th>
                        <th>My Share</th>
                        <th>Status</th>
                        <th>My Estimated Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($commissions as $c)
                    <tr class="table-row">
                        <td>
                            <a href="{{ route('partner.deals.show', $c['deal_id']) }}"
                               class="font-medium text-sm text-[#1E1B4B] hover:text-[#7B61FF] hover:underline transition-colors">
                                {{ $c['deal_name'] }}
                            </a>
                        </td>
                        <td class="hidden sm:table-cell">
                            @php
                            $stageBadge = match($c['deal_stage']) {
                                'introduction'  => 'badge badge-gray',
                                'presentation'  => 'badge badge-blue',
                                'contract_sent' => 'badge badge-orange',
                                'signed'        => 'bg-purple-100 text-purple-700 px-2 py-0.5 rounded-full text-xs font-semibold',
                                'paid'          => 'badge badge-green',
                                default         => 'badge badge-gray',
                            };
                            $stageLabel = ucwords(str_replace('_', ' ', $c['deal_stage']));
                            @endphp
                            <span class="{{ $stageBadge }}">{{ $stageLabel }}</span>
                        </td>
                        <td class="hidden md:table-cell text-sm text-gray-700 tabular-nums">
                            @if($c['pool'] > 0)
                                ₱{{ number_format($c['pool'], 0) }}
                                <span class="text-[10px] text-gray-400 block">of ₱{{ number_format($c['deal_value'], 0) }} deal</span>
                            @else
                                <span class="text-gray-300">—</span>
                            @endif
                        </td>
                        <td class="text-sm text-[#7B61FF] font-semibold tabular-nums">
                            @if($c['split_type'] === 'percentage')
                                {{ $c['split_value'] }}%
                            @else
                                ₱{{ number_format($c['split_value'], 0) }} fixed
                            @endif
                        </td>
                        <td>
                            @php
                            $cs = $c['commission_status'];
                            $csClass = match($cs) {
                                'paid'   => 'badge badge-green',
                                'locked' => 'bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full text-xs font-semibold',
                                default  => 'bg-violet-100 text-violet-700 px-2 py-0.5 rounded-full text-xs font-semibold',
                            };
                            @endphp
                            <span class="{{ $csClass }}">{{ ucfirst($cs) }}</span>
                        </td>
                        <td class="text-sm font-bold tabular-nums"
                            style="color:{{ $c['commission_status'] === 'paid' ? '#16a34a' : ($c['commission_status'] === 'locked' ? '#d97706' : '#7B61FF') }}">
                            ₱{{ number_format($c['my_amount'], 0) }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <p class="text-xs text-gray-400">
        Estimated amounts are subject to applicable taxes, deductions, and final confirmation by the deal administrator.
    </p>
    @endif

</div>
@endsection
