<?php

return [

    /*
    |--------------------------------------------------------------------------
    | STITCH Manifest — Phase 1 (Static Foundation)
    |--------------------------------------------------------------------------
    |
    | Source of truth for STITCH's fallback registry, role dashboards, route
    | aliases, terminal states, and critical-flow definitions (spec Section E).
    |
    | Phase 1 covers the guest/public surface plus top-level role-dashboard
    | entry points using routes confirmed to exist in routes/web.php. Internal
    | tenant/referrer/partner/super-admin flows (spec Section P items 16-91)
    | are tracked under `roadmap` pending the Phase 2 decision on a seeded
    | role/tenant matrix + runtime crawler — `stitch:fallbacks`/`stitch:contracts`
    | skip entries with a null `route` rather than inventing unverified names.
    |
    */

    'default_fallbacks' => [
        'public' => 'public.home',
        'auth' => 'login',
        'tenant_dashboard' => 'tenant.dashboard',
        'referrer_dashboard' => 'reseller.dashboard',
        'partner_dashboard' => 'partner.dashboard',
        'platform_dashboard' => 'platform.dashboard',
        'tenant_selector' => 'tenant.select',
    ],

    'role_dashboards' => [
        'guest' => 'login',
        'tenant_admin' => 'tenant.dashboard',
        'tenant_manager' => 'tenant.dashboard',
        'referrer' => 'reseller.dashboard',
        'partner' => 'partner.dashboard',
        'super_admin' => 'platform.dashboard',
    ],

    'route_aliases' => [
        'signup.build' => 'tenant.create',
        'join' => 'tenant.join',
    ],

    /*
    |--------------------------------------------------------------------------
    | Terminal States (spec Section H)
    |--------------------------------------------------------------------------
    */

    'terminal_states' => [
        'valid' => [
            'task_completed',
            'dashboard_reached',
            'setup_overview_reached',
            'list_page_reached',
            'role_portal_reached',
            'login_reached',
            'tenant_selector_reached',
            'permission_denied_friendly',
            'not_found_friendly',
            'validation_blocker_with_fix',
            'empty_state_with_cta',
            'failure_with_retry_and_fallback',
            'download_started',
            'email_sent_or_queued',
            'external_url_opened',
            'logout_then_login',
            'draft_saved_returned',
            'modal_cancelled_returned',
            'destructive_action_cancelled',
            'lgu_ids_action_blocked_with_audit',
        ],
        'invalid' => [
            'raw_404',
            'raw_419',
            'raw_500',
            'blank_page',
            'unresolved_exception',
            'redirect_loop',
            'inaccessible_route',
            'hidden_dead_end',
            'frozen_loading',
            'disabled_cta_no_explanation',
            'unresolved_modal',
            'missing_route',
            'wrong_dashboard',
            'wrong_portal',
            'cross_tenant_data_shown',
            'lgu_ids_logic_changed',
        ],
    ],

    'failure_modal' => [
        'component' => 'public.failure-modal',
        'open_event' => 'rb-modal-open',
    ],

    /*
    |--------------------------------------------------------------------------
    | Critical Flows (spec Section P)
    |--------------------------------------------------------------------------
    |
    | route: real named route, or null if not yet built/confirmed.
    | view: Blade view name, or null if unknown/not safely renderable standalone.
    | guest_reachable: true if stitch:map's guest HTTP crawl can reach this in
    | Phase 1 (no auth required to view).
    |
    */

    'critical_flows' => [
        // Public / Onboarding — spec Section P items 1-14
        ['id' => 1, 'category' => 'public', 'label' => 'Landing page', 'route' => 'public.home', 'view' => 'public.home', 'guest_reachable' => true],
        ['id' => 2, 'category' => 'public', 'label' => 'Build Referral Program CTA', 'route' => 'signup.build', 'view' => 'auth.build-program', 'guest_reachable' => true],
        ['id' => 3, 'category' => 'public', 'label' => 'Join Referral Program CTA', 'route' => 'join', 'view' => 'auth.join-program', 'guest_reachable' => true],
        ['id' => 4, 'category' => 'public', 'label' => 'Login', 'route' => 'login', 'view' => null, 'guest_reachable' => true],
        ['id' => 5, 'category' => 'public', 'label' => 'Signup build flow', 'route' => 'tenant.create', 'view' => 'auth.build-program', 'guest_reachable' => true],
        ['id' => 6, 'category' => 'public', 'label' => 'Existing email handling', 'route' => null, 'view' => null, 'guest_reachable' => false],
        ['id' => 7, 'category' => 'public', 'label' => 'Password reset', 'route' => null, 'view' => null, 'guest_reachable' => false],
        ['id' => 8, 'category' => 'public', 'label' => 'Email verification', 'route' => null, 'view' => null, 'guest_reachable' => false],
        ['id' => 9, 'category' => 'public', 'label' => 'New tenant creation', 'route' => 'tenant.create', 'view' => 'auth.build-program', 'guest_reachable' => true],
        ['id' => 10, 'category' => 'public', 'label' => 'Tenant selector', 'route' => 'tenant.select', 'view' => 'auth.tenant-coming-soon', 'guest_reachable' => true],
        ['id' => 11, 'category' => 'public', 'label' => 'Join invite lookup', 'route' => 'tenant.join', 'view' => 'auth.join-program', 'guest_reachable' => true],
        ['id' => 12, 'category' => 'public', 'label' => 'Invite acceptance', 'route' => null, 'view' => null, 'guest_reachable' => false],
        ['id' => 13, 'category' => 'public', 'label' => 'Expired invite', 'route' => null, 'view' => null, 'guest_reachable' => false],
        ['id' => 14, 'category' => 'public', 'label' => 'Wrong portal redirect', 'route' => null, 'view' => null, 'guest_reachable' => false],

        // Top-level role-dashboard entry points (spec Section N/O matrix anchors)
        ['id' => 15, 'category' => 'tenant_admin', 'label' => 'Tenant dashboard', 'route' => 'tenant.dashboard', 'view' => null, 'guest_reachable' => false],
        ['id' => 50, 'category' => 'referrer', 'label' => 'Referrer dashboard', 'route' => 'reseller.dashboard', 'view' => null, 'guest_reachable' => false],
        ['id' => 66, 'category' => 'partner', 'label' => 'Partner dashboard', 'route' => 'partner.dashboard', 'view' => null, 'guest_reachable' => false],
        ['id' => 74, 'category' => 'super_admin', 'label' => 'Platform dashboard', 'route' => 'platform.dashboard', 'view' => null, 'guest_reachable' => false],

        // System states — spec Section P items 82-85
        ['id' => 82, 'category' => 'system', 'label' => '403 friendly page', 'route' => null, 'view' => 'errors.403', 'guest_reachable' => false],
        ['id' => 83, 'category' => 'system', 'label' => '404 friendly page', 'route' => null, 'view' => 'errors.404', 'guest_reachable' => true],
        ['id' => 84, 'category' => 'system', 'label' => '419 CSRF recovery', 'route' => null, 'view' => 'errors.419', 'guest_reachable' => false],
        ['id' => 85, 'category' => 'system', 'label' => '500 friendly fallback', 'route' => null, 'view' => 'errors.500', 'guest_reachable' => false],
    ],

    /*
    |--------------------------------------------------------------------------
    | Roadmap (Phase 2/3 — not yet route-mapped)
    |--------------------------------------------------------------------------
    |
    | spec Section P categories not yet mapped to specific routes/views. Counts
    | are informational so stitch:report can show manifest coverage without
    | inventing unverified route names.
    |
    */

    'roadmap' => [
        ['category' => 'tenant_admin', 'label' => 'Tenant Admin / Manager flows', 'spec_items' => '16-49', 'count' => 34],
        ['category' => 'referrer', 'label' => 'Referrer flows', 'spec_items' => '51-65', 'count' => 15],
        ['category' => 'partner', 'label' => 'Partner flows', 'spec_items' => '67-73', 'count' => 7],
        ['category' => 'super_admin', 'label' => 'Super Admin flows', 'spec_items' => '75-81', 'count' => 7],
        ['category' => 'system', 'label' => 'Remaining system states', 'spec_items' => '86-91', 'count' => 6],
    ],

    /*
    |--------------------------------------------------------------------------
    | QA Role/Tenant Matrix (Phase 2)
    |--------------------------------------------------------------------------
    |
    | 13 seeded sessions for STITCH's runtime crawler: 4 tenant-scoped roles
    | (tenant_admin, tenant_manager, referrer, partner) x 3 QA tenants
    | (qa-tenant-a/b/c) + 1 platform-wide super_admin. Seeded via
    | ReferralBunnyQaSeeder (php artisan stitch:seed), shared password
    | QaPassword123!. lgu-ids is intentionally absent — it is the protected
    | production-like tenant (see App\Support\ProtectedTenants) and must
    | never be touched by STITCH tooling.
    |
    */

    'qa_matrix' => [
        ['role' => 'tenant_admin',   'tenant_id' => 'qa-tenant-a', 'email' => 'qa-admin-a@referralbunny.ai',           'login_route' => 'tenant.login',   'dashboard_route' => 'tenant.dashboard'],
        ['role' => 'tenant_admin',   'tenant_id' => 'qa-tenant-b', 'email' => 'qa-admin-b@referralbunny.ai',           'login_route' => 'tenant.login',   'dashboard_route' => 'tenant.dashboard'],
        ['role' => 'tenant_admin',   'tenant_id' => 'qa-tenant-c', 'email' => 'qa-admin-c@referralbunny.ai',           'login_route' => 'tenant.login',   'dashboard_route' => 'tenant.dashboard'],
        ['role' => 'tenant_manager', 'tenant_id' => 'qa-tenant-a', 'email' => 'qa-manager-a@referralbunny.ai',         'login_route' => 'tenant.login',   'dashboard_route' => 'tenant.dashboard'],
        ['role' => 'tenant_manager', 'tenant_id' => 'qa-tenant-b', 'email' => 'qa-manager-b@referralbunny.ai',         'login_route' => 'tenant.login',   'dashboard_route' => 'tenant.dashboard'],
        ['role' => 'tenant_manager', 'tenant_id' => 'qa-tenant-c', 'email' => 'qa-manager-c@referralbunny.ai',         'login_route' => 'tenant.login',   'dashboard_route' => 'tenant.dashboard'],
        ['role' => 'referrer',       'tenant_id' => 'qa-tenant-a', 'email' => 'qa-referrer-active@referralbunny.ai',   'login_route' => 'reseller.login', 'dashboard_route' => 'reseller.dashboard'],
        ['role' => 'referrer',       'tenant_id' => 'qa-tenant-b', 'email' => 'qa-referrer-active-b@referralbunny.ai', 'login_route' => 'reseller.login', 'dashboard_route' => 'reseller.dashboard'],
        ['role' => 'referrer',       'tenant_id' => 'qa-tenant-c', 'email' => 'qa-referrer-active-c@referralbunny.ai', 'login_route' => 'reseller.login', 'dashboard_route' => 'reseller.dashboard'],
        ['role' => 'partner',        'tenant_id' => 'qa-tenant-a', 'email' => 'qa-partner-active@referralbunny.ai',    'login_route' => 'partner.login',  'dashboard_route' => 'partner.dashboard'],
        ['role' => 'partner',        'tenant_id' => 'qa-tenant-b', 'email' => 'qa-partner-active-b@referralbunny.ai',  'login_route' => 'partner.login',  'dashboard_route' => 'partner.dashboard'],
        ['role' => 'partner',        'tenant_id' => 'qa-tenant-c', 'email' => 'qa-partner-active-c@referralbunny.ai',  'login_route' => 'partner.login',  'dashboard_route' => 'partner.dashboard'],
        ['role' => 'super_admin',    'tenant_id' => null,          'email' => 'qa-superadmin@referralbunny.ai',        'login_route' => 'login',          'dashboard_route' => 'platform.dashboard'],
    ],

];
