<?php

namespace App\GraphQL\Queries;

use App\Models\UserItem;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Support\Facades\Auth;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class MyItems
{
    /**
     * Get all items owned by the authenticated user
     * Returns UserItem records with entity_id references to Database of Things
     */
    public function __invoke($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $user = Auth::guard('sanctum')->user();

        if (! $user) {
            throw new \Exception('Unauthenticated');
        }

        return UserItem::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
