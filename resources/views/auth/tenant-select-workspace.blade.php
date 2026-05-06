<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Choose Your Workspace — ReferralBunny.ai</title>
    <link rel="icon" type="image/webp" href="/images/logos/referralbunny-favicon.webp">
    <link rel="apple-touch-icon" href="/images/logos/referralbunny-app-icon.webp">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0; padding: 0;
            min-height: 100vh;
            font-family: 'Inter', sans-serif;
            background: linear-gradient(145deg, #1E1347 0%, #2D1B69 55%, #1a1040 100%);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .page-wrap {
            width: 100%;
            padding: 2rem 1.25rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            min-height: 100vh;
            justify-content: center;
        }

        .card {
            width: 100%;
            max-width: 480px;
            background: #fff;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 32px 80px rgba(0,0,0,.45);
        }

        .card-header {
            padding: 2rem 2rem 1.25rem;
            text-align: center;
            border-bottom: 1px solid #f3f4f6;
        }

        .card-body {
            padding: 1.25rem 2rem 2rem;
        }

        .workspace-card {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem 1.125rem;
            border: 1.5px solid #e5e7eb;
            border-radius: 14px;
            text-decoration: none;
            transition: border-color .15s, box-shadow .15s, background .15s;
            cursor: pointer;
            background: #fff;
            width: 100%;
            margin-bottom: .625rem;
        }
        .workspace-card:last-child { margin-bottom: 0; }
        .workspace-card:hover {
            border-color: #8b5cf6;
            box-shadow: 0 0 0 3px rgba(139,92,246,.1);
            background: #faf9ff;
        }

        .workspace-avatar {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: linear-gradient(135deg, #7c3aed, #6d28d9);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: .9375rem;
            font-weight: 700;
            color: #fff;
            letter-spacing: -.02em;
        }

        .workspace-name {
            font-size: .9375rem;
            font-weight: 600;
            color: #111827;
            text-align: left;
            line-height: 1.3;
        }
        .workspace-meta {
            font-size: .75rem;
            color: #9ca3af;
            margin-top: .1875rem;
            text-align: left;
        }

        .role-badge {
            display: inline-flex;
            align-items: center;
            padding: .1875rem .5625rem;
            border-radius: 20px;
            font-size: .6875rem;
            font-weight: 600;
            margin-left: auto;
            flex-shrink: 0;
            white-space: nowrap;
        }
        .badge-owner   { background: #fef3c7; color: #92400e; }
        .badge-admin   { background: #ede9fe; color: #6d28d9; }
        .badge-manager { background: #e0f2fe; color: #0369a1; }
        .badge-member  { background: #f0fdf4; color: #15803d; }
        .badge-viewer  { background: #f3f4f6; color: #6b7280; }

        .arrow-icon {
            color: #d1d5db;
            flex-shrink: 0;
            transition: color .15s, transform .15s;
        }
        .workspace-card:hover .arrow-icon {
            color: #7c3aed;
            transform: translateX(3px);
        }

        .logout-link {
            display: block;
            text-align: center;
            font-size: .75rem;
            color: #9ca3af;
            text-decoration: none;
            margin-top: 1.25rem;
            transition: color .15s;
        }
        .logout-link:hover { color: #7c3aed; }

        @media (max-width: 520px) {
            .card-header { padding: 1.5rem 1.5rem 1rem; }
            .card-body   { padding: 1rem 1.5rem 1.5rem; }
        }
    </style>
</head>
<body>

<div class="page-wrap">

    <div class="card">
        <div class="card-header">
            <div style="margin-bottom:1.125rem">
                <x-rb-logo variant="horizontal" size="sm" :priority="true" :decorative="true" />
            </div>

            {{-- R Bunny waving mascot --}}
            <div style="margin-bottom:1rem">
                <x-r-bunny variant="rocket" size="md" :decorative="true" style="display:inline-block" />
            </div>

            <h1 style="margin:0 0 .25rem;font-size:1.3125rem;font-weight:700;color:#111827">Choose Your Workspace</h1>
            <p style="margin:0;font-size:.8125rem;color:#6b7280;line-height:1.5">
                You have access to multiple workspaces.<br>Select one to continue.
            </p>
        </div>

        <div class="card-body">
            @if ($errors->any())
                <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:12px;padding:.75rem 1rem;margin-bottom:1rem">
                    <p style="margin:0;font-size:.875rem;color:#dc2626">{{ $errors->first() }}</p>
                </div>
            @endif

            <div>
                @foreach($memberships as $membership)
                    @php
                        $tenantName = $membership->tenant?->name ?? 'Unknown Workspace';
                        $initials   = collect(explode(' ', $tenantName))
                                        ->take(2)
                                        ->map(fn($w) => strtoupper(substr($w, 0, 1)))
                                        ->implode('');
                        $badgeClass = match($membership->role) {
                            'owner'   => 'badge-owner',
                            'admin'   => 'badge-admin',
                            'manager' => 'badge-manager',
                            'member'  => 'badge-member',
                            default   => 'badge-viewer',
                        };
                        $roleLabel = match($membership->role) {
                            'owner'   => 'Owner',
                            'admin'   => 'Admin',
                            'manager' => 'Manager',
                            'member'  => 'Member',
                            default   => 'Viewer',
                        };
                    @endphp
                    <form method="POST" action="{{ route('tenant.choose-workspace') }}" style="display:contents">
                        @csrf
                        <input type="hidden" name="tenant_id" value="{{ $membership->tenant_id }}">
                        <button type="submit" class="workspace-card">
                            <div class="workspace-avatar">{{ $initials }}</div>
                            <div style="flex:1;min-width:0">
                                <div class="workspace-name">{{ $tenantName }}</div>
                                @if($membership->tenant?->program_name && $membership->tenant->program_name !== $tenantName)
                                    <div class="workspace-meta">{{ $membership->tenant->program_name }}</div>
                                @endif
                            </div>
                            <span class="role-badge {{ $badgeClass }}">{{ $roleLabel }}</span>
                            <svg class="arrow-icon" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </button>
                    </form>
                @endforeach
            </div>

            <a href="{{ route('tenant.logout') }}"
               onclick="event.preventDefault();document.getElementById('logout-form').submit()"
               class="logout-link">
                Sign out and switch account
            </a>
            <form id="logout-form" method="POST" action="{{ route('tenant.logout') }}" style="display:none">
                @csrf
            </form>
        </div>
    </div>

</div>

</body>
</html>
