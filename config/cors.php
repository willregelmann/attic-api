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

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'graphql'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'http://localhost:3000',
        'http://localhost:3001',
        'https://attic-ilwnq9i2k-will-regelmanns-projects.vercel.app',
        'https://attic-ui.vercel.app',
        'https://attic-ui-production.up.railway.app',
        'https://localhost:3000',
        'https://localhost:3001',
    ],

    'allowed_origins_patterns' => [
        'https://*.vercel.app',
        'https://*.up.railway.app',
        'http://localhost:*',
        'https://localhost:*',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];