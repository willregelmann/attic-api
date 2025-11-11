<?php

namespace App\GraphQL\Mutations;

use App\Models\UserItem;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class UserItemMutations
{
    /**
     * Add an item to user's collection
     */
    public function addItemToMyCollection($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        Log::info('Adding item to collection', $args);

        // Get the authenticated user
        $user = Auth::guard('sanctum')->user();

        if (! $user) {
            throw new \Exception('Unauthenticated');
        }

        $userId = $user->id;

        // Note: entity_id references Supabase entity UUID - no local validation possible

        // Create the UserItem record
        $userItem = new UserItem;
        $userItem->user_id = $userId;
        $userItem->entity_id = $args['entity_id'];
        $userItem->metadata = $args['metadata'] ?? null;
        $userItem->notes = $args['notes'] ?? null;
        $userItem->save();

        // Load user relationship for GraphQL response
        $userItem->load(['user']);

        Log::info('UserItem created', ['id' => $userItem->id]);

        return $userItem;
    }

    /**
     * Update user's item metadata
     */
    public function updateMyItem($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $user = Auth::guard('sanctum')->user();

        if (! $user) {
            throw new \Exception('Unauthenticated');
        }

        $userItem = UserItem::where('user_id', $user->id)
            ->where('id', $args['user_item_id'])
            ->firstOrFail();

        if (isset($args['metadata'])) {
            $userItem->metadata = array_merge(
                $userItem->metadata ?? [],
                $args['metadata']
            );
        }

        if (isset($args['notes'])) {
            $userItem->notes = $args['notes'];
        }

        $userItem->save();

        $userItem->load(['user']);

        return $userItem;
    }

    /**
     * Remove item from user's collection
     */
    public function removeItemFromMyCollection($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $user = Auth::guard('sanctum')->user();

        if (! $user) {
            throw new \Exception('Unauthenticated');
        }

        $deleted = UserItem::where('user_id', $user->id)
            ->where('entity_id', $args['entity_id'])
            ->delete();

        if ($deleted === 0) {
            throw new \Exception('Item not found in your collection');
        }

        return 'Item removed from collection';
    }
}
