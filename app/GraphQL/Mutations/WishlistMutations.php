<?php

namespace App\GraphQL\Mutations;

use App\Models\Wishlist;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class WishlistMutations
{
    /**
     * Add an item to user's wishlist
     */
    public function addItemToWishlist($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $user = Auth::guard('sanctum')->user();

        if (! $user) {
            throw new \Exception('Unauthenticated');
        }

        $entityId = $args['entity_id'];
        $variantId = $args['variant_id'] ?? null;
        $parentCollectionId = $args['parent_collection_id'] ?? null;

        // Note: entity_id references Supabase entity UUID - no local validation possible

        // Check if entity is already in user's collection
        $alreadyOwned = $user->userItems()->where('entity_id', $entityId)->exists();
        if ($alreadyOwned) {
            throw new \Exception('Item is already in your collection');
        }

        // Check if entity is already in wishlist (in the same collection with same variant)
        $existingWishlist = Wishlist::where('user_id', $user->id)
            ->where('entity_id', $entityId)
            ->where('variant_id', $variantId)
            ->where('parent_collection_id', $parentCollectionId)
            ->first();

        if ($existingWishlist) {
            return $existingWishlist;
        }

        // Create wishlist entry
        $wishlist = Wishlist::create([
            'user_id' => $user->id,
            'entity_id' => $entityId,
            'variant_id' => $variantId,
            'parent_collection_id' => $parentCollectionId,
        ]);

        Log::info('Item added to wishlist', [
            'user_id' => $user->id,
            'entity_id' => $entityId,
            'parent_collection_id' => $parentCollectionId,
        ]);

        return $wishlist->load('user');
    }

    /**
     * Remove an item from user's wishlist
     */
    public function removeItemFromWishlist($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $user = Auth::guard('sanctum')->user();

        if (! $user) {
            throw new \Exception('Unauthenticated');
        }

        $wishlist = Wishlist::where('user_id', $user->id)
            ->where('entity_id', $args['entity_id'])
            ->first();

        if (! $wishlist) {
            throw new \Exception('Item not found in wishlist');
        }

        $wishlist->delete();

        Log::info('Item removed from wishlist', ['user_id' => $user->id, 'entity_id' => $args['entity_id']]);

        return 'Item removed from wishlist successfully';
    }
}
