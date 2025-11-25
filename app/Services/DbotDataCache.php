<?php

namespace App\Services;

/**
 * Request-scoped cache for pre-fetched DBoT data
 *
 * This service stores DBoT entities and collection item counts that have been
 * pre-fetched in bulk (e.g., by MyCollectionTree), allowing field resolvers
 * to use the cached data instead of making individual DBoT calls.
 *
 * Registered as a singleton in the service container, so data is shared
 * across all field resolvers within a single HTTP request.
 */
class DbotDataCache
{
    /**
     * Pre-fetched DBoT entities indexed by ID
     */
    private array $entities = [];

    /**
     * Pre-fetched DBoT collection item counts indexed by collection ID
     */
    private array $collectionItemCounts = [];

    /**
     * Flag to indicate if pre-fetch has been done for this request
     */
    private bool $prefetched = false;

    /**
     * Store pre-fetched entities
     */
    public function setEntities(array $entities): void
    {
        $this->entities = array_merge($this->entities, $entities);
        $this->prefetched = true;
    }

    /**
     * Get a pre-fetched entity by ID
     *
     * @return array|null Entity data or null if not in cache
     */
    public function getEntity(string $entityId): ?array
    {
        return $this->entities[$entityId] ?? null;
    }

    /**
     * Check if an entity is in the cache
     */
    public function hasEntity(string $entityId): bool
    {
        return isset($this->entities[$entityId]);
    }

    /**
     * Store pre-fetched collection item counts
     */
    public function setCollectionItemCounts(array $counts): void
    {
        $this->collectionItemCounts = array_merge($this->collectionItemCounts, $counts);
        $this->prefetched = true;
    }

    /**
     * Get a pre-fetched collection item count
     *
     * @return int|null Item count or null if not in cache
     */
    public function getCollectionItemCount(string $collectionId): ?int
    {
        return $this->collectionItemCounts[$collectionId] ?? null;
    }

    /**
     * Check if a collection item count is in the cache
     */
    public function hasCollectionItemCount(string $collectionId): bool
    {
        return isset($this->collectionItemCounts[$collectionId]);
    }

    /**
     * Check if any pre-fetch has been done for this request
     */
    public function isPrefetched(): bool
    {
        return $this->prefetched;
    }

    /**
     * Get all cached entities (useful for debugging)
     */
    public function getAllEntities(): array
    {
        return $this->entities;
    }

    /**
     * Get all cached item counts (useful for debugging)
     */
    public function getAllCollectionItemCounts(): array
    {
        return $this->collectionItemCounts;
    }

    /**
     * Clear the cache (useful for testing)
     */
    public function clear(): void
    {
        $this->entities = [];
        $this->collectionItemCounts = [];
        $this->prefetched = false;
    }
}
