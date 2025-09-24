<?php

namespace App\GraphQL\Mutations;

use App\Models\Item;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class FavoriteMutations
{
    /**
     * Add a collection to user's favorites
     */
    public function favoriteCollection($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $user = Auth::guard('sanctum')->user();

        if (!$user) {
            throw new \Exception('Unauthenticated');
        }

        $collection = Item::where('id', $args['collection_id'])
            ->where('type', 'collection')
            ->first();

        if (!$collection) {
            throw new \Exception('Collection not found');
        }

        // Attach the collection to user's favorites (many-to-many relationship)
        $user->favoriteCollections()->syncWithoutDetaching([$collection->id]);

        Log::info('Collection favorited', ['user_id' => $user->id, 'collection_id' => $collection->id]);

        // Return the user with updated favorites
        return $user->load('favoriteCollections');
    }

    /**
     * Remove a collection from user's favorites
     */
    public function unfavoriteCollection($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $user = Auth::guard('sanctum')->user();

        if (!$user) {
            throw new \Exception('Unauthenticated');
        }

        $collection = Item::where('id', $args['collection_id'])
            ->where('type', 'collection')
            ->first();

        if (!$collection) {
            throw new \Exception('Collection not found');
        }

        // Detach the collection from user's favorites
        $user->favoriteCollections()->detach($collection->id);

        Log::info('Collection unfavorited', ['user_id' => $user->id, 'collection_id' => $collection->id]);

        // Return the user with updated favorites
        return $user->load('favoriteCollections');
    }
}