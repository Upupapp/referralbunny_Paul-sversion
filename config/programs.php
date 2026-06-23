<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Programs V4 Feature Flag
    |--------------------------------------------------------------------------
    |
    | When 'enabled' is false the V4 multi-program UI is hidden behind its
    | feature flag and tenants see the V3 single-program wizard instead.
    |
    | Override per-environment via .env:  PROGRAMS_V4_ENABLED=true
    |
    */

    'enabled' => (bool) env('PROGRAMS_V4_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Defaults
    |--------------------------------------------------------------------------
    */

    'default_status'       => 'draft',
    'max_programs_per_tenant' => (int) env('MAX_PROGRAMS_PER_TENANT', 25),

    /*
    |--------------------------------------------------------------------------
    | Commission Formula (locked — Phase 1)
    |--------------------------------------------------------------------------
    | See CommissionCalculationService. Listed here for reference only;
    | do not read this array in business logic — read the service env vars.
    |
    */

    'commission' => [
        'company_share_rate'    => 0.30,
        'commission_pool_rate'  => 0.70,
    ],

];
