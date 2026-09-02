<?php

declare(strict_types=1);

/*
| The SPA is served from a different origin to the API in every environment,
| so CORS is part of the contract rather than a local-only workaround. The
| allowed origin is configuration, never a wildcard.
*/

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        env('FRONTEND_URL', 'http://localhost:5173'),
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
