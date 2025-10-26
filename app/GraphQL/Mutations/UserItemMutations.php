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

        // Note: entity_id references Supabase entity UUID - no local validation possible

        // Create the UserItem record
        $userItem = new UserItem();
        $userItem->user_id = $userId;
        $userItem->entity_id = $args['entity_id'];
        $userItem->metadata = $args['metadata'] ?? null;
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

        if (!$user) {
            throw new \Exception('Unauthenticated');
        }

        $userItem = UserItem::where('user_id', $user->id)
            ->where('entity_id', $args['entity_id'])
            ->firstOrFail();

        $userItem->metadata = array_merge(
            $userItem->metadata ?? [],
            $args['metadata']
        );
        $userItem->save();

        $userItem->load(['user']);

        return $userItem;
    }
}