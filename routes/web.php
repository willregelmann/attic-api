<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/health', function () {
    $health = [
        'status' => 'ok',
        'timestamp' => now()->toIso8601String(),
        'environment' => config('app.env'),
        'debug' => config('app.debug'),
    ];

    try {
        DB::connection()->getPdo();
        $health['database'] = 'connected';
    } catch (\Exception $e) {
        $health['status'] = 'error';
        $health['database'] = 'disconnected';
        $health['database_error'] = $e->getMessage();
    }

    return response()->json($health);
});
