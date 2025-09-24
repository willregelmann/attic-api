<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class HealthController extends Controller
{
    public function index()
    {
        return view('welcome');
    }
    
    public function test()
    {
        return 'Test route works';
    }
    
    public function health()
    {
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
    }
}