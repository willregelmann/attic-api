<?php

namespace App\Services;

use App\Models\UserCollection;
use App\Models\UserItem;
use App\Models\Wishlist;
use Illuminate\Support\Collection;

class UserCollectionService
{
    private DatabaseOfThingsService $dbotService;

    public function __construct(DatabaseOfThingsService $dbotService)
    {
        $this->dbotService = $dbotService;
    }
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

    /**
     * Calculate simple progress for a collection (direct children only)
     *
     * @param string $collectionId
     * @return array{owned_count: int, wishlist_count: int, total_count: int, percentage: float}
     */
    public function calculateSimpleProgress(string $collectionId): array
    {
        $ownedCount = UserItem::where('parent_collection_id', $collectionId)->count();
        $wishlistCount = Wishlist::where('parent_collection_id', $collectionId)->count();
        $totalCount = $ownedCount + $wishlistCount;

        $percentage = $totalCount > 0
            ? round(($ownedCount / $totalCount) * 100, 2)
            : 0;

        return [
            'owned_count' => $ownedCount,
            'wishlist_count' => $wishlistCount,
            'total_count' => $totalCount,
            'percentage' => $percentage,
        ];
    }

    /**
     * Calculate progress for a collection (includes all descendants recursively)
     *
     * @param string $collectionId
     * @return array{owned_count: int, wishlist_count: int, total_count: int, percentage: float}
     */
    public function calculateProgress(string $collectionId): array
    {
        $collection = UserCollection::find($collectionId);
        $ownedCount = $this->countOwnedItemsRecursive($collectionId);
        $wishlistCount = $this->countWishlistedItemsRecursive($collectionId);

        // For linked collections, total = DBoT collection size
        // For regular collections, total = owned + wishlisted
        if ($collection && $collection->linked_dbot_collection_id) {
            // Fetch DBoT collection to get true total
            $dbotResponse = $this->dbotService->getCollectionItems($collection->linked_dbot_collection_id);
            $totalCount = count($dbotResponse['items'] ?? []);
        } else {
            $totalCount = $ownedCount + $wishlistCount;
        }

        $percentage = $totalCount > 0
            ? round(($ownedCount / $totalCount) * 100, 2)
            : 0;

        return [
            'owned_count' => $ownedCount,
            'wishlist_count' => $wishlistCount,
            'total_count' => $totalCount,
            'percentage' => $percentage,
        ];
    }

    /**
     * Count owned items recursively including all descendants
     *
     * @param string $collectionId
     * @return int
     */
    protected function countOwnedItemsRecursive(string $collectionId): int
    {
        // Count direct children
        $count = UserItem::where('parent_collection_id', $collectionId)->count();

        // Get subcollections and recursively count their items
        $subcollections = UserCollection::where('parent_collection_id', $collectionId)->get();
        foreach ($subcollections as $subcollection) {
            $count += $this->countOwnedItemsRecursive($subcollection->id);
        }

        return $count;
    }

    /**
     * Count wishlisted items recursively including all descendants
     *
     * @param string $collectionId
     * @return int
     */
    protected function countWishlistedItemsRecursive(string $collectionId): int
    {
        // Count direct children
        $count = Wishlist::where('parent_collection_id', $collectionId)->count();

        // Get subcollections and recursively count their items
        $subcollections = UserCollection::where('parent_collection_id', $collectionId)->get();
        foreach ($subcollections as $subcollection) {
            $count += $this->countWishlistedItemsRecursive($subcollection->id);
        }

        return $count;
    }

    /**
     * Get items from a DBoT collection that should be added to wishlist.
     * Filters out items already owned or wishlisted in the target collection.
     *
     * @param string $userId
     * @param string $dbotCollectionId
     * @param string|null $targetCollectionId
     * @return array ['items_to_add' => array, 'already_owned_count' => int, 'already_wishlisted_count' => int]
     */
    public function getItemsToAddToWishlist(
        string $userId,
        string $dbotCollectionId,
        ?string $targetCollectionId = null
    ): array {
        // 1. Get all items from DBoT collection
        $dbotResult = $this->dbotService->getCollectionItems($dbotCollectionId);
        $dbotItems = $dbotResult['items'] ?? [];

        // Extract entity IDs from DBoT items
        $dbotEntityIds = array_map(fn($item) => $item['entity']['id'], $dbotItems);

        // If no target collection specified, return all items
        if ($targetCollectionId === null) {
            return [
                'items_to_add' => $dbotItems,
                'already_owned_count' => 0,
                'already_wishlisted_count' => 0,
            ];
        }

        // 2. Get existing items in target collection
        $existingOwnedIds = UserItem::where('user_id', $userId)
            ->where('parent_collection_id', $targetCollectionId)
            ->whereIn('entity_id', $dbotEntityIds)
            ->pluck('entity_id')
            ->toArray();

        // 3. Get existing wishlisted items in target collection
        $existingWishlistedIds = Wishlist::where('user_id', $userId)
            ->where('parent_collection_id', $targetCollectionId)
            ->whereIn('entity_id', $dbotEntityIds)
            ->pluck('entity_id')
            ->toArray();

        // 4. Filter out items already present in target
        // Use array_flip for O(1) lookup instead of O(n) with in_array
        $excludedIds = array_flip(array_merge($existingOwnedIds, $existingWishlistedIds));
        $filteredItems = array_filter($dbotItems, function ($item) use ($excludedIds) {
            return !isset($excludedIds[$item['entity']['id']]);
        });

        // 5. Return result with counts
        return [
            'items_to_add' => array_values($filteredItems), // Reset array keys
            'already_owned_count' => count($existingOwnedIds),
            'already_wishlisted_count' => count($existingWishlistedIds),
        ];
    }

    /**
     * Create a tracked collection (linked to DBoT collection).
     *
     * @param string $userId
     * @param string $dbotCollectionId
     * @param string $name
     * @param string|null $parentCollectionId
     * @return UserCollection
     * @throws \Exception If DBoT collection not found
     */
    public function createTrackedCollection(
        string $userId,
        string $dbotCollectionId,
        string $name,
        ?string $parentCollectionId = null
    ): UserCollection
    {
        // Validate DBoT collection exists
        $dbotCollection = $this->dbotService->getCollection($dbotCollectionId);

        if ($dbotCollection === null) {
            throw new \Exception('DBoT collection not found');
        }

        // Create user_collection record
        return UserCollection::create([
            'user_id' => $userId,
            'name' => $name,
            'linked_dbot_collection_id' => $dbotCollectionId,
            'parent_collection_id' => $parentCollectionId,
        ]);
    }

    /**
     * Bulk add items to wishlist.
     *
     * @param string $userId
     * @param array $entityIds Array of entity IDs to add
     * @param string|null $parentCollectionId
     * @return array ['items_added' => int, 'items_skipped' => int]
     */
    public function bulkAddToWishlist(
        string $userId,
        array $entityIds,
        ?string $parentCollectionId = null
    ): array
    {
        // Handle empty array case
        if (empty($entityIds)) {
            return [
                'items_added' => 0,
                'items_skipped' => 0,
            ];
        }

        return \DB::transaction(function () use ($userId, $entityIds, $parentCollectionId) {
            // 1. Query existing wishlists to find duplicates
            $existingWishlists = Wishlist::where('user_id', $userId)
                ->whereIn('entity_id', $entityIds)
                ->where('parent_collection_id', $parentCollectionId)
                ->pluck('entity_id')
                ->toArray();

            // 2. Filter out existing items
            $itemsToAdd = array_diff($entityIds, $existingWishlists);

            // 3. Bulk insert new wishlist records
            if (!empty($itemsToAdd)) {
                $wishlistRecords = [];
                foreach ($itemsToAdd as $entityId) {
                    $wishlistRecords[] = [
                        'id' => (string) \Illuminate\Support\Str::uuid(),
                        'user_id' => $userId,
                        'entity_id' => $entityId,
                        'parent_collection_id' => $parentCollectionId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                Wishlist::insert($wishlistRecords);
            }

            // 4. Return counts
            return [
                'items_added' => count($itemsToAdd),
                'items_skipped' => count($entityIds) - count($itemsToAdd),
            ];
        });
    }
}
