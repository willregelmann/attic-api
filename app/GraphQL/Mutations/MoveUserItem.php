<?php

namespace App\GraphQL\Mutations;

use App\Models\UserItem;
use App\Models\UserCollection;
use GraphQL\Error\UserError;

class MoveUserItem
{
    public function __invoke($rootValue, array $args)
    {
        $user = auth()->user();
        $itemId = $args['item_id'];
        $newParentId = $args['new_parent_collection_id'] ?? null;

        // Find item and verify ownership
        $item = UserItem::where('id', $itemId)
            ->where('user_id', $user->id)
            ->first();

        if (!$item) {
            throw new UserError('Item not found or access denied');
        }

        // Validate collection ownership if provided
        if ($newParentId) {
            $collection = UserCollection::where('id', $newParentId)
                ->where('user_id', $user->id)
                ->first();

            if (!$collection) {
                throw new UserError('Collection not found or access denied');
            }
        }

        // Update item
        $item->parent_collection_id = $newParentId;
        $item->save();

        return $item;
    }
}
