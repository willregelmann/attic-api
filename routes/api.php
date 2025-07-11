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
    
    // Test authentication endpoint for development
    Route::post('test/login', [AuthController::class, 'testLogin']);
    
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
    
    // User profile routes
    Route::prefix('user')->group(function () {
        Route::get('google-profile-photo', [App\Http\Controllers\UserController::class, 'getGoogleProfilePhoto']);
    });
    
    // Image upload routes
    Route::prefix('upload')->group(function () {
        Route::post('collection-image', [App\Http\Controllers\ImageUploadController::class, 'uploadCollectionImage']);
        Route::post('item-image', [App\Http\Controllers\ImageUploadController::class, 'uploadItemImage']);
    });
    
    // Note: GraphQL endpoint is automatically registered by Lighthouse at /graphql
    // REST API routes for compatibility (collections are public, so only CUD operations need auth)
    Route::apiResource('collectibles', App\Http\Controllers\CollectibleController::class);
    Route::apiResource('items', App\Http\Controllers\ItemController::class);
    
    // Protected collection operations (create, update, delete)
    Route::post('collections', [App\Http\Controllers\CollectionController::class, 'store']);
    Route::put('collections/{collection}', [App\Http\Controllers\CollectionController::class, 'update']);
    Route::patch('collections/{collection}', [App\Http\Controllers\CollectionController::class, 'update']);
    Route::delete('collections/{collection}', [App\Http\Controllers\CollectionController::class, 'destroy']);
});

// Image serving routes (public access - no auth required)
Route::get('storage/{path}', [App\Http\Controllers\ImageUploadController::class, 'serveImage'])
    ->where('path', '.*');

// Public collections - allow browsing without authentication
Route::get('collections', [App\Http\Controllers\CollectionController::class, 'index']);
Route::get('collections/{collection}', [App\Http\Controllers\CollectionController::class, 'show']);

// Health check with optional collections data
Route::get('health', function (Request $request) {
    $response = [
        'status' => 'healthy',
        'timestamp' => now()->toISOString(),
        'version' => '1.1.0-no-cache'
    ];
    
    // If collections parameter is present, include empty collections data
    if ($request->has('collections')) {
        $response['collections'] = [];
        $response['message'] = 'Collections endpoint working via health check';
    }
    
    return response()->json($response);
});