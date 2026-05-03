@extends('layouts.auth')
@section('title', 'Login')

@section('content')
<div class="p-6">
    <h2 class="text-xl font-bold text-[#1E1B4B] mb-1">Welcome back</h2>
    <p class="text-gray-400 text-sm mb-6">Sign in to your admin account</p>

    @if($errors->any())
        <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-xl text-red-600 text-sm">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf

        <div>
            <label class="form-label">Email Address</label>
            <input type="email" name="email" value="{{ old('email') }}" required autofocus
                   class="form-input" placeholder="admin@referralbunny.com">
        </div>

        <div>
            <label class="form-label">Password</label>
            <input type="password" name="password" required
                   class="form-input" placeholder="••••••••">
        </div>

        <div class="flex items-center justify-between text-sm">
            <label class="flex items-center gap-2 text-gray-600 cursor-pointer">
                <input type="checkbox" name="remember" class="rounded accent-purple-600">
                Remember me
            </label>
        </div>

        <button type="submit"
                class="w-full py-2.5 rounded-xl text-white font-medium text-sm transition-all hover:opacity-90 active:scale-[0.98]"
                style="background: linear-gradient(135deg, #7B61FF, #FF6CAB)">
            Sign In
        </button>
    </form>
</div>
@endsection
