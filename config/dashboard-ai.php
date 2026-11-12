<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Dashboard definition generator
    |--------------------------------------------------------------------------
    |
    | Drivers are selected explicitly. Any missing or unsupported value
    | resolves to a provider that fails safely; there is no implicit fallback.
    |
    */
    'driver' => env('DASHBOARD_AI_DRIVER'),

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-5.6-terra'),
        'timeout' => (int) env('OPENAI_TIMEOUT', 60),
        'max_retries' => (int) env('OPENAI_MAX_RETRIES', 2),
    ],
];
