<?php

return [
    'session_key' => 'current_tenant_id',

    /*
    |--------------------------------------------------------------------------
    | Demo data on registration
    |--------------------------------------------------------------------------
    |
    | When enabled, every new organization receives the demonstration sales
    | dataset so generated dashboards have data to show. Disable it in
    | environments that must start with empty organizations.
    |
    */
    'seed_demo_data' => filter_var(env('TENANCY_SEED_DEMO_DATA', true), FILTER_VALIDATE_BOOLEAN),
];
