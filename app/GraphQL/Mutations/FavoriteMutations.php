<?php

namespace App\GraphQL\Mutations;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class FavoriteMutations
{
    /**
     * Add a collection to user's favorites
     * Note: collection_id references the Database of Things API, not local items table
     */
    public function favoriteCollection($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $user = Auth::guard('sanctum')->user();

        if (!$user) {
            throw new \Exception('Unauthenticated');
        }

        $collectionId = $args['collection_id'];

        // Attach the collection to user's favorites (many-to-many relationship)
        // No validation needed - collection_id references external Database of Things API
        $user->favoriteCollections()->syncWithoutDetaching([$collectionId]);

        Log::info('Collection favorited', ['user_id' => $user->id, 'collection_id' => $collectionId]);

        // Return the user with updated favorites
        return $user->load('favoriteCollections');
    }

    /**
     * Remove a collection from user's favorites
     * Note: collection_id references the Database of Things API, not local items table
     */
    public function unfavoriteCollection($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $user = Auth::guard('sanctum')->user();

        if (!$user) {
            throw new \Exception('Unauthenticated');
        }

        $collectionId = $args['collection_id'];

        // Detach the collection from user's favorites
        // No validation needed - collection_id references external Database of Things API
        $user->favoriteCollections()->detach($collectionId);

        Log::info('Collection unfavorited', ['user_id' => $user->id, 'collection_id' => $collectionId]);

        // Return the user with updated favorites
        return $user->load('favoriteCollections');
    }
}