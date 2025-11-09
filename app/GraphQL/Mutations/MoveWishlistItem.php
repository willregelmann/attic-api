<?php

namespace App\GraphQL\Mutations;

use App\Models\Wishlist;
use App\Models\UserCollection;
use GraphQL\Error\UserError;

class MoveWishlistItem
{
    public function __invoke($rootValue, array $args)
    {
        $user = auth()->user();
        $wishlistId = $args['wishlist_id'];
        $newParentId = $args['new_parent_collection_id'] ?? null;

        // Find wishlist item and verify ownership
        $wishlist = Wishlist::where('id', $wishlistId)
            ->where('user_id', $user->id)
            ->first();

        if (!$wishlist) {
            throw new UserError('Wishlist item not found or access denied');
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

        // Update wishlist item
        $wishlist->parent_collection_id = $newParentId;
        $wishlist->save();

        return $wishlist;
    }
}
