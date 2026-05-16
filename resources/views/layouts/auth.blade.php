<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Login' }} — Referral Bunny</title>
    @include('partials.google-analytics')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen font-sans antialiased flex items-center justify-center p-4"
      style="background: linear-gradient(135deg, #FF6CAB22 0%, #7B61FF22 50%, #67D2F822 100%); background-color: #F0EFFA;">

    <div class="w-full max-w-md">
        {{-- Logo --}}
        <div class="flex items-center justify-center gap-3 mb-8">
            <div class="w-12 h-12 rounded-2xl flex items-center justify-center text-2xl"
                 style="background: linear-gradient(135deg, #FF6CAB, #7B61FF)">
                🐰
            </div>
            <div>
                <p class="font-bold text-[#1E1B4B] text-xl leading-none">Referral Bunny</p>
                <p class="text-gray-400 text-sm mt-0.5">Platform Administration</p>
            </div>
        </div>

        <div class="card shadow-xl shadow-purple-100/50">
            @yield('content')
        </div>

        <p class="text-center text-xs text-gray-400 mt-6">
            © {{ date('Y') }} Referral Bunny. All rights reserved.
        </p>
    </div>
</body>
</html>
