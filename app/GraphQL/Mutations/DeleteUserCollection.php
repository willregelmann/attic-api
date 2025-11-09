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
        $deleteContents = $args['delete_contents'];

        // Find collection and verify ownership
        $collection = UserCollection::where('id', $collectionId)
            ->where('user_id', $user->id)
            ->first();

        if (!$collection) {
            throw new UserError('Collection not found or access denied');
        }

        DB::transaction(function () use ($collection, $deleteContents) {
            if ($deleteContents) {
                // Delete all descendant collections recursively
                $this->deleteDescendantCollections($collection->id);

                // Move all items/wishlists to root (preserve user data)
                $this->moveContentsToRoot($collection->id);
            } else {
                // Move contents to parent collection
                $newParentId = $collection->parent_collection_id;

                UserCollection::where('parent_collection_id', $collection->id)
                    ->update(['parent_collection_id' => $newParentId]);

                UserItem::where('parent_collection_id', $collection->id)
                    ->update(['parent_collection_id' => $newParentId]);

                Wishlist::where('parent_collection_id', $collection->id)
                    ->update(['parent_collection_id' => $newParentId]);
            }

            // Delete the collection
            $collection->delete();
        });

        return [
            'success' => true,
            'message' => 'Collection deleted successfully',
        ];
    }

    protected function deleteDescendantCollections(string $collectionId): void
    {
        $children = UserCollection::where('parent_collection_id', $collectionId)->get();

        foreach ($children as $child) {
            $this->deleteDescendantCollections($child->id);
            $child->delete();
        }
    }

    protected function moveContentsToRoot(string $collectionId): void
    {
        // Get all descendant collection IDs
        $descendantIds = $this->getDescendantIds($collectionId);
        $descendantIds[] = $collectionId;

        // Move all items and wishlists from this collection and descendants to root
        UserItem::whereIn('parent_collection_id', $descendantIds)
            ->update(['parent_collection_id' => null]);

        Wishlist::whereIn('parent_collection_id', $descendantIds)
            ->update(['parent_collection_id' => null]);
    }

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
