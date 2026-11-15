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
    | Generation rate limits
    |--------------------------------------------------------------------------
    |
    | Protect the provider quota (and its cost). per_minute and per_hour apply to
    | each user. global_per_day is shared by everyone (a public demo on a free
    | tier would otherwise be drained by many accounts); 0 disables it.
    |
    */
    'rate_limit' => [
        'per_minute' => (int) env('DASHBOARD_AI_RATE_LIMIT_PER_MINUTE', 3),
        'per_hour' => (int) env('DASHBOARD_AI_RATE_LIMIT_PER_HOUR', 20),
        'global_per_day' => (int) env('DASHBOARD_AI_GLOBAL_PER_DAY', 0),
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
        'model' => env('GEMINI_MODEL', 'gemini-3.6-flash'),
        'timeout' => (int) env('GEMINI_TIMEOUT', 60),
        'max_retries' => (int) env('GEMINI_MAX_RETRIES', 2),
        'max_output_tokens' => (int) env('GEMINI_MAX_OUTPUT_TOKENS', 16384),
    ],

    /*
    |--------------------------------------------------------------------------
    | Help answers
    |--------------------------------------------------------------------------
    |
    | Questions about the product itself ("what can I do?") are answered from fixed
    | content first. Only a question the fixed rules do not recognise is sent to the
    | model, which answers from a short fact sheet (never from the user's rows) with a
    | small output budget. These limits keep it from becoming a general chat or a cost:
    | per_hour applies to each user, global_per_day to everyone (0 disables it).
    | Nothing the user typed is stored.
    |
    */
    'help' => [
        'enabled' => (bool) env('DASHBOARD_AI_HELP_ENABLED', true),
        // Defaults to the generation model; set a cheaper one here if you want.
        'model' => env('GEMINI_HELP_MODEL'),
        'timeout' => (int) env('GEMINI_HELP_TIMEOUT', 12),
        // Generous on purpose: thinking models spend part of this budget before writing.
        'max_output_tokens' => (int) env('GEMINI_HELP_MAX_OUTPUT_TOKENS', 1024),
        'max_question_chars' => 200,
        'rate_limit' => [
            'per_hour' => (int) env('DASHBOARD_AI_HELP_PER_HOUR', 20),
            'global_per_day' => (int) env('DASHBOARD_AI_HELP_GLOBAL_PER_DAY', 300),
        ],
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-5.6-terra'),
        'timeout' => (int) env('OPENAI_TIMEOUT', 60),
        'max_retries' => (int) env('OPENAI_MAX_RETRIES', 2),
    ],
];
