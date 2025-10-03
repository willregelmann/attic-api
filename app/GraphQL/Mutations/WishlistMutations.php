<?php

namespace App\GraphQL\Mutations;

use App\Models\Item;
use App\Models\Wishlist;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class WishlistMutations
{
    /**
     * Add an item to user's wishlist
     */
    public function addItemToWishlist($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $user = Auth::guard('sanctum')->user();

        if (!$user) {
            throw new \Exception('Unauthenticated');
        }

        $item = Item::find($args['item_id']);

        if (!$item) {
            throw new \Exception('Item not found');
        }

        // Check if item is already in user's collection
        $alreadyOwned = $user->items()->where('item_id', $item->id)->exists();
        if ($alreadyOwned) {
            throw new \Exception('Item is already in your collection');
        }

        // Check if item is already in wishlist
        $existingWishlist = Wishlist::where('user_id', $user->id)
            ->where('item_id', $item->id)
            ->first();

        if ($existingWishlist) {
            return $existingWishlist;
        }

        // Create wishlist entry
        $wishlist = Wishlist::create([
            'user_id' => $user->id,
            'item_id' => $item->id,
        ]);

        Log::info('Item added to wishlist', ['user_id' => $user->id, 'item_id' => $item->id]);

        return $wishlist->load('item', 'user');
    }

    /**
     * Remove an item from user's wishlist
     */
    public function removeItemFromWishlist($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $user = Auth::guard('sanctum')->user();

        if (!$user) {
            throw new \Exception('Unauthenticated');
        }

        $wishlist = Wishlist::where('user_id', $user->id)
            ->where('item_id', $args['item_id'])
            ->first();

        if (!$wishlist) {
            throw new \Exception('Item not found in wishlist');
        }

        $wishlist->delete();

        Log::info('Item removed from wishlist', ['user_id' => $user->id, 'item_id' => $args['item_id']]);

        return 'Item removed from wishlist successfully';
    }
}
