<?php

namespace App\GraphQL\Mutations;

use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class ApiTokenMutations
{
    /**
     * Create a new API token for the authenticated user.
     *
     * @param  null  $_
     * @return array
     */
    public function createToken($_, array $args)
    {
        /** @var User $user */
        $user = Auth::guard('sanctum')->user();

        if (! $user) {
            throw new \Exception('User not authenticated');
        }

        $result = ApiToken::createTokenForUser(
            $user,
            $args['name'],
            $args['abilities'] ?? ['*'],
            isset($args['expires_at']) ? new \DateTime($args['expires_at']) : null
        );

        return [
            'token' => $result['token'],
            'plainTextToken' => $result['plainTextToken'],
        ];
    }

    /**
     * List all API tokens for the authenticated user.
     *
     * @param  null  $_
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function listTokens($_, array $args)
    {
        /** @var User $user */
        $user = Auth::guard('sanctum')->user();

        if (! $user) {
            throw new \Exception('User not authenticated');
        }

        return $user->apiTokens()->orderBy('created_at', 'desc')->get();
    }

    /**
     * Revoke an API token.
     *
     * @param  null  $_
     * @return string
     */
    public function revokeToken($_, array $args)
    {
        /** @var User $user */
        $user = Auth::guard('sanctum')->user();

        if (! $user) {
            throw new \Exception('User not authenticated');
        }

        $token = $user->apiTokens()->find($args['id']);

        if (! $token) {
            throw new \Exception('Token not found');
        }

        $token->delete();

        return 'Token revoked successfully';
    }
}
