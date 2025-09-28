<?php

namespace App\GraphQL\Mutations;

use App\Models\Item;
use App\Models\UserItem;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use GraphQL\Type\Definition\ResolveInfo;
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

        if (!$user) {
            throw new \Exception('Unauthenticated');
        }

        $userId = $user->id;

        // Check if the item exists
        $item = Item::find($args['item_id']);
        if (!$item) {
            throw new \Exception('Item not found');
        }

        // Create the UserItem record
        $userItem = new UserItem();
        $userItem->user_id = $userId;
        $userItem->item_id = $args['item_id'];
        $userItem->metadata = $args['metadata'] ?? null;
        $userItem->save();

        // Load relationships for GraphQL response
        $userItem->load(['user', 'item']);

        Log::info('UserItem created', ['id' => $userItem->id]);

        return $userItem;
    }

    /**
     * Update user's item metadata
     */
    public function updateMyItem($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $user = Auth::guard('sanctum')->user();

        if (!$user) {
            throw new \Exception('Unauthenticated');
        }

        $userItem = UserItem::where('user_id', $user->id)
            ->where('item_id', $args['item_id'])
            ->firstOrFail();

        $userItem->metadata = array_merge(
            $userItem->metadata ?? [],
            $args['metadata']
        );
        $userItem->save();

        $userItem->load(['user', 'item']);

        return $userItem;
    }
}