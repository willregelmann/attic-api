<?php

namespace App\GraphQL\Queries;

use App\Models\Item;
use App\Models\UserItem;
use Illuminate\Support\Facades\Auth;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class MyCollectionStats
{
    /**
     * DEPRECATED: This query needs to be reimplemented with Supabase integration
     *
     * Previously calculated collection completion stats based on local data.
     * Now that collections are in Supabase:
     * 1. Fetch total items in collection from Supabase via SupabaseGraphQLService
     * 2. Count how many of those entity_ids exist in user_items table for this user
     * 3. Calculate completion percentage
     *
     * For now, returns placeholder stats
     */
    public function __invoke($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $user = Auth::guard('sanctum')->user();

        if (!$user) {
            throw new \Exception('Unauthenticated');
        }

        // TODO: Integrate with Supabase to get collection items and calculate stats
        // For now, return basic stats based on all user items
        $ownedItems = UserItem::where('user_id', $user->id)->count();

        return [
            'totalItems' => 0, // TODO: Fetch from Supabase
            'catalogedItems' => 0, // TODO: Fetch from Supabase
            'ownedItems' => $ownedItems,
            'completionPercentage' => 0,
        ];
    }
}