<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageUploadController extends Controller
{
    public function uploadCollectionImage(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:5120', // 5MB max
        ]);

        try {
            $image = $request->file('image');
            $filename = 'collections/' . time() . '-' . Str::random(10) . '.' . $image->getClientOriginalExtension();
            
            // Store the image (this will use the default disk configured in config/filesystems.php)
            $path = $image->storeAs('public', $filename);
            
            // Generate the public URL through our API route
            $url = '/storage/' . $filename;
            
            return response()->json([
                'success' => true,
                'url' => $url,
                'filename' => $filename
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to upload image: ' . $e->getMessage()
            ], 500);
        }
    }

    public function uploadItemImage(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:5120', // 5MB max
        ]);

        try {
            $image = $request->file('image');
            $filename = 'items/' . time() . '-' . Str::random(10) . '.' . $image->getClientOriginalExtension();
            
            // Store the image
            $path = $image->storeAs('public', $filename);
            
            // Generate the public URL through our API route
            $url = '/storage/' . $filename;
            
            return response()->json([
                'success' => true,
                'url' => $url,
                'filename' => $filename
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to upload image: ' . $e->getMessage()
            ], 500);
        }
    }

    public function serveImage($path)
    {
        try {
            // Check if file exists in storage
            if (!Storage::disk('public')->exists($path)) {
                abort(404);
            }
            
            // Get the file path
            $filePath = Storage::disk('public')->path($path);
            
            // Get MIME type
            $mimeType = Storage::disk('public')->mimeType($path);
            
            // Return the file with proper headers
            return response()->file($filePath, [
                'Content-Type' => $mimeType,
                'Cache-Control' => 'public, max-age=3600', // Cache for 1 hour
            ]);
            
        } catch (\Exception $e) {
            abort(404);
        }
    }
}