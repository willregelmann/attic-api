<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\StorageController;
use App\Http\Controllers\HealthController;

Route::get('/', [HealthController::class, 'index']);
Route::get('/test', [HealthController::class, 'test']);
Route::get('/health', [HealthController::class, 'health']);

// Serve storage images directly
Route::get('/storage/{path}', [StorageController::class, 'serve'])->where('path', '.*');

// Debug storage
Route::get('/debug-storage/{path}', [StorageController::class, 'debug'])->where('path', '.*');
