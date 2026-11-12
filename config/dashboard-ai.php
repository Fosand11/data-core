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

    /*
    |--------------------------------------------------------------------------
    | Generation rate limits (per user)
    |--------------------------------------------------------------------------
    |
    | Protects the provider quota (and its cost) from a single account.
    |
    */
    'rate_limit' => [
        'per_minute' => (int) env('DASHBOARD_AI_RATE_LIMIT_PER_MINUTE', 3),
        'per_hour' => (int) env('DASHBOARD_AI_RATE_LIMIT_PER_HOUR', 20),
    ],

    /*
    |--------------------------------------------------------------------------
    | Stale generations
    |--------------------------------------------------------------------------
    |
    | A queued or generating dashboard that has not moved for this many minutes
    | is considered stuck (a worker died, a deploy interrupted it) and may be
    | retried. Keep it above the job timeout.
    |
    */
    'stale_after_minutes' => (int) env('DASHBOARD_AI_STALE_AFTER_MINUTES', 10),

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-3.8-flash'),
        'timeout' => (int) env('GEMINI_TIMEOUT', 60),
        'max_retries' => (int) env('GEMINI_MAX_RETRIES', 2),
        'max_output_tokens' => (int) env('GEMINI_MAX_OUTPUT_TOKENS', 16384),
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-5.6-terra'),
        'timeout' => (int) env('OPENAI_TIMEOUT', 60),
        'max_retries' => (int) env('OPENAI_MAX_RETRIES', 2),
    ],
];
