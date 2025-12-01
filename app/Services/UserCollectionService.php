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
     * @return array{owned_count: int, wishlist_count: int, total_count: int, percentage: float}
     */
    public function calculateSimpleProgress(string $collectionId): array
    {
        // Count unique entity_ids instead of total items
        $ownedCount = UserItem::where('parent_collection_id', $collectionId)
            ->distinct('entity_id')
            ->count('entity_id');
        $wishlistCount = Wishlist::where('parent_collection_id', $collectionId)
            ->distinct('entity_id')
            ->count('entity_id');
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
     * @param  DbotDataCache|null  $dbotCache  Optional cache for pre-fetched DBoT data
     * @return array{owned_count: int, wishlist_count: int, total_count: int, percentage: float}
     */
    public function calculateProgress(string $collectionId, ?DbotDataCache $dbotCache = null): array
    {
        $collection = UserCollection::find($collectionId);
        $ownedCount = $this->countOwnedItemsRecursive($collectionId);
        $wishlistCount = $this->countWishlistedItemsRecursive($collectionId);

        // Calculate total count (using cache if available)
        $totalCount = $this->calculateTotalCountRecursive($collectionId, $dbotCache);

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
     * Calculate total count recursively including all child collections
     *
     * @param  DbotDataCache|null  $dbotCache  Optional cache for pre-fetched DBoT data
     */
    protected function calculateTotalCountRecursive(string $collectionId, ?DbotDataCache $dbotCache = null): int
    {
        $collection = UserCollection::find($collectionId);
        $totalCount = 0;

        // If this collection is linked to DBoT, add its DBoT size
        if ($collection && $collection->linked_dbot_collection_id) {
            // Try to use cached item count first (from MyCollectionTree pre-fetch)
            $cachedCount = $dbotCache?->getCollectionItemCount($collection->linked_dbot_collection_id);

            if ($cachedCount !== null) {
                $totalCount += $cachedCount;
            } else {
                // Fallback: fetch from DBoT (for non-tree queries)
                $dbotResponse = $this->dbotService->getCollectionItems(
                    $collection->linked_dbot_collection_id,
                    PHP_INT_MAX  // Fetch all items, no limit
                );
                $totalCount += count($dbotResponse['items'] ?? []);
            }
        } else {
            // For non-linked collections at this level, count unique entity_ids
            $ownedCount = UserItem::where('parent_collection_id', $collectionId)
                ->distinct('entity_id')
                ->count('entity_id');
            $wishlistCount = Wishlist::where('parent_collection_id', $collectionId)
                ->distinct('entity_id')
                ->count('entity_id');
            $totalCount += $ownedCount + $wishlistCount;
        }

        // Recursively add totals from child collections
        $subcollections = UserCollection::where('parent_collection_id', $collectionId)->get();
        foreach ($subcollections as $subcollection) {
            $totalCount += $this->calculateTotalCountRecursive($subcollection->id, $dbotCache);
        }

        return $totalCount;
    }

    /**
     * Count owned items recursively including all descendants
     */
    protected function countOwnedItemsRecursive(string $collectionId): int
    {
        // Count unique entity_ids in direct children
        $count = UserItem::where('parent_collection_id', $collectionId)
            ->distinct('entity_id')
            ->count('entity_id');

        // Get subcollections and recursively count their items
        $subcollections = UserCollection::where('parent_collection_id', $collectionId)->get();
        foreach ($subcollections as $subcollection) {
            $count += $this->countOwnedItemsRecursive($subcollection->id);
        }

        return $count;
    }

    /**
     * Count wishlisted items recursively including all descendants
     */
    protected function countWishlistedItemsRecursive(string $collectionId): int
    {
        // Count unique entity_ids in direct children
        $count = Wishlist::where('parent_collection_id', $collectionId)
            ->distinct('entity_id')
            ->count('entity_id');

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
     * @return array ['items_to_add' => array, 'already_owned_count' => int, 'already_wishlisted_count' => int]
     */
    public function getItemsToAddToWishlist(
        string $userId,
        string $dbotCollectionId,
        ?string $targetCollectionId = null
    ): array {
        // 1. Get all items from DBoT collection (fetch everything)
        $dbotResult = $this->dbotService->getCollectionItems($dbotCollectionId, PHP_INT_MAX);
        $dbotItems = $dbotResult['items'] ?? [];

        // Extract entity IDs from DBoT items
        $dbotEntityIds = array_map(fn ($item) => $item['entity']['id'], $dbotItems);

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
            return ! isset($excludedIds[$item['entity']['id']]);
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
     * @throws \Exception If DBoT collection not found
     */
    public function createTrackedCollection(
        string $userId,
        string $dbotCollectionId,
        string $name,
        ?string $parentCollectionId = null
    ): UserCollection {
        // Check if a linked collection already exists for this DBoT collection
        $existingCollection = UserCollection::where('user_id', $userId)
            ->where('linked_dbot_collection_id', $dbotCollectionId)
            ->first();

        if ($existingCollection) {
            // Return existing collection instead of creating duplicate
            return $existingCollection;
        }

        // Validate DBoT collection exists
        $dbotCollection = $this->dbotService->getCollection($dbotCollectionId);

        if ($dbotCollection === null) {
            throw new \Exception('DBoT collection not found');
        }

        // Create user_collection record
        // Note: 'type' is computed from linked_dbot_collection_id via model accessor
        return UserCollection::create([
            'user_id' => $userId,
            'name' => $name,
            'linked_dbot_collection_id' => $dbotCollectionId,
            'parent_collection_id' => $parentCollectionId,
        ]);
    }

    /**
     * Calculate progress for multiple collections efficiently (no N+1)
     *
     * This method pre-fetches all required data in bulk queries, then calculates
     * progress in memory. Much faster than calling calculateProgress per collection.
     *
     * @param  string  $userId  User ID
     * @param  array  $collectionIds  Array of collection IDs to calculate progress for
     * @param  DatabaseOfThingsService|null  $dbotService  DBoT service for linked collection counts
     * @return array<string, array{owned_count: int, wishlist_count: int, total_count: int, percentage: float}>
     */
    public function calculateBulkProgress(string $userId, array $collectionIds, ?DbotDataCache $dbotCache = null): array
    {
        if (empty($collectionIds)) {
            return [];
        }

        // 1. Pre-fetch ALL user collections (for building tree structure)
        $allCollections = UserCollection::where('user_id', $userId)
            ->get()
            ->keyBy('id');

        // 2. Build parent->children mapping for efficient tree traversal
        $childrenMap = [];
        foreach ($allCollections as $collection) {
            $parentId = $collection->parent_collection_id ?? 'root';
            if (!isset($childrenMap[$parentId])) {
                $childrenMap[$parentId] = [];
            }
            $childrenMap[$parentId][] = $collection->id;
        }

        // 3. Pre-fetch item counts per collection (one query)
        $itemCounts = UserItem::where('user_id', $userId)
            ->selectRaw('parent_collection_id, COUNT(DISTINCT entity_id) as count')
            ->groupBy('parent_collection_id')
            ->pluck('count', 'parent_collection_id')
            ->toArray();

        // 4. Pre-fetch wishlist counts per collection (one query)
        $wishlistCounts = Wishlist::where('user_id', $userId)
            ->selectRaw('parent_collection_id, COUNT(DISTINCT entity_id) as count')
            ->groupBy('parent_collection_id')
            ->pluck('count', 'parent_collection_id')
            ->toArray();

        // 5. Get DBoT item counts for linked collections (from cache or fetch)
        $linkedDbotIds = $allCollections
            ->filter(fn ($c) => $c->linked_dbot_collection_id !== null)
            ->pluck('linked_dbot_collection_id', 'id')
            ->toArray();

        $dbotCounts = [];
        if (!empty($linkedDbotIds)) {
            foreach ($linkedDbotIds as $collectionId => $dbotId) {
                // Try cache first
                $cachedCount = $dbotCache?->getCollectionItemCount($dbotId);
                if ($cachedCount !== null) {
                    $dbotCounts[$collectionId] = $cachedCount;
                }
                // If not cached, we'll need to fetch - but this should be rare
                // since the frontend will typically call the dedicated progress endpoint
            }
        }

        // 6. Calculate progress for each requested collection using pre-fetched data
        $results = [];
        foreach ($collectionIds as $collectionId) {
            $results[$collectionId] = $this->calculateProgressFromPrefetched(
                $collectionId,
                $allCollections,
                $childrenMap,
                $itemCounts,
                $wishlistCounts,
                $dbotCounts
            );
        }

        return $results;
    }

    /**
     * Calculate progress for a single collection using pre-fetched data
     */
    protected function calculateProgressFromPrefetched(
        string $collectionId,
        \Illuminate\Support\Collection $allCollections,
        array $childrenMap,
        array $itemCounts,
        array $wishlistCounts,
        array $dbotCounts
    ): array {
        // Get all descendant collection IDs (including self)
        $descendantIds = $this->getDescendantIdsFromMap($collectionId, $childrenMap);
        $descendantIds[] = $collectionId;

        // Sum owned counts across all descendants
        $ownedCount = 0;
        foreach ($descendantIds as $id) {
            $ownedCount += $itemCounts[$id] ?? 0;
        }

        // Sum wishlist counts across all descendants
        $wishlistCount = 0;
        foreach ($descendantIds as $id) {
            $wishlistCount += $wishlistCounts[$id] ?? 0;
        }

        // Calculate total count
        $totalCount = 0;
        foreach ($descendantIds as $id) {
            $collection = $allCollections->get($id);
            if ($collection && $collection->linked_dbot_collection_id) {
                // Linked collection - use DBoT count
                $totalCount += $dbotCounts[$id] ?? 0;
            } else {
                // Non-linked collection - total is owned + wishlisted
                $totalCount += ($itemCounts[$id] ?? 0) + ($wishlistCounts[$id] ?? 0);
            }
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
     * Get all descendant IDs from pre-built children map (no queries)
     */
    protected function getDescendantIdsFromMap(string $collectionId, array $childrenMap): array
    {
        $descendants = [];
        $children = $childrenMap[$collectionId] ?? [];

        foreach ($children as $childId) {
            $descendants[] = $childId;
            $descendants = array_merge($descendants, $this->getDescendantIdsFromMap($childId, $childrenMap));
        }

        return $descendants;
    }

    /**
     * Bulk add items to wishlist.
     *
     * @param  array  $entityIds  Array of entity IDs to add
     * @return array ['items_added' => int, 'items_skipped' => int]
     */
    public function bulkAddToWishlist(
        string $userId,
        array $entityIds,
        ?string $parentCollectionId = null
    ): array {
        // Handle empty array case
        if (empty($entityIds)) {
            return [
                'items_added' => 0,
                'items_skipped' => 0,
            ];
        }

        return \DB::transaction(function () use ($userId, $entityIds, $parentCollectionId) {
            // 1. Query existing wishlists to find duplicates
            // Note: Unique constraint is on (user_id, entity_id) only, not parent_collection_id
            $existingWishlists = Wishlist::where('user_id', $userId)
                ->whereIn('entity_id', $entityIds)
                ->pluck('entity_id')
                ->toArray();

            // 2. Filter out existing items
            $itemsToAdd = array_diff($entityIds, $existingWishlists);

            // 3. Bulk insert new wishlist records
            if (! empty($itemsToAdd)) {
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
