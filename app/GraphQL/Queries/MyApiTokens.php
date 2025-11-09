<?php

namespace App\GraphQL\Queries;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

class MyApiTokens
{
    /**
     * List all API tokens for the authenticated user.
     *
     * @param  null  $_
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function __invoke($_, array $args)
    {
        /** @var User $user */
        $user = Auth::guard('sanctum')->user();

        if (! $user) {
            throw new \Exception('User not authenticated');
        }

        return $user->apiTokens()->orderBy('created_at', 'desc')->get();
    }
}
