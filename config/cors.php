<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'graphql', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_filter([
        'http://localhost:5173',
        'http://localhost:5174',
        'http://localhost:5175',
        'https://www.myattic.online',
        'https://www.my-attic.online',
        'https://myattic.online',
        'https://my-attic.online',
        env('FRONTEND_URL'),
    ]),

    'allowed_origins_patterns' => [
        'https://*.up.railway.app',
        'https://*.myattic.online',
        'https://*.my-attic.online',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];