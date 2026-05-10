@extends('layouts.reseller')
@section('title', 'My Commission')
@section('nav') @include('reseller._nav') @endsection

@section('content')
<div class="space-y-5">

    {{-- Commission Summary Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        @foreach([['Pending','pending','#EDE9FE','#7B61FF'],['Locked','locked','#FEF3C7','#D97706'],['Paid','paid','#D1FAE5','#10B981']] as [$label,$key,$bg,$color])
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-medium text-gray-500">{{ $label }}</span>
                <span class="w-2.5 h-2.5 rounded-full" style="background:{{ $color }}"></span>
            </div>
            <p class="text-2xl font-bold" style="color:#1E1B4B">
                ₱{{ number_format($commissionStats[$key] ?? 0) }}
            </p>
            <p class="text-xs text-gray-400 mt-1">{{ $leads->where('commission_status', $key)->count() }} deal(s) · your share</p>
        </div>
        @endforeach
    </div>

    {{-- Deal Commission List --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100">
        <div class="px-5 py-3.5 border-b border-gray-100">
            <p class="text-sm font-semibold" style="color:#1E1B4B">Commission Breakdown</p>
        </div>
        @if($leads->isEmpty())
        <div class="flex flex-col items-center justify-center py-12 text-center">
            <img src="/images/mascots/r-bunny-sleeping.webp" alt="" class="w-12 h-12 object-contain mb-3 opacity-50">
            <p class="text-sm text-gray-400">No commission records yet.</p>
        </div>
        @else
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead><tr class="table-head">
                    <th>Deal</th><th>Contract Value</th><th>Your Commission</th><th>Status</th>
                </tr></thead>
                <tbody>
                @foreach($leads as $lead)
                @php
                    $aa  = (float) ($lead->added_amount ?? 0);
                    $bc  = (float) ($lead->base_cost ?? 0);
                    $cv  = ($bc + $aa) ?: (float) ($lead->deal_value ?? 0);
                @endphp
                <tr class="table-row">
                    <td>
                        <p class="font-medium text-sm" style="color:#1E1B4B">{{ $lead->name }}</p>
                        <p class="text-xs text-gray-400 capitalize">{{ str_replace('_', ' ', $lead->stage) }}</p>
                    </td>
                    <td class="text-sm font-semibold tabular-nums" style="color:#1E1B4B">₱{{ number_format((int)$cv) }}</td>
                    <td>
                        @if(($lead->my_commission ?? 0) > 0)
                            <p class="text-sm font-bold tabular-nums" style="color:#10B981">₱{{ number_format((int)($lead->my_commission ?? 0)) }}</p>
                            <p class="text-xs text-gray-400">{{ $lead->split_percentage ?? 100 }}% of commission pool</p>
                        @else
                            <p class="text-sm text-gray-400">—</p>
                            <p class="text-xs text-gray-400">Financial data not set</p>
                        @endif
                    </td>
                    <td>
                        <span class="text-xs px-2.5 py-1 rounded-full font-medium capitalize"
                              style="background:{{ $lead->commission_status === 'paid' ? '#D1FAE5' : ($lead->commission_status === 'locked' ? '#FEF3C7' : '#EDE9FE') }};color:{{ $lead->commission_status === 'paid' ? '#065F46' : ($lead->commission_status === 'locked' ? '#D97706' : '#7B61FF') }}">
                            {{ $lead->commission_status ?? 'pending' }}
                        </span>
                    </td>
                </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
</div>
@endsection
