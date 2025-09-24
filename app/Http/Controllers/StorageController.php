<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class StorageController extends Controller
{
    public function serve($path)
    {
        // Check if file exists in storage
        if (!Storage::disk('public')->exists($path)) {
            abort(404);
        }
        
        // Get file content and mime type
        $file = Storage::disk('public')->get($path);
        $mimeType = Storage::disk('public')->mimeType($path);
        
        // Return file with appropriate headers
        return response($file, 200)
            ->header('Content-Type', $mimeType)
            ->header('Cache-Control', 'public, max-age=31536000'); // Cache for 1 year
    }
    
    public function debug($path)
    {
        $diskPath = Storage::disk('public')->path($path);
        $exists = Storage::disk('public')->exists($path);
        
        // Try to list parent directory if file doesn't exist
        $dirContents = null;
        if (!$exists) {
            $dir = dirname($path);
            try {
                if (Storage::disk('public')->exists($dir)) {
                    $dirContents = Storage::disk('public')->files($dir);
                } else {
                    // Try to list what directories do exist
                    $dirContents = [
                        'images_exists' => Storage::disk('public')->exists('images'),
                        'collections_exists' => Storage::disk('public')->exists('images/collections'),
                        'root_dirs' => Storage::disk('public')->directories(''),
                        'images_dirs' => Storage::disk('public')->exists('images') ? Storage::disk('public')->directories('images') : [],
                    ];
                }
            } catch (\Exception $e) {
                $dirContents = ['error' => $e->getMessage()];
            }
        }
        
        return response()->json([
            'requested_path' => $path,
            'disk_path' => $diskPath,
            'exists' => $exists,
            'storage_root' => Storage::disk('public')->path(''),
            'railway_volume' => env('RAILWAY_VOLUME_MOUNT_PATH'),
            'directory_contents' => $dirContents,
        ]);
    }
}