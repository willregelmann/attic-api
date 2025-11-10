<?php

namespace App\GraphQL\Mutations;

use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class FavoriteMutations
{
    /**
     * Add a collection to user's favorites
     * Note: entity_id references the Database of Things API, not local items table
     */
    public function favoriteCollection($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $user = Auth::guard('sanctum')->user();

        if (! $user) {
            throw new \Exception('Unauthenticated');
        }

        $entityId = $args['entity_id'];

        // Attach the collection to user's favorites (many-to-many relationship)
        // No validation needed - entity_id references external Database of Things API
        $user->favoriteCollections()->syncWithoutDetaching([$entityId]);

        Log::info('Collection favorited', ['user_id' => $user->id, 'entity_id' => $entityId]);

        // Return the user with updated favorites
        return $user->load('favoriteCollections');
    }

    /**
     * Remove a collection from user's favorites
     * Note: entity_id references the Database of Things API, not local items table
     */
    public function unfavoriteCollection($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $user = Auth::guard('sanctum')->user();

        if (! $user) {
            throw new \Exception('Unauthenticated');
        }

        $entityId = $args['entity_id'];

        // Detach the collection from user's favorites
        // No validation needed - entity_id references external Database of Things API
        $user->favoriteCollections()->detach($entityId);

        Log::info('Collection unfavorited', ['user_id' => $user->id, 'entity_id' => $entityId]);

        // Return the user with updated favorites
        return $user->load('favoriteCollections');
    }
}
