<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $program->name }} — {{ $tenant->name }}</title>
    <meta name="description" content="{{ $program->short_description ?? $tenant->name . ' referral program' }}">
    {{-- No index for unlisted programs --}}
    @if($program->public_visibility === 'unlisted')
    <meta name="robots" content="noindex,nofollow">
    @endif
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full bg-gray-50 antialiased">

    <div class="max-w-2xl mx-auto px-4 py-16 space-y-8">

        {{-- Tenant branding --}}
        <div class="text-center">
            @if($tenant->logo_url)
            <img src="{{ $tenant->logo_url }}" alt="{{ $tenant->name }}" class="h-10 mx-auto mb-4 object-contain">
            @endif
            <p class="text-sm text-gray-500">{{ $tenant->name }}</p>
        </div>

        {{-- Program card --}}
        <div class="rounded-2xl border border-gray-200 bg-white shadow-md overflow-hidden">
            <div class="px-8 py-8 space-y-4">
                <h1 class="text-2xl font-bold text-gray-900">{{ $program->name }}</h1>

                @if($program->short_description)
                <p class="text-gray-600">{{ $program->short_description }}</p>
                @endif

                @if($program->full_description)
                <div class="prose prose-sm text-gray-600 max-w-none pt-2 border-t border-gray-100">
                    {!! nl2br(e($program->full_description)) !!}
                </div>
                @endif

                {{-- Status messaging --}}
                @if($program->status === 'active')
                    @if($program->application_mode !== 'invite_only')
                    <div class="pt-4">
                        <p class="text-sm text-gray-500 mb-3">{{ $program->public_cta_text ?: "Interested in joining? Contact {$tenant->name} to learn more." }}</p>
                    </div>
                    @endif
                @elseif($program->status === 'scheduled')
                <div class="rounded-lg bg-blue-50 border border-blue-200 px-4 py-3 text-sm text-blue-800">
                    This program hasn't launched yet.
                    @if($program->starts_at) It opens on {{ $program->starts_at->format('F j, Y') }}.@endif
                </div>
                @elseif($program->status === 'paused')
                <div class="rounded-lg bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-800">
                    This program is currently paused. New applications are not being accepted.
                </div>
                @elseif($program->status === 'ended')
                <div class="rounded-lg bg-gray-100 border border-gray-200 px-4 py-3 text-sm text-gray-600">
                    This program has ended.
                </div>
                @endif
            </div>
        </div>

        <p class="text-center text-xs text-gray-400">
            Powered by <a href="https://referralbunny.ai" class="hover:underline">referralbunny.ai</a>
        </p>
    </div>

</body>
</html>
