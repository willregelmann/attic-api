<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class UserController extends Controller
{
    public function getGoogleProfilePhoto(Request $request)
    {
        $user = Auth::user();
        
        if (!$user || !$user->google_id) {
            return response()->json(['error' => 'User not authenticated with Google'], 401);
        }

        try {
            // Get the user's Google access token from the current Sanctum token
            // For now, we'll use the Google ID to construct a more stable URL pattern
            
            // Alternative approach: Try to use a more stable Google Photos URL pattern
            $googleId = $user->google_id;
            
            // Try different Google avatar URL patterns that are more stable
            $possibleUrls = [
                "https://lh3.googleusercontent.com/a/default-user={$googleId}",
                "https://lh3.googleusercontent.com/a-/default-user={$googleId}",
                "https://lh3.googleusercontent.com/a/{$googleId}",
            ];
            
            foreach ($possibleUrls as $url) {
                try {
                    $response = Http::timeout(5)->get($url);
                    if ($response->successful() && $response->header('content-type', '')->startsWith('image/')) {
                        return response()->json([
                            'success' => true,
                            'photo_url' => $url . '?sz=96'
                        ]);
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }
            
            // If no direct URL works, return null to use fallback
            return response()->json([
                'success' => true,
                'photo_url' => null
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch profile photo'
            ], 500);
        }
    }
}