<?php

namespace App\Http\Controllers;

use App\Models\User;
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
                'user' => $user->load(['collections', 'items', 'collectibles']),
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
            
            // Create API token
            $token = $user->createToken('api-token', ['*'], now()->addDays(30))->plainTextToken;
            
            return response()->json([
                'success' => true,
                'token' => $token,
                'user' => $user->load(['collections', 'items', 'collectibles']),
                'expires_at' => now()->addDays(30)->toISOString(),
            ]);
            
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Google token auth error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Authentication failed',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
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
        
        // Update last active timestamp
        $user->update(['last_active_at' => now()]);
        
        return response()->json([
            'success' => true,
            'user' => $user->load(['collections', 'items', 'collectibles'])
        ]);
    }
    
    /**
     * Logout user (revoke current token)
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if ($user) {
            // Revoke current token
            $request->user()->currentAccessToken()->delete();
        }
        
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
        $user = $request->user();
        
        if ($user) {
            // Revoke all tokens
            $user->tokens()->delete();
        }
        
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
        // Try to find existing user by Google ID
        $user = User::where('google_id', $googleUser->id)->first();
        
        if ($user) {
            // Update user info from Google
            $user->update([
                'email' => $googleUser->email,
                'google_avatar' => $googleUser->avatar,
                'email_verified_at' => now(),
                'profile' => array_merge($user->profile ?? [], [
                    'displayName' => $googleUser->name,
                ]),
                'last_active_at' => now(),
            ]);
            
            return $user;
        }
        
        // Try to find by email (for account linking)
        $user = User::where('email', $googleUser->email)->first();
        
        if ($user) {
            // Link Google account
            $user->update([
                'google_id' => $googleUser->id,
                'google_avatar' => $googleUser->avatar,
                'email_verified_at' => now(),
                'profile' => array_merge($user->profile ?? [], [
                    'displayName' => $googleUser->name,
                ]),
                'last_active_at' => now(),
            ]);
            
            return $user;
        }
        
        // Create new user
        $username = $this->generateUniqueUsername($googleUser->name ?? $googleUser->email);
        
        return User::create([
            'username' => $username,
            'email' => $googleUser->email,
            'google_id' => $googleUser->id,
            'google_avatar' => $googleUser->avatar,
            'email_verified_at' => now(),
            'profile' => [
                'displayName' => $googleUser->name,
                'bio' => null,
                'location' => null,
            ],
            'preferences' => [
                'defaultVisibility' => 'private',
                'notifications' => true,
            ],
            'trade_rating' => [
                'score' => 5.0,
                'totalTrades' => 0,
                'completedTrades' => 0,
            ],
            'subscription' => [
                'tier' => 'free',
                'expiresAt' => null,
            ],
            'last_active_at' => now(),
        ]);
    }
    
    /**
     * Verify Google token using Google's API
     */
    private function verifyGoogleToken(string $token): ?object
    {
        try {
            $response = Http::get('https://www.googleapis.com/oauth2/v1/userinfo', [
                'access_token' => $token
            ]);
            
            if ($response->successful()) {
                $data = $response->json();
                
                return (object) [
                    'id' => $data['id'],
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
    
    /**
     * Generate unique username from display name
     */
    private function generateUniqueUsername(string $name): string
    {
        $base = strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $name));
        $base = trim($base, '_');
        $base = preg_replace('/_{2,}/', '_', $base);
        
        if (empty($base)) {
            $base = 'user';
        }
        
        $username = $base;
        $counter = 1;
        
        while (User::where('username', $username)->exists()) {
            $username = $base . '_' . $counter;
            $counter++;
        }
        
        return $username;
    }
}
