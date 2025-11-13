<?php

namespace App\GraphQL\Mutations;

use App\Models\UserCollection;
use App\Models\UserItem;
use App\Models\Wishlist;
use GraphQL\Error\UserError;
use Illuminate\Support\Facades\DB;

class DeleteUserCollection
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

        $itemsDeleted = 0;
        $subcollectionsDeleted = 0;

        DB::transaction(function () use ($collection, &$itemsDeleted, &$subcollectionsDeleted) {
            // Count and soft delete all descendant collections recursively
            $subcollectionsDeleted = $this->softDeleteDescendantCollections($collection->id);

            // Count and soft delete all items and wishlists in this collection and descendants
            $itemsDeleted = $this->softDeleteDescendantItems($collection->id);

            // Soft delete the collection itself
            $collection->delete();
        });

        return [
            'success' => true,
            'deleted_collection_id' => $collectionId,
            'items_deleted' => $itemsDeleted,
            'subcollections_deleted' => $subcollectionsDeleted,
        ];
    }

    /**
     * Recursively soft delete all descendant collections
     * Returns count of deleted collections
     */
    protected function softDeleteDescendantCollections(string $collectionId): int
    {
        $count = 0;
        $children = UserCollection::where('parent_collection_id', $collectionId)->get();

        foreach ($children as $child) {
            // Recursively delete descendants first
            $count += $this->softDeleteDescendantCollections($child->id);

            // Soft delete this child
            $child->delete();
            $count++;
        }

        return $count;
    }

    /**
     * Soft delete all items and wishlists in this collection and its descendants
     * Returns count of deleted items (UserItems + Wishlists)
     */
    protected function softDeleteDescendantItems(string $collectionId): int
    {
        // Get all descendant collection IDs (including this one)
        $descendantIds = $this->getDescendantIds($collectionId);
        $descendantIds[] = $collectionId;

        // Count and soft delete items
        $itemCount = UserItem::whereIn('parent_collection_id', $descendantIds)->count();
        UserItem::whereIn('parent_collection_id', $descendantIds)->delete();

        // Count and soft delete wishlists
        $wishlistCount = Wishlist::whereIn('parent_collection_id', $descendantIds)->count();
        Wishlist::whereIn('parent_collection_id', $descendantIds)->delete();

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
