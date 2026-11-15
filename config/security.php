<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Content Security Policy
    |--------------------------------------------------------------------------
    |
    | "enforce" blocks anything the policy does not allow, "report-only" only reports it in
    | the browser console (to try a change safely) and "off" sends no policy. It is never
    | sent while the Vite dev server is running, which needs inline scripts and websockets.
    |
    */
    'csp' => env('SECURITY_CSP', 'enforce'),

    /*
    |--------------------------------------------------------------------------
    | HTTP Strict Transport Security
    |--------------------------------------------------------------------------
    |
    | Tells browsers to use HTTPS only, for this many seconds. Sent only over a secure
    | request in a non-local environment; 0 disables it.
    |
    */
    'hsts_max_age' => (int) env('SECURITY_HSTS_MAX_AGE', 31536000),
];
