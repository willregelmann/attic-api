<?php

namespace App\Services;

use App\Models\UserCollection;
use Illuminate\Support\Collection;

class UserCollectionService
{
    /**
     * Get collection tree for user
     *
     * @param string $userId
     * @param string|null $parentId
     * @return Collection
     */
    public function getCollectionTree(string $userId, ?string $parentId = null): Collection
    {
        return UserCollection::where('user_id', $userId)
            ->where('parent_collection_id', $parentId)
            ->orderBy('name')
            ->get();
    }

    /**
     * Validate that a collection can be moved to a new parent
     *
     * @param string $collectionId
     * @param string|null $newParentId
     * @return void
     * @throws \InvalidArgumentException
     */
    public function validateMove(string $collectionId, ?string $newParentId): void
    {
        // Cannot move to self
        if ($collectionId === $newParentId) {
            throw new \InvalidArgumentException('Cannot move collection into itself');
        }

        // Moving to root is always valid
        if ($newParentId === null) {
            return;
        }

        // Cannot move into own descendant
        $descendants = $this->getDescendantIds($collectionId);
        if (in_array($newParentId, $descendants)) {
            throw new \InvalidArgumentException('Cannot move collection into its own children');
        }
    }

    /**
     * Get all descendant collection IDs recursively
     *
     * @param string $collectionId
     * @return array
     */
    protected function getDescendantIds(string $collectionId): array
    {
        $descendants = [];
        $children = UserCollection::where('parent_collection_id', $collectionId)->get();

        foreach ($children as $child) {
            $descendants[] = $child->id;
            // Recursively get grandchildren
            $descendants = array_merge($descendants, $this->getDescendantIds($child->id));
        }

        return $descendants;
    }
}
