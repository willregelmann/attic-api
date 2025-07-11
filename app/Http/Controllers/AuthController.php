<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Http\Resources\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    /**
     * Handle Google OAuth callback - for traditional web flow
     */
    public function googleCallback(Request $request): JsonResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
            
            $user = $this->findOrCreateUser($googleUser);
            
            // Create API token
            $token = $user->createToken('api-token')->plainTextToken;
            
            return response()->json([
                'success' => true,
                'token' => $token,
                'user' => $user->load(['items']),
            ]);
            
        } catch (\Exception $e) {
            Log::error('Google OAuth error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Authentication failed',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
    
    /**
     * Handle Google token verification - for frontend apps
     */
    public function googleTokenAuth(Request $request): JsonResponse
    {
        $request->validate([
            'google_token' => 'required|string',
        ]);
        
        try {
            // Verify Google token
            $googleUser = $this->verifyGoogleToken($request->google_token);
            
            if (!$googleUser) {
                throw ValidationException::withMessages([
                    'google_token' => ['Invalid Google token']
                ]);
            }
            
            $user = $this->findOrCreateUser($googleUser);
            
            // Create real Sanctum API token
            $token = $user->createToken('api-token')->plainTextToken;
            
            return response()->json([
                'success' => true,
                'token' => $token,
                'user' => $user->load(['items']), // Load relationships
                'expires_at' => now()->addDays(30)->toISOString(),
            ]);
            
        } catch (ValidationException $e) {
            return ApiResponse::validationError($e->errors());
        } catch (\Exception $e) {
            Log::error('Google token auth error: ' . $e->getMessage());
            
            if (config('app.debug')) {
                return ApiResponse::serverError($e->getMessage());
            }
            
            return ApiResponse::serverError('Authentication failed');
        }
    }
    
    /**
     * Get authenticated user info
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], 401);
        }
        
        // No need to track last active for MVP
        
        return response()->json([
            'success' => true,
            'user' => $user // Skip relationship loading for now
        ]);
    }
    
    /**
     * Logout user (revoke current token)
     */
    public function logout(Request $request): JsonResponse
    {
        // Mock logout for MVP - no database operations needed
        return response()->json([
            'success' => true,
            'message' => 'Successfully logged out'
        ]);
    }
    
    /**
     * Revoke all user tokens
     */
    public function logoutAll(Request $request): JsonResponse
    {
        // Mock logout all for MVP - no database operations needed
        return response()->json([
            'success' => true,
            'message' => 'Successfully logged out from all devices'
        ]);
    }
    
    /**
     * Find or create user from Google data
     */
    private function findOrCreateUser($googleUser): User
    {
        // Check if user already exists by Google ID
        $user = User::where('google_id', $googleUser->id)->first();
        
        if ($user) {
            // Update user info from Google in case it changed
            $user->update([
                'name' => $googleUser->name ?? $user->name,
                'email' => $googleUser->email ?? $user->email,
                'google_avatar' => $googleUser->avatar ?? $user->google_avatar,
            ]);
            
            return $user;
        }
        
        // Create new user
        return User::create([
            'name' => $googleUser->name ?? 'Unknown User',
            'email' => $googleUser->email ?? '',
            'google_id' => $googleUser->id,
            'google_avatar' => $googleUser->avatar,
            'email_verified_at' => now(), // Google OAuth verifies email
        ]);
    }
    
    /**
     * Test login endpoint for development (bypasses Google OAuth)
     * ONLY available in local development environment
     */
    public function testLogin(Request $request): JsonResponse
    {
        // SECURITY: Prevent test authentication in production
        if (!app()->environment(['local', 'testing'])) {
            return response()->json([
                'success' => false,
                'message' => 'Test authentication not available in this environment'
            ], 403);
        }
        
        $request->validate([
            'test_user_id' => 'string|nullable',
        ]);
        
        try {
            // Create mock Google user for testing
            $testGoogleUser = (object) [
                'id' => $request->test_user_id ?? 'test_google_id_123',
                'email' => 'test@example.com',
                'name' => 'Test User',
                'avatar' => 'https://example.com/avatar.jpg',
            ];
            
            $user = $this->findOrCreateUser($testGoogleUser);
            
            // Create API token
            $token = 'test_token_' . time() . '_' . rand(1000, 9999);
            
            return response()->json([
                'success' => true,
                'token' => $token,
                'user' => $user,
                'expires_at' => now()->addDays(30)->toISOString(),
            ]);
            
        } catch (\Exception $e) {
            Log::error('Test login error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Test authentication failed',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
    
    /**
     * Verify Google ID token (JWT) using Google's API
     */
    private function verifyGoogleToken(string $token): ?object
    {
        try {
            // Verify the Google ID token using Google's tokeninfo endpoint
            $response = Http::get('https://www.googleapis.com/oauth2/v3/tokeninfo', [
                'id_token' => $token
            ]);
            
            if ($response->successful()) {
                $data = $response->json();
                
                // Verify the audience matches our Google Client ID
                $expectedAudience = config('services.google.client_id');
                if ($data['aud'] !== $expectedAudience) {
                    Log::warning('Google token audience mismatch', [
                        'expected' => $expectedAudience,
                        'received' => $data['aud'] ?? 'none'
                    ]);
                    return null;
                }
                
                return (object) [
                    'id' => $data['sub'], // Google's subject ID
                    'email' => $data['email'],
                    'name' => $data['name'] ?? null,
                    'avatar' => $data['picture'] ?? null,
                ];
            }
            
            return null;
        } catch (\Exception $e) {
            Log::error('Google token verification failed: ' . $e->getMessage());
            return null;
        }
    }
    
}
