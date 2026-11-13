<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Showcase demo account
    |--------------------------------------------------------------------------
    |
    | Used only by `php artisan demo:install`, which creates one account with a
    | few ready-made dashboards for a public demo. The password has no default on
    | purpose: it must be set in the server's environment.
    |
    */
    'name' => env('DEMO_USER_NAME', 'Demo'),
    'email' => env('DEMO_USER_EMAIL'),
    'password' => env('DEMO_USER_PASSWORD'),
];
