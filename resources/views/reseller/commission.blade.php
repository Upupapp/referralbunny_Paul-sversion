@extends('layouts.reseller')
@section('title', 'My Commission')
@section('nav') @include('reseller._nav') @endsection

@section('content')
<div class="space-y-5">

    {{-- Commission Summary Cards --}}
    @php
    $commissionCards = [
        [
            'label' => 'Pending',
            'key'   => 'pending',
            'color' => '#7B61FF',
            'dot'   => '#EDE9FE',
            'info'  => 'Commission estimates from your active deals that have not yet reached the Signed stage. These amounts may change if deal values, stages, or split allocations are updated.',
        ],
        [
            'label' => 'Locked',
            'key'   => 'locked',
            'color' => '#D97706',
            'dot'   => '#FEF3C7',
            'info'  => 'Commission confirmed when a deal reached the Signed stage. This amount is locked and is pending final release or payout approval. It will not change unless the deal is revised.',
        ],
        [
            'label' => 'Paid',
            'key'   => 'paid',
            'color' => '#10B981',
            'dot'   => '#D1FAE5',
            'info'  => 'Commission that has been approved and released. These amounts are final and have been paid out.',
        ],
    ];
    @endphp
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        @foreach($commissionCards as $card)
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-1.5">
                    <span class="text-xs font-semibold" style="color:#1E1B4B">{{ $card['label'] }}</span>
                    {{-- Info icon --}}
                    <div x-data="{ open: false }" class="relative">
                        <button @click="open = !open" @click.outside="open = false" @keydown.escape.window="open = false"
                                class="w-4 h-4 flex items-center justify-center text-gray-300 hover:text-gray-500 transition-colors"
                                aria-label="About {{ $card['label'] }} commission">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </button>
                        <div x-show="open" x-cloak x-transition
                             class="absolute left-0 top-6 z-30 w-64 bg-white border border-gray-100 rounded-xl shadow-xl p-3 text-xs text-gray-500 leading-relaxed">
                            <strong class="block mb-1" style="color:#1E1B4B">{{ $card['label'] }} Commission</strong>
                            {{ $card['info'] }}
                        </div>
                    </div>
                </div>
                <span class="w-2.5 h-2.5 rounded-full" style="background:{{ $card['color'] }}"></span>
            </div>
            <div class="flex items-baseline gap-1 mt-1">
                <p class="text-2xl font-bold tabular-nums" style="color:#1E1B4B">
                    ₱{{ number_format($commissionStats[$card['key']] ?? 0) }}
                </p>
                <span class="text-[10px] text-gray-400 font-medium">*</span>
            </div>
            <p class="text-xs text-gray-400 mt-1">{{ $statusCounts[$card['key']] ?? 0 }} deal(s) · your share</p>
            <p class="text-[10px] text-gray-400 mt-1.5">* Subject to applicable taxes &amp; deductions</p>
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
                @foreach($pagedLeads as $lead)
                @php
                    $aa  = (float) ($lead->added_amount ?? 0);
                    $bc  = (float) ($lead->base_cost ?? 0);
                    $cv  = ($bc + $aa) ?: (float) ($lead->deal_value ?? 0);
                    // Terminal deal statuses override commission status display
                    $dealStatus = $lead->status ?? null;
                    $isTerminal = in_array($dealStatus, ['archived', 'declined', 'expired']);
                    $terminalStyles = match($dealStatus) {
                        'archived' => ['bg' => '#F3F4F6', 'text' => '#6B7280'],
                        'declined' => ['bg' => '#FEE2E2', 'text' => '#DC2626'],
                        'expired'  => ['bg' => '#FEF3C7', 'text' => '#D97706'],
                        default    => null,
                    };
                @endphp
                <tr class="table-row">
                    <td>
                        <a href="{{ route('reseller.deals.show', [$tenant->id, $lead->id]) }}"
                           class="font-medium text-sm hover:text-[#7B61FF] transition-colors" style="color:#1E1B4B">
                            {{ $lead->name }}
                        </a>
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
                        @if($isTerminal && $terminalStyles)
                            {{-- Deal is in a terminal state — show deal status, not commission status --}}
                            <span class="text-xs px-2.5 py-1 rounded-full font-medium capitalize"
                                  style="background:{{ $terminalStyles['bg'] }};color:{{ $terminalStyles['text'] }}">
                                {{ ucfirst($dealStatus) }}
                            </span>
                        @else
                            <span class="text-xs px-2.5 py-1 rounded-full font-medium capitalize"
                                  style="background:{{ $lead->commission_status === 'paid' ? '#D1FAE5' : ($lead->commission_status === 'locked' ? '#FEF3C7' : '#EDE9FE') }};color:{{ $lead->commission_status === 'paid' ? '#065F46' : ($lead->commission_status === 'locked' ? '#D97706' : '#7B61FF') }}">
                                {{ $lead->commission_status ?? 'pending' }}
                            </span>
                        @endif
                    </td>
                </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @if($pagedLeads->hasPages())
        <div class="px-5 py-3 border-t border-gray-100">{{ $pagedLeads->links() }}</div>
        @endif
        @endif
    </div>
</div>
@endsection
