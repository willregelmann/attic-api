<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Test Authentication Controller
 *
 * Provides endpoints for E2E tests to authenticate without going through OAuth flow.
 * These endpoints are ONLY available when APP_ENV=testing.
 *
 * SECURITY: These endpoints bypass normal authentication and should NEVER
 * be available in production environments.
 */
class TestAuthController extends Controller
{
    /**
     * Generate a Sanctum token for a test user
     *
     * If the user doesn't exist, creates them with default password.
     * Returns a valid Sanctum token for authentication in tests.
     *
     * POST /api/test/token
     * Body: { "email": "test@example.com" }
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getToken(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'name' => 'sometimes|string|max:255',
        ]);

        // Find or create test user
        $user = User::firstOrCreate(
            ['email' => $request->email],
            [
                'name' => $request->input('name', 'Test User'),
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
            ]
        );

        // Generate a Sanctum token
        $token = $user->createToken('test-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'name' => $user->name,
            ],
        ]);
    }

    /**
     * Register a new test user
     *
     * Creates a new user account for tests that need fresh users.
     *
     * POST /api/test/register
     * Body: { "name": "Test User", "email": "test@example.com", "password": "password123" }
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'email_verified_at' => now(),
        ]);

        return response()->json([
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'name' => $user->name,
            ],
        ], 201);
    }

    /**
     * Clear all test users from the database
     *
     * Useful for cleaning up after test runs.
     * Only deletes users with email ending in @test.com or @example.com
     *
     * DELETE /api/test/users
     *
     * @return JsonResponse
     */
    public function clearTestUsers(): JsonResponse
    {
        $deleted = User::where('email', 'like', '%@test.com')
            ->orWhere('email', 'like', '%@example.com')
            ->delete();

        return response()->json([
            'message' => 'Test users cleared',
            'deleted' => $deleted,
        ]);
    }
}
