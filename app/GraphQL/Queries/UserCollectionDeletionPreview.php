<?php

namespace App\GraphQL\Queries;

use App\Models\UserCollection;
use App\Models\UserItem;
use App\Models\Wishlist;
use GraphQL\Error\UserError;

class UserCollectionDeletionPreview
{
    public function __invoke($rootValue, array $args)
    {
        $user = auth()->user();
        $collectionId = $args['id'];

        // Find collection and verify ownership
        $collection = UserCollection::where('id', $collectionId)
            ->where('user_id', $user->id)
            ->first();

        if (!$collection) {
            throw new UserError('Collection not found or access denied');
        }

        // Count descendant collections
        $totalSubcollections = $this->countDescendantCollections($collection->id);

        // Count descendant items (UserItems + Wishlists)
        $totalItems = $this->countDescendantItems($collection->id);

        return [
            'collection_id' => $collection->id,
            'collection_name' => $collection->name,
            'total_items' => $totalItems,
            'total_subcollections' => $totalSubcollections,
        ];
    }

    /**
     * Recursively count all descendant collections
     */
    protected function countDescendantCollections(string $collectionId): int
    {
        $count = 0;
        $children = UserCollection::where('parent_collection_id', $collectionId)->get();

        foreach ($children as $child) {
            $count++; // Count this child
            $count += $this->countDescendantCollections($child->id); // Count its descendants
        }

        return $count;
    }

    /**
     * Count all items and wishlists in this collection and its descendants
     */
    protected function countDescendantItems(string $collectionId): int
    {
        // Get all descendant collection IDs (including this one)
        $descendantIds = $this->getDescendantIds($collectionId);
        $descendantIds[] = $collectionId;

        // Count items and wishlists
        $itemCount = UserItem::whereIn('parent_collection_id', $descendantIds)->count();
        $wishlistCount = Wishlist::whereIn('parent_collection_id', $descendantIds)->count();

        return $itemCount + $wishlistCount;
    }

    /**
     * Get all descendant collection IDs recursively
     */
    protected function getDescendantIds(string $collectionId): array
    {
        $descendants = [];
        $children = UserCollection::where('parent_collection_id', $collectionId)->get();

        foreach ($children as $child) {
            $descendants[] = $child->id;
            $descendants = array_merge($descendants, $this->getDescendantIds($child->id));
        }

        return $descendants;
    }
}
