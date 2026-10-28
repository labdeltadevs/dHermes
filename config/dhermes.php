<?php

return [
    'tenant' => [
        'code' => env('DHERMES_TENANT_CODE', 'DHERMES'),
        'name' => env('DHERMES_TENANT_NAME', 'dHermes'),
        'timezone' => env('DHERMES_TENANT_TIMEZONE', 'America/La_Paz'),
        'local_currency' => env('DHERMES_TENANT_CURRENCY', 'BOB'),
    ],

    'admin' => [
        'name' => env('DHERMES_ADMIN_NAME', 'Administrador Maestro'),
        'email' => env('DHERMES_ADMIN_EMAIL', 'admin@dhermes.local'),
        'password' => env('DHERMES_ADMIN_PASSWORD', 'admin-password-1234'),
    ],
];
