<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>My Programs — Referral Bunny</title>@vite(['resources/css/app.css', 'resources/js/app.js'])</head>
<body class="bg-purple-50 text-gray-900 min-h-screen">
<header class="bg-white p-4 flex items-center justify-between"><a href="{{ route('public.home') }}" class="font-bold">Referral Bunny</a><x-portal-view-switch current="admin" /></header>
<main class="max-w-2xl mx-auto p-8"><h1 class="text-2xl font-bold mb-4">My Programs</h1><p class="mb-6">You don’t have a company workspace yet. Create one to start your own referral program.</p><a class="inline-block rounded-xl bg-purple-600 text-white px-4 py-3" href="{{ route('tenant.create') }}">Create a company</a></main>
</body></html>
