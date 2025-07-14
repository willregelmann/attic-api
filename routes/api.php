<?php

use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;

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
    
    // Debug endpoint to check environment configuration
    Route::get('debug/config', function () {
        return response()->json([
            'app_env' => env('APP_ENV'),
            'app_debug' => env('APP_DEBUG'),
            'database_connection' => env('DB_CONNECTION'),
            'google_client_id_set' => !empty(env('GOOGLE_CLIENT_ID')),
            'google_client_secret_set' => !empty(env('GOOGLE_CLIENT_SECRET')),
            'google_redirect_uri' => env('GOOGLE_REDIRECT_URI'),
            'database_connected' => DB::connection()->getPdo() ? true : false,
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

// Ultra simple test endpoint
Route::get('ping', function () {
    return 'pong';
});

// Simple test endpoint
Route::get('test', function () {
    return response()->json([
        'status' => 'working',
        'message' => 'Simple test endpoint is functional',
        'php_version' => PHP_VERSION,
        'laravel_version' => app()->version()
    ]);
});

// Database diagnostic endpoint
Route::get('db-test', function () {
    try {
        $dbConfig = config('database.connections.sqlite');
        $dbPath = $dbConfig['database'];
        
        $response = [
            'database_config' => $dbConfig,
            'database_file_exists' => file_exists($dbPath),
            'database_file_readable' => is_readable($dbPath),
            'database_file_writable' => is_writable($dbPath),
            'current_working_directory' => getcwd(),
            'database_absolute_path' => realpath($dbPath) ?: 'File not found',
        ];
        
        // Try to connect to database
        try {
            $pdo = new PDO('sqlite:' . $dbPath);
            $response['pdo_connection'] = 'successful';
            
            // Try a simple query
            $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $response['tables'] = $tables;
            
        } catch (Exception $e) {
            $response['pdo_connection'] = 'failed';
            $response['pdo_error'] = $e->getMessage();
        }
        
        // Try Laravel DB connection
        try {
            $tables = DB::select("SELECT name FROM sqlite_master WHERE type='table'");
            $response['laravel_db_connection'] = 'successful';
            $response['laravel_tables'] = collect($tables)->pluck('name')->toArray();
        } catch (Exception $e) {
            $response['laravel_db_connection'] = 'failed';
            $response['laravel_db_error'] = $e->getMessage();
        }
        
        return response()->json($response);
        
    } catch (Exception $e) {
        return response()->json([
            'error' => 'Database diagnostic failed',
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], 500);
    }
});

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