<?php

use App\Http\Controllers\TestAuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Health check endpoint
Route::get('/health', function () {
    return response()->json(['status' => 'ok']);
});

// Test-only endpoints (only available when APP_ENV=testing or local)
if (in_array(config('app.env'), ['testing', 'local']) && config('app.enable_test_endpoints', false)) {
    Route::prefix('test')->group(function () {
        Route::post('/token', [TestAuthController::class, 'getToken']);
        Route::post('/register', [TestAuthController::class, 'register']);
        Route::delete('/users', [TestAuthController::class, 'clearTestUsers']);
    });
}
