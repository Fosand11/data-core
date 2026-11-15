<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Privacy policy
    |--------------------------------------------------------------------------
    |
    | The version is stored with each person's acceptance when they register, so a change
    | of terms can be told apart from the one they agreed to. Change it (and the date)
    | whenever the policy's meaning changes, not for typo fixes.
    |
    */
    'version' => '2026-11-15',
    'updated_at' => '2026-11-15',

    // Who answers for the data and how to reach them. The e-mail is optional and comes from the
    // environment so that no personal address lives in the repository; without it the page
    // points to the studio's site.
    'controller' => [
        'name' => 'Cifra Studios',
        'url' => 'https://cifra-studios-core.vercel.app/',
    ],
    'contact_email' => env('PRIVACY_CONTACT_EMAIL'),

    /*
    |--------------------------------------------------------------------------
    | Who else handles the data
    |--------------------------------------------------------------------------
    |
    | Shown on the policy page exactly as listed here, so the page cannot drift from what the
    | application really does. Add a line before adding an integration.
    |
    */
    'processors' => [
        [
            'name' => 'Render',
            'purpose' => 'Alojamiento de la aplicación',
            'data' => 'Todo el tráfico de la aplicación y los registros técnicos de las peticiones, que incluyen la dirección IP.',
            'location' => 'Estados Unidos (región Ohio)',
            'url' => 'https://render.com/privacy',
        ],
        [
            'name' => 'Neon',
            'purpose' => 'Base de datos PostgreSQL',
            'data' => 'Tu cuenta, tu organización, tus dashboards, las preguntas con las que los creaste y las filas que importes.',
            'location' => 'Según la región elegida para el proyecto de base de datos',
            'url' => 'https://neon.com/privacy-policy',
        ],
        [
            'name' => 'Google (API de Gemini)',
            'purpose' => 'Diseñar dashboards y responder preguntas de ayuda con inteligencia artificial',
            'data' => 'El texto de tu pregunta y los nombres de las columnas de tus datos. Nunca tus filas, ni tu correo, ni datos de tu cuenta.',
            'location' => 'Estados Unidos y otras regiones donde opera Google',
            'url' => 'https://policies.google.com/privacy',
        ],
        [
            'name' => 'UptimeRobot',
            'purpose' => 'Comprobar que el servicio está disponible',
            'data' => 'Solo consulta una dirección pública de la aplicación que no contiene datos personales.',
            'location' => 'Según el proveedor',
            'url' => 'https://uptimerobot.com/privacy/',
        ],
    ],
];
