<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Dashboard definition generator
    |--------------------------------------------------------------------------
    |
    | Phase 2 only ships the deterministic development provider. Any missing
    | or unsupported value resolves to a provider that fails safely.
    |
    */
    'driver' => env('DASHBOARD_AI_DRIVER'),
];
