<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie', 'broadcasting/auth', 'login', 'logout'],
    'allowed_methods' => ['*'],
    'allowed_origins' => array_values(array_filter(array_map('trim', explode(',', env(
        'FRONTEND_URLS',
        env('FRONTEND_URL', 'http://127.0.0.1:5173') . ',http://127.0.0.1:5174,http://localhost:5173,http://localhost:5174'
    ))))),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];
