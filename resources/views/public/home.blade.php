@extends('layouts.public')

@section('stitch_page', 'public-landing')

@section('content')
    @include('public.sections.header')

    <main id="main-content">
        @include('public.sections.hero')
        @include('public.sections.problem')
        @include('public.sections.solution')
        @include('public.sections.how-it-works')
        @include('public.sections.product-preview')
        @include('public.sections.benefits')
        @include('public.sections.features')
        @include('public.sections.use-cases')
        @include('public.sections.r-bunny-helper')
        @include('public.sections.automations')
        @include('public.sections.security')
        @include('public.sections.setup-wizard')
        @include('public.sections.product-proof')
        @include('public.sections.pricing-preview')
        @include('public.sections.faq')
        @include('public.sections.final-cta')
    </main>

    @include('public.sections.footer')

    <x-public.failure-modal />
@endsection
