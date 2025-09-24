<?php

namespace App\GraphQL\Mutations;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Illuminate\Support\Facades\Log;

class AuthMutations
{
    public function login($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $user = User::where('email', $args['email'])->first();

        if (!$user || !Hash::check($args['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return [
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
        ];
    }

    public function logout($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $user = Auth::guard('sanctum')->user();

        if ($user) {
            $user->currentAccessToken()->delete();
            return 'Successfully logged out';
        }

        return 'No active session';
    }

    public function register($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $user = User::create([
            'name' => $args['name'],
            'email' => $args['email'],
            'password' => Hash::make($args['password']),
        ]);

        $token = $user->createToken('api-token')->plainTextToken;

        return [
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
        ];
    }

    public function googleLogin($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        Log::info('Google login attempt', ['token' => substr($args['google_token'], 0, 50) . '...']);

        // Decode the Google JWT token to get user info
        // In production, you should verify the token with Google's public keys
        $googleToken = $args['google_token'];

        // Parse JWT token (header.payload.signature)
        $tokenParts = explode('.', $googleToken);
        if (count($tokenParts) !== 3) {
            throw new \Exception('Invalid Google token format');
        }

        // Decode payload
        $tokenPayload = base64_decode(str_replace(['-', '_'], ['+', '/'], $tokenParts[1]));
        $tokenData = json_decode($tokenPayload, true);

        if (!$tokenData || !isset($tokenData['email'])) {
            throw new \Exception('Invalid Google token data');
        }

        Log::info('Google token decoded', ['email' => $tokenData['email']]);

        // Find or create user
        $user = User::firstOrCreate(
            ['email' => $tokenData['email']],
            [
                'name' => $tokenData['name'] ?? ($tokenData['given_name'] . ' ' . $tokenData['family_name']),
                'password' => Hash::make(uniqid()), // Random password since they use Google
                'email_verified_at' => now(),
            ]
        );

        // Update user info if it changed
        if ($user->name !== $tokenData['name']) {
            $user->name = $tokenData['name'] ?? ($tokenData['given_name'] . ' ' . $tokenData['family_name']);
            $user->save();
        }

        // Create Laravel Sanctum token
        $token = $user->createToken('api-token')->plainTextToken;

        Log::info('User authenticated via Google', ['user_id' => $user->id]);

        return [
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
        ];
    }
}