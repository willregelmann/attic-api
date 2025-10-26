<?php

namespace App\GraphQL\Queries;

use App\Models\UserItem;
use Illuminate\Support\Facades\Auth;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class MyCollectionItems
{
    /**
     * DEPRECATED: This query needs to be reimplemented with Supabase integration
     *
     * Previously checked which items in a local collection were owned by the user.
     * Now that collections are in Supabase:
     * 1. Fetch collection items from Supabase via SupabaseGraphQLService
     * 2. Check which entity_ids exist in user_items table for this user
     * 3. Return matching UserItem records
     *
     * For now, returns all user items (not filtered by collection)
     */
    public function __invoke($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $user = Auth::guard('sanctum')->user();

        if (!$user) {
            throw new \Exception('Unauthenticated');
        }

        // TODO: Integrate with Supabase to filter by collection_id
        // For now, return all user items
        return UserItem::where('user_id', $user->id)->get();
    }
}