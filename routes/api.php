<?php

use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Public authentication routes
Route::prefix('auth')->group(function () {
    Route::post('google/token', [AuthController::class, 'googleTokenAuth']);
    Route::get('google/callback', [AuthController::class, 'googleCallback']);
    
    // Get Google OAuth URL for frontend apps
    Route::get('google/url', function () {
        return response()->json([
            'url' => \Laravel\Socialite\Facades\Socialite::driver('google')
                ->stateless()
                ->redirect()
                ->getTargetUrl()
        ]);
    });
});

// Protected routes requiring authentication
Route::middleware(['auth:sanctum'])->group(function () {
    // User routes
    Route::prefix('auth')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('logout-all', [AuthController::class, 'logoutAll']);
    });
    
    // Future API routes will go here
    // Route::apiResource('collections', CollectionController::class);
    // Route::apiResource('collectibles', CollectibleController::class);
    // Route::apiResource('items', ItemController::class);
});

// Health check
Route::get('health', function () {
    return response()->json([
        'status' => 'healthy',
        'timestamp' => now()->toISOString(),
        'version' => '1.0.0'
    ]);
});