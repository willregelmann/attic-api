<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'message' => 'Will\'s Attic API is running!',
        'version' => '1.0.0',
        'status' => 'operational',
        'documentation' => [
            'base_url' => url('/'),
            'endpoints' => [
                'auth' => [
                    'POST /api/auth/google/token' => 'Authenticate with Google token',
                    'GET /api/auth/google/url' => 'Get Google OAuth URL',
                    'GET /api/auth/me' => 'Get authenticated user (requires token)',
                    'POST /api/auth/logout' => 'Logout current session (requires token)',
                    'POST /api/auth/logout-all' => 'Logout all sessions (requires token)',
                ],
                'health' => [
                    'GET /api/health' => 'API health check',
                ],
            ],
            'authentication' => [
                'type' => 'Bearer Token',
                'header' => 'Authorization: Bearer {token}',
                'note' => 'Obtain token via Google OAuth endpoints',
            ],
        ],
    ]);
});

Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'timestamp' => now()->toISOString()
    ]);
});
