@extends('layouts.app')
@section('title', $tenant->name . ' — Dashboard')
@section('nav')
    @include('tenant._nav', ['tenant' => $tenant])
@endsection
@section('content')
<style>
.rb-selected-dashboard{max-width:1280px;margin:auto;padding:4px 0 24px}.rb-selected-dashboard .rb-selected-header{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:20px;padding:26px;border:1px solid #e9e1f3;border-radius:20px;background:linear-gradient(115deg,#fff 55%,#faf4ff);margin-bottom:24px}.rb-selected-dashboard .rb-eyebrow{font-size:11px;font-weight:700;letter-spacing:1.3px;text-transform:uppercase;color:#9740db;margin-bottom:9px}.rb-selected-dashboard h1{font-size:25px;font-weight:700;color:#2b204b;line-height:1.3}.rb-selected-dashboard .rb-context{font-size:13px;color:#887b96;margin-top:10px;line-height:1.6}.rb-selected-dashboard .rb-settings{padding:11px 16px;border:1px solid #e4d2f4;border-radius:11px;color:#872bc9;background:#faf5ff;font-size:13px;font-weight:600;text-decoration:none}.rb-selected-dashboard .rb-settings:hover{background:#f0e4ff}.rb-selected-dashboard .rb-settings:focus-visible{outline:3px solid #c595ed;outline-offset:3px}
</style>
<div class="rb-selected-dashboard">
    <header class="rb-selected-header">
        <div>
            <p class="rb-eyebrow">Program dashboard</p>
            <h1>{{ $program->name }}</h1>
            <p class="rb-context">{{ ucfirst($performance['mode']) }} · {{ ucfirst($program->status) }} · All-time activity<br>Use the program menu above to switch dashboards.</p>
        </div>
        <a class="rb-settings" href="{{ route('tenant.programs.workspace', [$tenant->id, $program->id]) }}">Manage program →</a>
    </header>
    @include('tenant.programs._performance')
</div>
@endsection
