@extends('layouts.reseller')
@section('title','Referral export')
@section('nav') @include('reseller._nav') @endsection
@section('content')
<div class="bg-white border rounded-xl p-4"><h1 class="text-xl font-bold">Referral export</h1>
<p class="my-3">{{ session('success') }} Status: {{ str_replace('_',' ',$export->status) }}.</p>
<p>Your selected account cohort and filters are saved with this request. Approval and download expiry follow your company’s export policy.</p>
<div class="flex gap-4 mt-4"><a href="{{ request()->fullUrlWithQuery(['download'=>null]) }}">Refresh status</a>
@if($export->canBeDownloaded())<a href="{{ request()->fullUrlWithQuery(['download'=>1]) }}">Download CSV</a>@endif
<a href="{{ route('reseller.deals',['tenantId'=>$tenant->id]+$export->export_scope) }}">Back to My Referrals</a></div></div>
@endsection
