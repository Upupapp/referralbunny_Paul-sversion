@extends('layouts.app')
@section('title', 'Commission Report')
@section('nav') @include('tenant._nav') @endsection

@section('content')
@php
    use App\Services\CommissionCalculationService;
    $calc = app(CommissionCalculationService::class);
    $fmt  = fn(float $v): string => '&#8369;' . number_format((int) round($v));
@endphp

<div class="space-y-5">

    {{-- Page header --}}
    <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px">
        <div>
            <h1 style="font-size:20px;font-weight:700;color:#1E1B4B">Commission Report</h1>
            <p style="font-size:13px;color:#9ca3af;margin-top:2px">All deals · {{ $tenant->name }}</p>
        </div>
        <a href="{{ route('tenant.commission.export', $tenant->id) }}{{ request()->getQueryString() ? '?' . request()->getQueryString() : '' }}"
           style="display:inline-flex;align-items:center;gap:6px;padding:8px 18px;border-radius:12px;background:#7B61FF;color:white;font-size:13px;font-weight:600;text-decoration:none;transition:opacity .15s"
           @mouseenter="this.style.opacity='0.85'" @mouseleave="this.style.opacity='1'">
            <svg style="width:14px;height:14px" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
            </svg>
            Export CSV
        </a>
    </div>

    {{-- Summary cards --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px">
        @foreach([
            ['label' => 'Total Commission Pool', 'value' => $summary['total_commission_pool'], 'color' => '#7B61FF', 'bg' => '#ede9fe'],
            ['label' => 'Paid Commission Pool',  'value' => $summary['paid_commission_pool'],  'color' => '#16a34a', 'bg' => '#dcfce7'],
            ['label' => 'Locked',                'value' => $summary['locked_commission_pool'],'color' => '#d97706', 'bg' => '#fef3c7'],
            ['label' => 'Pending / Draft',       'value' => $summary['pending_commission_pool'],'color'=>'#6b7280', 'bg' => '#f3f4f6'],
            ['label' => 'Total Company Share',   'value' => $summary['total_company_share'],   'color' => '#2563eb', 'bg' => '#dbeafe'],
            ['label' => 'Total Contract Value',  'value' => $summary['total_contract'],         'color' => '#1E1B4B', 'bg' => '#f0effa'],
        ] as $card)
        <div class="card" style="padding:16px">
            <p style="font-size:11px;font-weight:600;color:#9ca3af;letter-spacing:.05em;text-transform:uppercase;margin-bottom:6px">{{ $card['label'] }}</p>
            <p style="font-size:18px;font-weight:800;color:{{ $card['color'] }}">
                {!! $fmt($card['value']) !!}
            </p>
        </div>
        @endforeach
    </div>

    @if(($tenant->slug ?? '') === 'lgu-ids')
    {{-- LGU IDS: Pricing & Commission Scheme Reference ─────────────────────── --}}
    <div x-data="{ open: true }" class="card" style="padding:0;overflow:hidden">

        {{-- Toggle header --}}
        <button type="button" @click="open = !open"
                style="width:100%;display:flex;align-items:center;justify-content:space-between;padding:14px 20px;background:#fafafa;border:none;cursor:pointer;text-align:left;border-bottom:1px solid #f0effa">
            <div style="display:flex;align-items:center;gap:10px">
                <div style="width:32px;height:32px;border-radius:8px;background:#EDE9FE;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                    <svg style="width:16px;height:16px;color:#7B61FF" fill="none" stroke="#7B61FF" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 11h.01M12 11h.01M15 11h.01M4 19h16a2 2 0 002-2V7a2 2 0 00-2-2H4a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                </div>
                <div>
                    <p style="font-size:13px;font-weight:700;color:#1E1B4B;margin:0">LGU IDS — Pricing & Commission Scheme</p>
                    <p style="font-size:11px;color:#9ca3af;margin:2px 0 0">Base cost tiers · Added amount split · Referrer earnings formula</p>
                </div>
            </div>
            <svg :style="open ? 'transform:rotate(180deg)' : ''"
                 style="width:16px;height:16px;color:#9ca3af;flex-shrink:0;transition:transform .2s"
                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
        </button>

        <div x-show="open" x-collapse style="padding:20px;display:grid;grid-template-columns:1fr 1fr;gap:20px">

            {{-- ── Left: Pricing Tiers ───────────────────────────────────────── --}}
            <div>
                <p style="font-size:11px;font-weight:700;color:#7B61FF;text-transform:uppercase;letter-spacing:.07em;margin-bottom:10px">
                    Step 1 — Base Cost by Deal Amount
                </p>
                <div style="border:1.5px solid #ede9fe;border-radius:12px;overflow:hidden">
                    <table style="width:100%;border-collapse:collapse;font-size:13px">
                        <thead>
                            <tr style="background:#EDE9FE">
                                <th style="padding:8px 14px;text-align:left;font-size:11px;font-weight:700;color:#6d28d9;letter-spacing:.05em">Deal Amount Range</th>
                                <th style="padding:8px 14px;text-align:center;font-size:11px;font-weight:700;color:#6d28d9;letter-spacing:.05em">Base Cost %</th>
                                <th style="padding:8px 14px;text-align:right;font-size:11px;font-weight:700;color:#6d28d9;letter-spacing:.05em">Added Amount %</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach([
                                ['range' => '₱0 – ₱6,000,000',          'base' => '60%', 'added' => '40%', 'highlight' => false],
                                ['range' => '₱6,000,001 – ₱12,000,000', 'base' => '58%', 'added' => '42%', 'highlight' => false],
                                ['range' => '₱12,000,001 – ₱15,000,000','base' => '48%', 'added' => '52%', 'highlight' => false],
                                ['range' => '₱15,000,001 and above',     'base' => '41%', 'added' => '59%', 'highlight' => true],
                            ] as $i => $tier)
                            <tr style="border-top:1px solid #f0effa;{{ $tier['highlight'] ? 'background:#fdf8ff' : '' }}">
                                <td style="padding:9px 14px;color:#1E1B4B;font-weight:{{ $i === 0 ? 400 : 400 }}">
                                    {{ $tier['range'] }}
                                </td>
                                <td style="padding:9px 14px;text-align:center">
                                    <span style="display:inline-block;padding:2px 10px;border-radius:20px;background:#fee2e2;color:#991b1b;font-weight:700;font-size:12px">
                                        {{ $tier['base'] }}
                                    </span>
                                </td>
                                <td style="padding:9px 14px;text-align:right">
                                    <span style="display:inline-block;padding:2px 10px;border-radius:20px;background:#dcfce7;color:#15803d;font-weight:700;font-size:12px">
                                        {{ $tier['added'] }}
                                    </span>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p style="font-size:11px;color:#9ca3af;margin-top:8px">
                    <strong style="color:#1E1B4B">Added Amount</strong> = Deal Amount − Base Cost
                </p>
            </div>

            {{-- ── Right: Commission Split + Formula ────────────────────────── --}}
            <div>
                <p style="font-size:11px;font-weight:700;color:#7B61FF;text-transform:uppercase;letter-spacing:.07em;margin-bottom:10px">
                    Step 2 — Commission Split of Added Amount
                </p>

                {{-- Split bar visual --}}
                <div style="border:1.5px solid #ede9fe;border-radius:12px;overflow:hidden;margin-bottom:12px">
                    <div style="display:flex;height:36px">
                        <div style="flex:30;background:#dbeafe;display:flex;align-items:center;justify-content:center">
                            <span style="font-size:12px;font-weight:700;color:#1d4ed8">Company 30%</span>
                        </div>
                        <div style="width:1px;background:#e5e7eb"></div>
                        <div style="flex:70;background:#dcfce7;display:flex;align-items:center;justify-content:center">
                            <span style="font-size:12px;font-weight:700;color:#15803d">Referrer Pool 70%</span>
                        </div>
                    </div>
                </div>

                {{-- Formula rows --}}
                <div style="border:1.5px solid #ede9fe;border-radius:12px;overflow:hidden">
                    <table style="width:100%;border-collapse:collapse;font-size:13px">
                        <thead>
                            <tr style="background:#EDE9FE">
                                <th style="padding:8px 14px;text-align:left;font-size:11px;font-weight:700;color:#6d28d9;letter-spacing:.05em">Component</th>
                                <th style="padding:8px 14px;text-align:left;font-size:11px;font-weight:700;color:#6d28d9;letter-spacing:.05em">Formula</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr style="border-top:1px solid #f0effa">
                                <td style="padding:9px 14px;color:#9ca3af;font-size:12px">Base Cost</td>
                                <td style="padding:9px 14px;color:#1E1B4B;font-family:monospace;font-size:12px">Deal Amount × Tier %</td>
                            </tr>
                            <tr style="border-top:1px solid #f0effa;background:#fdf8ff">
                                <td style="padding:9px 14px;font-weight:700;color:#1E1B4B;font-size:12px">Added Amount</td>
                                <td style="padding:9px 14px;color:#1E1B4B;font-family:monospace;font-size:12px">Deal Amount − Base Cost</td>
                            </tr>
                            <tr style="border-top:1px solid #f0effa">
                                <td style="padding:9px 14px;color:#2563eb;font-weight:600;font-size:12px">Company Share</td>
                                <td style="padding:9px 14px;color:#2563eb;font-family:monospace;font-size:12px">Added Amount × 30%</td>
                            </tr>
                            <tr style="border-top:1px solid #f0effa">
                                <td style="padding:9px 14px;color:#15803d;font-weight:600;font-size:12px">Referrer Pool</td>
                                <td style="padding:9px 14px;color:#15803d;font-family:monospace;font-size:12px">Added Amount × 70%</td>
                            </tr>
                            <tr style="border-top:1px solid #f0effa;background:#f9fafb">
                                <td style="padding:9px 14px;color:#6b7280;font-size:12px">Referrer Earning</td>
                                <td style="padding:9px 14px;color:#6b7280;font-family:monospace;font-size:12px">Pool × Referrer Split %</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                {{-- Example --}}
                <div style="margin-top:12px;padding:12px 14px;background:#f0fdf4;border:1.5px solid #bbf7d0;border-radius:10px">
                    <p style="font-size:11px;font-weight:700;color:#15803d;margin-bottom:6px;text-transform:uppercase;letter-spacing:.05em">Example — ₱8,000,000 deal</p>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:4px;font-size:12px;color:#374151">
                        <span>Base Cost (58%)</span>      <span style="text-align:right;font-weight:600">₱4,640,000</span>
                        <span>Added Amount (42%)</span>   <span style="text-align:right;font-weight:700;color:#15803d">₱3,360,000</span>
                        <span style="color:#2563eb">Company (30%)</span> <span style="text-align:right;font-weight:600;color:#2563eb">₱1,008,000</span>
                        <span style="color:#15803d">Referrer Pool (70%)</span> <span style="text-align:right;font-weight:700;color:#15803d">₱2,352,000</span>
                    </div>
                </div>
            </div>

        </div>
    </div>
    @endif

    {{-- Filters --}}
    <form method="GET" action="{{ route('tenant.commission', $tenant->id) }}"
          style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
        <div>
            <label style="font-size:11px;font-weight:600;color:#9ca3af;display:block;margin-bottom:4px">Commission Status</label>
            <select name="status"
                    style="padding:8px 12px;border:1.5px solid #e5e7eb;border-radius:10px;font-size:13px;color:#1E1B4B;background:white;cursor:pointer">
                <option value="">All</option>
                <option value="pending" {{ $filterStatus === 'pending' ? 'selected' : '' }}>Pending</option>
                <option value="locked"  {{ $filterStatus === 'locked'  ? 'selected' : '' }}>Locked</option>
                <option value="paid"    {{ $filterStatus === 'paid'    ? 'selected' : '' }}>Paid</option>
            </select>
        </div>
        <div>
            <label style="font-size:11px;font-weight:600;color:#9ca3af;display:block;margin-bottom:4px">Referrer</label>
            <input type="text" name="referrer" value="{{ $filterReferrer }}"
                   placeholder="Search referrer..."
                   style="padding:8px 12px;border:1.5px solid #e5e7eb;border-radius:10px;font-size:13px;color:#1E1B4B;background:white;width:180px">
        </div>
        <div>
            <label style="font-size:11px;font-weight:600;color:#9ca3af;display:block;margin-bottom:4px">From</label>
            <input type="date" name="date_from" value="{{ $filterDate }}"
                   style="padding:8px 12px;border:1.5px solid #e5e7eb;border-radius:10px;font-size:13px;color:#1E1B4B;background:white">
        </div>
        <div>
            <label style="font-size:11px;font-weight:600;color:#9ca3af;display:block;margin-bottom:4px">To</label>
            <input type="date" name="date_to" value="{{ $filterDateTo }}"
                   style="padding:8px 12px;border:1.5px solid #e5e7eb;border-radius:10px;font-size:13px;color:#1E1B4B;background:white">
        </div>
        <button type="submit"
                style="padding:9px 20px;border-radius:10px;background:#1E1B4B;color:white;border:none;font-size:13px;font-weight:600;cursor:pointer">
            Filter
        </button>
        @if($filterStatus || $filterReferrer || $filterDate || $filterDateTo)
        <a href="{{ route('tenant.commission', $tenant->id) }}"
           style="padding:9px 16px;border-radius:10px;border:1.5px solid #e5e7eb;background:white;color:#6b7280;font-size:13px;font-weight:600;text-decoration:none">
            Clear
        </a>
        @endif
    </form>

    {{-- Deals table --}}
    <div class="card" style="padding:0;overflow:hidden">

        {{-- Table header --}}
        <div style="display:grid;grid-template-columns:2fr 1fr 1fr 1fr 1fr 1fr 1fr;gap:0;padding:10px 16px;border-bottom:1px solid #f3f4f6;background:#f9fafb">
            <p style="font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.05em">Deal</p>
            <p style="font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.05em;text-align:right">Contract Value</p>
            <p style="font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.05em;text-align:right">Added Amount</p>
            <p style="font-size:11px;font-weight:700;color:#2563eb;text-transform:uppercase;letter-spacing:.05em;text-align:right">Company (30%)</p>
            <p style="font-size:11px;font-weight:700;color:#16a34a;text-transform:uppercase;letter-spacing:.05em;text-align:right">Pool (70%)</p>
            <p style="font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.05em;text-align:right">Referrer</p>
            <p style="font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.05em;text-align:center">Status</p>
        </div>

        {{-- Rows --}}
        @forelse($enriched as $deal)
        @php
            $cs = $splits[$deal['id']] ?? collect();
            $ps = $partnerSplits[$deal['id']] ?? collect();
            $statusStyles = match($deal['commission_status'] ?? 'pending') {
                'paid'   => 'background:#dcfce7;color:#15803d',
                'locked' => 'background:#fef3c7;color:#d97706',
                default  => 'background:#ede9fe;color:#7B61FF',
            };
        @endphp
        <div style="display:grid;grid-template-columns:2fr 1fr 1fr 1fr 1fr 1fr 1fr;gap:0;padding:12px 16px;border-bottom:1px solid #f9fafb;transition:background .1s"
             @mouseenter="this.style.background='#fafafa'" @mouseleave="this.style.background='white'">

            {{-- Deal name + referrer + stage --}}
            <div style="min-width:0">
                <a href="{{ route('tenant.deals', $tenant->id) }}/{{ $deal['id'] }}"
                   style="font-size:13px;font-weight:600;color:#1E1B4B;text-decoration:none;display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"
                   @mouseenter="this.style.color='#7B61FF'" @mouseleave="this.style.color='#1E1B4B'">
                    {{ $deal['name'] }}
                </a>
                <p style="font-size:11px;color:#9ca3af;margin-top:2px">
                    {{ $deal['reseller_name'] ?? 'No referrer' }}
                    · {{ ucwords(str_replace('_', ' ', $deal['stage'] ?? '—')) }}
                </p>
            </div>

            {{-- Contract value --}}
            <p style="font-size:13px;font-weight:600;color:#1E1B4B;text-align:right;font-variant-numeric:tabular-nums">
                {!! $fmt($deal['computed_contract_value']) !!}
            </p>

            {{-- Added amount --}}
            <p style="font-size:13px;font-weight:600;color:#2563eb;text-align:right;font-variant-numeric:tabular-nums">
                {!! $fmt($deal['computed_added_amount']) !!}
            </p>

            {{-- Company share --}}
            <p style="font-size:13px;font-weight:600;color:#2563eb;text-align:right;font-variant-numeric:tabular-nums">
                {!! $fmt($deal['computed_company_share']) !!}
            </p>

            {{-- Commission pool --}}
            <p style="font-size:13px;font-weight:700;color:#16a34a;text-align:right;font-variant-numeric:tabular-nums">
                {!! $fmt($deal['computed_commission_pool']) !!}
            </p>

            {{-- Referrer commission --}}
            <div style="text-align:right">
                @if($cs->count())
                @foreach($cs as $split)
                <p style="font-size:12px;color:#1E1B4B;font-variant-numeric:tabular-nums">
                    {!! $fmt($calc->referrerShare($deal['computed_commission_pool'], (float)$split->percentage)) !!}
                    <span style="color:#9ca3af;font-size:10px">({{ number_format($split->percentage, 0) }}%)</span>
                </p>
                @endforeach
                @else
                <p style="font-size:11px;color:#d97706">Unassigned</p>
                @endif
                @if($ps->count())
                <p style="font-size:10px;color:#9ca3af;margin-top:2px">+ {{ $ps->count() }} partner split(s)</p>
                @endif
            </div>

            {{-- Commission status --}}
            <div style="display:flex;justify-content:center;align-items:center">
                <span style="font-size:10px;font-weight:700;padding:2px 10px;border-radius:9999px;{{ $statusStyles }}">
                    {{ ucfirst($deal['commission_status'] ?? 'pending') }}
                </span>
            </div>
        </div>
        @empty
        <div style="text-align:center;padding:40px;color:#9ca3af;font-size:14px">
            No deals match the current filters.
        </div>
        @endforelse
    </div>

    {{-- Pagination --}}
    <div>{{ $deals->links() }}</div>

    {{-- Formula reminder --}}
    <div style="padding:14px 16px;background:#f5f3ff;border:1px solid #ede9fe;border-radius:14px;font-size:12px;color:#7B61FF">
        <span style="font-weight:700">Commission Formula: </span>
        Deal Value = Base Cost + Added Amount &nbsp;·&nbsp;
        Company Share = 30% of Added Amount &nbsp;·&nbsp;
        Commission Pool = 70% of Added Amount
    </div>

</div>
@endsection
