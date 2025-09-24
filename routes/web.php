<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

Route::get('/', function () {
    return view('welcome');
});

// Serve storage images directly
Route::get('/storage/{path}', function ($path) {
    $fullPath = $path;
    
    // Log for debugging
    \Illuminate\Support\Facades\Log::info('Storage request', [
        'path' => $path,
        'disk_root' => Storage::disk('public')->path(''),
        'exists' => Storage::disk('public')->exists($fullPath),
        'railway_volume' => env('RAILWAY_VOLUME_MOUNT_PATH'),
    ]);
    
    // Check if file exists in storage
    if (!Storage::disk('public')->exists($fullPath)) {
        \Illuminate\Support\Facades\Log::error('File not found in storage', [
            'path' => $fullPath,
            'attempted_path' => Storage::disk('public')->path($fullPath),
        ]);
        abort(404);
    }
    
    // Get file content and mime type
    $file = Storage::disk('public')->get($fullPath);
    $mimeType = Storage::disk('public')->mimeType($fullPath);
    
    // Return file with appropriate headers
    return response($file, 200)
        ->header('Content-Type', $mimeType)
        ->header('Cache-Control', 'public, max-age=31536000'); // Cache for 1 year
})->where('path', '.*');

Route::get('/health', function () {
    $health = [
        'status' => 'ok',
        'timestamp' => now()->toIso8601String(),
        'environment' => config('app.env'),
        'debug' => config('app.debug'),
        'app_url' => config('app.url'),
    ];

    try {
        DB::connection()->getPdo();
        $health['database'] = 'connected';
    } catch (\Exception $e) {
        $health['status'] = 'error';
        $health['database'] = 'disconnected';
        $health['database_error'] = $e->getMessage();
    }

    // Check storage
    try {
        $testFile = 'test-' . uniqid() . '.txt';
        Storage::disk('public')->put($testFile, 'test');
        $exists = Storage::disk('public')->exists($testFile);
        Storage::disk('public')->delete($testFile);
        $health['storage'] = $exists ? 'working' : 'not working';
        $health['storage_path'] = storage_path('app/public');
        $health['railway_volume'] = env('RAILWAY_VOLUME_MOUNT_PATH', 'not set');
    } catch (\Exception $e) {
        $health['storage'] = 'error';
        $health['storage_error'] = $e->getMessage();
    }

    return response()->json($health);
});
