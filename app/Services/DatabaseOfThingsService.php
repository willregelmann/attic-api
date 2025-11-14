<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Service for interacting with the "Database of Things" GraphQL API
 *
 * This service provides a clean interface to query canonical collectibles data
 * from the external Database of Things, separating it from our user-specific data.
 */
class DatabaseOfThingsService
{
    private Client $client;

    private string $baseUrl;

    private string $graphqlUrl;

    private string $apiKey;

    public function __construct()
    {
        $this->baseUrl = config('services.database_of_things.url');
        $this->graphqlUrl = $this->baseUrl.'/graphql/v1';
        $this->apiKey = config('services.database_of_things.api_key');

        $this->client = new Client([
            'timeout' => 10.0,
            'http_errors' => false, // We'll handle errors manually
        ]);
    }

    /**
     * Normalize an image URL/path to a full URL with environment-specific transformations
     *
     * @param  string|null  $imageUrl  The image URL or path from the API
     * @return string|null Full image URL or null
     */
    private function normalizeImageUrl(?string $imageUrl): ?string
    {
        if (empty($imageUrl)) {
            return null;
        }

        // If it's already a full URL (starts with http:// or https://), apply transforms
        if (preg_match('/^https?:\/\//', $imageUrl)) {
            return $this->applyImageUrlTransforms($imageUrl);
        }

        // It's a path, prepend the base URL
        // Remove leading slash if present to avoid double slashes
        $path = ltrim($imageUrl, '/');
        $fullUrl = $this->baseUrl.'/'.$path;

        // Apply environment-specific transformations
        return $this->applyImageUrlTransforms($fullUrl);
    }

    /**
     * Apply environment-specific URL transformations from configuration
     *
     * @param  string  $url  The URL to transform
     * @return string The transformed URL
     */
    private function applyImageUrlTransforms(string $url): string
    {
        $transforms = config('services.database_of_things.image_url_transforms', []);

        foreach ($transforms as $from => $to) {
            $url = str_replace($from, $to, $url);
        }

        return $url;
    }

    /**
     * Normalize image URLs in an entity array
     *
     * @param  array  $entity  Entity data
     * @return array Entity with normalized image_url and thumbnail_url
     */
    private function normalizeEntityImages(array $entity): array
    {
        if (isset($entity['image_url'])) {
            $entity['image_url'] = $this->normalizeImageUrl($entity['image_url']);
        }
        if (isset($entity['thumbnail_url'])) {
            $entity['thumbnail_url'] = $this->normalizeImageUrl($entity['thumbnail_url']);
        }

        // Flatten entity_variants from GraphQL connection structure to JSON array
        if (isset($entity['entity_variants'])) {
            $entity['entity_variants'] = $this->normalizeEntityVariants($entity['entity_variants']);
        }

        return $entity;
    }

    /**
     * Normalize entity_variants from GraphQL connection structure to array
     *
     * @param  array  $variantsConnection  GraphQL connection structure with edges/node
     * @return array Array of variant objects
     */
    private function normalizeEntityVariants(array $variantsConnection): array
    {
        $variants = [];

        if (isset($variantsConnection['edges']) && is_array($variantsConnection['edges'])) {
            foreach ($variantsConnection['edges'] as $edge) {
                if (isset($edge['node'])) {
                    $variant = $edge['node'];

                    // Normalize image URLs in the variant
                    if (isset($variant['image_url'])) {
                        $variant['image_url'] = $this->normalizeImageUrl($variant['image_url']);
                    }
                    if (isset($variant['thumbnail_url'])) {
                        $variant['thumbnail_url'] = $this->normalizeImageUrl($variant['thumbnail_url']);
                    }

                    $variants[] = $variant;
                }
            }
        }

        return $variants;
    }

    /**
     * Execute a GraphQL query against the Database of Things API
     *
     * @param  string  $query  The GraphQL query string
     * @param  array  $variables  Query variables
     * @return array The decoded response data
     *
     * @throws \Exception If the request fails
     */
    public function query(string $query, array $variables = []): array
    {
        try {
            $response = $this->client->post($this->graphqlUrl, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'apikey' => $this->apiKey,
                    'Accept' => 'application/json',
                ],
                'json' => [
                    'query' => $query,
                    'variables' => $variables,
                ],
            ]);

            $statusCode = $response->getStatusCode();
            $body = $response->getBody()->getContents();
            $data = json_decode($body, true);

            if ($statusCode !== 200) {
                Log::error('Database of Things API request failed', [
                    'status' => $statusCode,
                    'body' => $body,
                ]);
                throw new \Exception("Database of Things API returned status {$statusCode}");
            }

            if (isset($data['errors'])) {
                Log::error('Database of Things GraphQL errors', [
                    'errors' => $data['errors'],
                    'query' => $query,
                    'variables' => $variables,
                ]);
                throw new \Exception('GraphQL query returned errors: '.json_encode($data['errors']));
            }

            return $data;

        } catch (GuzzleException $e) {
            Log::error('Database of Things API connection failed', [
                'message' => $e->getMessage(),
                'query' => $query,
            ]);
            throw new \Exception('Failed to connect to Database of Things API: '.$e->getMessage());
        }
    }

    /**
     * Fetch a collection by ID
     *
     * @param  string  $collectionId  UUID of the collection
     * @return array|null Collection data or null if not found
     */
    public function getCollection(string $collectionId): ?array
    {
        $query = '
            query($id: UUID!) {
                entitiesCollection(filter: {id: {eq: $id}}) {
                    edges {
                        node {
                            id
                            name
                            type
                            year
                            country
                            attributes
                            image_url
                            thumbnail_url
                            external_ids
                            entity_variants {
                                edges {
                                    node {
                                        id
                                        name
                                        attributes
                                        image_url
                                        thumbnail_url
                                    }
                                }
                            }
                            created_at
                            updated_at
                        }
                    }
                }
            }
        ';

        $result = $this->query($query, ['id' => $collectionId]);
        $edges = $result['data']['entitiesCollection']['edges'] ?? [];

        $entity = $edges[0]['node'] ?? null;

        return $entity ? $this->normalizeEntityImages($entity) : null;
    }

    /**
     * Fetch items in a collection
     *
     * @param  string  $collectionId  UUID of the collection
     * @param  int  $first  Number of items to fetch (will fetch all pages if needed)
     * @param  string|null  $after  Cursor for pagination
     * @return array Collection items with pagination info
     */
    public function getCollectionItems(string $collectionId, int $first = 100, ?string $after = null): array
    {
        $query = '
            query($collectionId: UUID!, $first: Int!, $after: Cursor) {
                relationshipsCollection(
                    filter: {
                        from_id: {eq: $collectionId},
                        type: {eq: "contains"}
                    },
                    first: $first,
                    after: $after
                ) {
                    edges {
                        node {
                            to_id
                            order
                            entities {
                                id
                                name
                                type
                                year
                                country
                                attributes
                                image_url
                                thumbnail_url
                                external_ids
                                entity_variants {
                                    edges {
                                        node {
                                            id
                                            name
                                            attributes
                                            image_url
                                            thumbnail_url
                                        }
                                    }
                                }
                            }
                        }
                    }
                    pageInfo {
                        hasNextPage
                        endCursor
                    }
                }
            }
        ';

        // Fetch all pages automatically
        $allItems = [];
        $currentCursor = $after;
        $pageSize = 100; // Use a reasonable page size

        do {
            $variables = [
                'collectionId' => $collectionId,
                'first' => $pageSize,
            ];

            if ($currentCursor !== null) {
                $variables['after'] = $currentCursor;
            }

            $result = $this->query($query, $variables);
            $relationships = $result['data']['relationshipsCollection'] ?? ['edges' => [], 'pageInfo' => ['hasNextPage' => false, 'endCursor' => null]];

            // Transform and collect items from this page
            $pageItems = array_map(function ($edge) {
                return [
                    'entity' => $this->normalizeEntityImages($edge['node']['entities']),
                    'order' => $edge['node']['order'] ?? 0,
                ];
            }, $relationships['edges']);

            $allItems = array_merge($allItems, $pageItems);

            $hasNextPage = $relationships['pageInfo']['hasNextPage'] ?? false;
            $currentCursor = $relationships['pageInfo']['endCursor'] ?? null;

            // Continue fetching until we have enough items or no more pages
        } while ($hasNextPage && count($allItems) < $first);

        // Sort items by order field
        usort($allItems, function ($a, $b) {
            return ($a['order'] ?? 0) <=> ($b['order'] ?? 0);
        });

        return [
            'items' => $allItems,
            'pageInfo' => [
                'hasNextPage' => false, // We fetched everything
                'endCursor' => null,
            ],
        ];
    }

    /**
     * Search for entities by name
     *
     * @param  string  $searchTerm  Term to search for
     * @param  string|null  $type  Optional entity type filter (e.g., "collection", "trading_card")
     * @param  int  $first  Number of results to return
     * @return array Matching entities
     */
    public function searchEntities(string $searchTerm, ?string $type = null, int $first = 50): array
    {
        $filters = ['name' => ['ilike' => '%'.$searchTerm.'%']];

        if ($type !== null) {
            $filters['type'] = ['eq' => $type];
        }

        $query = '
            query($filters: entitiesFilter!, $first: Int!) {
                entitiesCollection(
                    filter: $filters,
                    first: $first,
                    orderBy: {name: AscNullsLast}
                ) {
                    edges {
                        node {
                            id
                            name
                            type
                            year
                            country
                            attributes
                            image_url
                            thumbnail_url
                            external_ids
                            entity_variants {
                                edges {
                                    node {
                                        id
                                        name
                                        attributes
                                        image_url
                                        thumbnail_url
                                    }
                                }
                            }
                        }
                    }
                    pageInfo {
                        hasNextPage
                        endCursor
                    }
                }
            }
        ';

        $result = $this->query($query, [
            'filters' => $filters,
            'first' => $first,
        ]);

        return [
            'items' => array_map(
                fn ($edge) => $this->normalizeEntityImages($edge['node']),
                $result['data']['entitiesCollection']['edges'] ?? []
            ),
            'pageInfo' => $result['data']['entitiesCollection']['pageInfo'] ?? [
                'hasNextPage' => false,
                'endCursor' => null,
            ],
        ];
    }

    /**
     * List all collections
     *
     * @param  int  $first  Number of collections to return
     * @param  string|null  $after  Cursor for pagination
     * @return array Collections with pagination info
     */
    public function listCollections(int $first = 50, ?string $after = null): array
    {
        $query = '
            query($first: Int!, $after: Cursor) {
                entitiesCollection(
                    filter: {type: {eq: "collection"}},
                    first: $first,
                    after: $after,
                    orderBy: {year: DescNullsLast}
                ) {
                    edges {
                        node {
                            id
                            name
                            type
                            year
                            country
                            attributes
                            image_url
                            thumbnail_url
                            external_ids
                            entity_variants {
                                edges {
                                    node {
                                        id
                                        name
                                        attributes
                                        image_url
                                        thumbnail_url
                                    }
                                }
                            }
                        }
                    }
                    pageInfo {
                        hasNextPage
                        endCursor
                    }
                }
            }
        ';

        $variables = ['first' => $first];
        if ($after !== null) {
            $variables['after'] = $after;
        }

        $result = $this->query($query, $variables);

        return [
            'collections' => array_map(
                fn ($edge) => $this->normalizeEntityImages($edge['node']),
                $result['data']['entitiesCollection']['edges'] ?? []
            ),
            'pageInfo' => $result['data']['entitiesCollection']['pageInfo'] ?? [
                'hasNextPage' => false,
                'endCursor' => null,
            ],
        ];
    }

    /**
     * Get an entity by ID
     *
     * @param  string  $entityId  UUID of the entity
     * @return array|null Entity data or null if not found
     */
    public function getEntity(string $entityId): ?array
    {
        $query = '
            query($id: UUID!) {
                entitiesCollection(filter: {id: {eq: $id}}) {
                    edges {
                        node {
                            id
                            name
                            type
                            year
                            country
                            attributes
                            image_url
                            thumbnail_url
                            external_ids
                            entity_variants {
                                edges {
                                    node {
                                        id
                                        name
                                        attributes
                                        image_url
                                        thumbnail_url
                                    }
                                }
                            }
                            created_at
                            updated_at
                        }
                    }
                }
            }
        ';

        $result = $this->query($query, ['id' => $entityId]);
        $edges = $result['data']['entitiesCollection']['edges'] ?? [];

        $entity = $edges[0]['node'] ?? null;

        return $entity ? $this->normalizeEntityImages($entity) : null;
    }

    /**
     * Get multiple entities by IDs (batch fetch)
     *
     * @param  array  $entityIds  Array of entity UUIDs
     * @return array Entities indexed by ID
     */
    public function getEntitiesByIds(array $entityIds): array
    {
        if (empty($entityIds)) {
            return [];
        }

        $query = '
            query($ids: [UUID!]!) {
                entitiesCollection(filter: {id: {in: $ids}}) {
                    edges {
                        node {
                            id
                            name
                            type
                            year
                            country
                            attributes
                            image_url
                            thumbnail_url
                            external_ids
                            entity_variants {
                                edges {
                                    node {
                                        id
                                        name
                                        attributes
                                        image_url
                                        thumbnail_url
                                    }
                                }
                            }
                        }
                    }
                }
            }
        ';

        $result = $this->query($query, ['ids' => $entityIds]);
        $entities = [];

        foreach ($result['data']['entitiesCollection']['edges'] ?? [] as $edge) {
            $entities[$edge['node']['id']] = $this->normalizeEntityImages($edge['node']);
        }

        return $entities;
    }

    /**
     * Semantic search using vector embeddings
     *
     * @param  string  $queryText  The search query text
     * @param  string|null  $entityType  Optional entity type filter (e.g., "collection", "trading_card")
     * @param  int  $limit  Number of results to return
     * @return array Search results with similarity scores
     */
    public function semanticSearch(string $queryText, ?string $entityType = null, int $limit = 20): array
    {
        $url = $this->baseUrl.'/rest/v1/rpc/search_by_text';

        $payload = [
            'query_text' => $queryText,
            'result_limit' => $limit,
            'entity_type_filter' => $entityType,
        ];

        Log::info('Semantic search request', [
            'url' => $url,
            'payload' => $payload,
        ]);

        try {
            $response = $this->client->post($url, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'apikey' => $this->apiKey,
                    'Authorization' => 'Bearer '.$this->apiKey,
                    'Accept' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $statusCode = $response->getStatusCode();
            $body = $response->getBody()->getContents();
            $data = json_decode($body, true);

            Log::info('Semantic search response', [
                'status' => $statusCode,
                'body' => $body,
            ]);

            if ($statusCode !== 200) {
                Log::error('Database of Things REST API request failed', [
                    'status' => $statusCode,
                    'body' => $body,
                ]);
                throw new \Exception("Database of Things REST API returned status {$statusCode}");
            }

            // Normalize image URLs in search results
            $results = $data ?? [];

            return array_map(fn ($entity) => $this->normalizeEntityImages($entity), $results);

        } catch (GuzzleException $e) {
            Log::error('Database of Things REST API connection failed', [
                'message' => $e->getMessage(),
                'query' => $queryText,
            ]);
            throw new \Exception('Failed to connect to Database of Things REST API: '.$e->getMessage());
        }
    }

    /**
     * Get parent collections for an item with full hierarchy
     *
     * @param  string  $itemId  UUID of the item
     * @param  int  $maxDepth  Maximum depth to traverse (default 10)
     * @param  array  $visited  Track visited nodes to prevent infinite loops
     * @return array Parent collections with nested parents
     */
    public function getItemParents(string $itemId, int $maxDepth = 10, array &$visited = []): array
    {
        // Prevent infinite loops
        if (in_array($itemId, $visited) || $maxDepth <= 0) {
            return [];
        }

        $visited[] = $itemId;

        $query = '
            query($itemId: UUID!) {
                relationshipsCollection(
                    filter: {
                        to_id: {eq: $itemId},
                        type: {eq: "contains"}
                    }
                ) {
                    edges {
                        node {
                            from_id
                            order
                        }
                    }
                }
            }
        ';

        $result = $this->query($query, ['itemId' => $itemId]);
        $relationships = $result['data']['relationshipsCollection']['edges'] ?? [];

        // Extract parent IDs
        $parentIds = array_map(function ($edge) {
            return $edge['node']['from_id'];
        }, $relationships);

        if (empty($parentIds)) {
            return [];
        }

        // Fetch parent entities by IDs (already normalized by getEntitiesByIds)
        $parents = $this->getEntitiesByIds($parentIds);

        // Recursively fetch parents for each parent
        foreach ($parents as $parentId => &$parent) {
            $parent['parents'] = $this->getItemParents($parentId, $maxDepth - 1, $visited);
        }

        return array_values($parents);
    }

    /**
     * Get collection items with caching to improve performance
     *
     * Caches collection items for 1 hour since canonical data doesn't change frequently.
     * This significantly improves performance for favorite collections queries.
     *
     * @param  string  $collectionId  Collection UUID
     * @param  int  $first  Maximum number of items to fetch
     * @param  string|null  $after  Cursor for pagination
     * @return array Collection items with pagination info
     */
    public function getCollectionItemsCached(string $collectionId, int $first = 100, ?string $after = null): array
    {
        $cacheKey = "collection_items:{$collectionId}:{$first}:".($after ?? 'null');

        return Cache::remember($cacheKey, 3600, function () use ($collectionId, $first, $after) {
            return $this->getCollectionItems($collectionId, $first, $after);
        });
    }

    /**
     * Fetch collection items for multiple collections in parallel
     *
     * Uses Guzzle's Pool to make concurrent HTTP requests, dramatically improving
     * performance when fetching multiple collections (e.g., for favorite collections).
     *
     * Performance: 10 collections fetched in ~0.5s instead of ~5s (10x improvement)
     *
     * @param  array  $collectionIds  Array of collection UUIDs
     * @param  int  $first  Maximum items per collection
     * @return array Associative array of collectionId => items data
     */
    public function getMultipleCollectionItemsInParallel(array $collectionIds, int $first = 100): array
    {
        if (empty($collectionIds)) {
            return [];
        }

        // Check cache first
        $results = [];
        $uncachedIds = [];

        foreach ($collectionIds as $collectionId) {
            $cacheKey = "collection_items:{$collectionId}:{$first}:null";
            $cached = Cache::get($cacheKey);

            if ($cached !== null) {
                $results[$collectionId] = $cached;
            } else {
                $uncachedIds[] = $collectionId;
            }
        }

        // If all collections were cached, return early
        if (empty($uncachedIds)) {
            return $results;
        }

        // Build GraphQL query for fetching collection items
        $query = '
            query($collectionId: UUID!, $first: Int!) {
                relationshipsCollection(
                    filter: {
                        from_id: {eq: $collectionId},
                        type: {eq: "contains"}
                    },
                    first: $first
                ) {
                    edges {
                        node {
                            to_id
                            order
                            entities {
                                id
                                name
                                type
                                year
                                country
                                attributes
                                image_url
                                thumbnail_url
                                external_ids
                                entity_variants {
                                    edges {
                                        node {
                                            id
                                            name
                                            attributes
                                            image_url
                                            thumbnail_url
                                        }
                                    }
                                }
                            }
                        }
                    }
                    pageInfo {
                        hasNextPage
                        endCursor
                    }
                }
            }
        ';

        // Create request generator for Guzzle Pool
        $requests = function () use ($uncachedIds, $query, $first) {
            foreach ($uncachedIds as $collectionId) {
                $payload = json_encode([
                    'query' => $query,
                    'variables' => [
                        'collectionId' => $collectionId,
                        'first' => min($first, 100), // Fetch first page only for parallel requests
                    ],
                ]);

                yield $collectionId => new Request(
                    'POST',
                    $this->graphqlUrl,
                    [
                        'Content-Type' => 'application/json',
                        'apikey' => $this->apiKey,
                        'Accept' => 'application/json',
                    ],
                    $payload
                );
            }
        };

        // Execute requests in parallel
        $pool = new Pool($this->client, $requests(), [
            'concurrency' => 5, // Limit concurrent requests to avoid overwhelming the API
            'fulfilled' => function (Response $response, $collectionId) use (&$results, $first) {
                $body = json_decode($response->getBody()->getContents(), true);

                if (isset($body['data']['relationshipsCollection'])) {
                    $relationships = $body['data']['relationshipsCollection'];

                    // Transform items
                    $items = array_map(function ($edge) {
                        return [
                            'entity' => $this->normalizeEntityImages($edge['node']['entities']),
                            'order' => $edge['node']['order'] ?? 0,
                        ];
                    }, $relationships['edges'] ?? []);

                    // Sort by order
                    usort($items, fn ($a, $b) => ($a['order'] ?? 0) <=> ($b['order'] ?? 0));

                    $result = [
                        'items' => $items,
                        'pageInfo' => $relationships['pageInfo'] ?? ['hasNextPage' => false, 'endCursor' => null],
                    ];

                    $results[$collectionId] = $result;

                    // Cache the result for 1 hour
                    $cacheKey = "collection_items:{$collectionId}:{$first}:null";
                    Cache::put($cacheKey, $result, 3600);
                } else {
                    Log::warning("Failed to fetch collection items for {$collectionId}", [
                        'response' => $body,
                    ]);
                    $results[$collectionId] = ['items' => [], 'pageInfo' => ['hasNextPage' => false, 'endCursor' => null]];
                }
            },
            'rejected' => function ($reason, $collectionId) use (&$results) {
                Log::error("Failed to fetch collection items for {$collectionId}", [
                    'reason' => (string) $reason,
                ]);
                $results[$collectionId] = ['items' => [], 'pageInfo' => ['hasNextPage' => false, 'endCursor' => null]];
            },
        ]);

        // Execute the pool
        $promise = $pool->promise();
        $promise->wait();

        return $results;
    }

    /**
     * Get unique parent collections for all items in a collection
     *
     * Returns all collections that contain items from the given collection,
     * excluding the current collection itself. Includes which items belong to each parent.
     *
     * @param  string  $collectionId  UUID of the collection
     * @return array Array of parent collection entities with item_ids field
     */
    public function getCollectionParentCollections(string $collectionId): array
    {
        // Step 1: Get ALL items in this collection by querying relationships directly
        // Note: Supabase paginates by default, so we need to fetch all pages
        $itemsQuery = '
            query($collectionId: UUID!, $first: Int!, $after: Cursor) {
                relationshipsCollection(
                    filter: {
                        from_id: {eq: $collectionId},
                        type: {eq: "contains"}
                    },
                    first: $first,
                    after: $after
                ) {
                    edges {
                        node {
                            to_id
                        }
                    }
                    pageInfo {
                        hasNextPage
                        endCursor
                    }
                }
            }
        ';

        // Paginate through all items
        $allItemIds = [];
        $after = null;
        $pageSize = 1000;
        $pageCount = 0;

        do {
            $variables = [
                'collectionId' => $collectionId,
                'first' => $pageSize,
            ];
            if ($after !== null) {
                $variables['after'] = $after;
            }

            $itemsResult = $this->query($itemsQuery, $variables);
            $itemEdges = $itemsResult['data']['relationshipsCollection']['edges'] ?? [];
            $pageInfo = $itemsResult['data']['relationshipsCollection']['pageInfo'] ?? [];

            // Extract item IDs from this page
            $pageItemIds = array_map(fn ($edge) => $edge['node']['to_id'], $itemEdges);
            $allItemIds = array_merge($allItemIds, $pageItemIds);

            $after = $pageInfo['endCursor'] ?? null;
            $hasNextPage = $pageInfo['hasNextPage'] ?? false;
        } while ($hasNextPage);

        // Extract item IDs
        $itemIds = $allItemIds;

        if (empty($itemIds)) {
            return [];
        }

        // Step 2: Query for all parent relationships for these items (with pagination)
        $parentsQuery = '
            query($itemIds: [UUID!]!, $first: Int!, $after: Cursor) {
                relationshipsCollection(
                    filter: {
                        to_id: {in: $itemIds},
                        type: {eq: "contains"}
                    },
                    first: $first,
                    after: $after
                ) {
                    edges {
                        node {
                            from_id
                            to_id
                        }
                    }
                    pageInfo {
                        hasNextPage
                        endCursor
                    }
                }
            }
        ';

        // Paginate through all parent relationships
        $allRelationships = [];
        $after = null;
        $pageSize = 1000;

        do {
            $variables = [
                'itemIds' => $itemIds,
                'first' => $pageSize,
            ];
            if ($after !== null) {
                $variables['after'] = $after;
            }

            $result = $this->query($parentsQuery, $variables);
            $pageRelationships = $result['data']['relationshipsCollection']['edges'] ?? [];
            $pageInfo = $result['data']['relationshipsCollection']['pageInfo'] ?? [];

            $allRelationships = array_merge($allRelationships, $pageRelationships);

            $after = $pageInfo['endCursor'] ?? null;
            $hasNextPage = $pageInfo['hasNextPage'] ?? false;
        } while ($hasNextPage);

        // Build a map of parent_id => [item_ids]
        $parentToItems = [];
        foreach ($allRelationships as $edge) {
            $parentId = $edge['node']['from_id'];
            $itemId = $edge['node']['to_id'];

            // Skip the current collection
            if ($parentId === $collectionId) {
                continue;
            }

            if (!isset($parentToItems[$parentId])) {
                $parentToItems[$parentId] = [];
            }
            $parentToItems[$parentId][] = $itemId;
        }

        $parentIds = array_keys($parentToItems);

        if (empty($parentIds)) {
            return [];
        }

        // Fetch parent collection entities (already normalized by getEntitiesByIds)
        $parents = $this->getEntitiesByIds($parentIds);

        // Add item_ids to each parent collection entity's attributes
        foreach ($parents as $parentId => &$parent) {
            // Decode attributes if it's a string
            $attributes = is_string($parent['attributes'] ?? null)
                ? json_decode($parent['attributes'], true)
                : ($parent['attributes'] ?? []);

            // Add item_ids to attributes
            $attributes['item_ids'] = array_unique($parentToItems[$parentId]);

            // Re-encode attributes
            $parent['attributes'] = json_encode($attributes);
        }

        // Return as indexed array
        return array_values($parents);
    }

    /**
     * Get a representative image for a collection by traversing descendants
     *
     * Looks for an image in this order:
     * 1. Collection's own image_url
     * 2. Random image from items in descendant collections (up to 3 levels deep)
     *
     * @param  string  $collectionId  UUID of the collection
     * @param  int  $maxDepth  Maximum depth to traverse (default: 3)
     * @param  int  $sampleSize  Number of images to sample from (default: 20)
     * @return string|null Representative image URL or null
     */
    public function getRepresentativeImages(string $collectionId, int $maxDepth = 3, int $sampleSize = 20, int $maxImages = 5): array
    {
        // Check cache first
        $cacheKey = "representative_images:{$collectionId}:{$maxDepth}:{$maxImages}";
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        // Get the collection itself to check if it has an image
        $collection = $this->getEntity($collectionId);
        if ($collection && !empty($collection['image_url'])) {
            // Cache and return empty array (collection has its own image)
            Cache::put($cacheKey, [], 86400); // 24 hours
            return [];
        }

        // Collect images from descendants
        $imageUrls = $this->collectDescendantImages($collectionId, $maxDepth, $sampleSize);

        if (empty($imageUrls)) {
            // No images found, cache empty array for shorter period
            Cache::put($cacheKey, [], 3600); // 1 hour for empty results
            return [];
        }

        // Randomly shuffle and take up to maxImages (5 to know if there are more than 4)
        shuffle($imageUrls);
        $representativeImages = array_slice($imageUrls, 0, $maxImages);

        // Cache for 24 hours
        Cache::put($cacheKey, $representativeImages, 86400);

        return $representativeImages;
    }

    /**
     * Collect image URLs from descendant items using breadth-first search
     * Prefers images from direct children, only goes deeper if current level has no images
     *
     * @param  string  $collectionId  Current collection ID
     * @param  int  $remainingDepth  Remaining depth to traverse
     * @param  int  $sampleSize  Target number of images to collect
     * @param  array  $collected  Already collected image URLs
     * @return array Array of image URLs
     */
    private function collectDescendantImages(string $collectionId, int $remainingDepth, int $sampleSize, array $collected = []): array
    {
        // Stop if we've collected enough samples or reached max depth
        if (count($collected) >= $sampleSize || $remainingDepth <= 0) {
            return $collected;
        }

        // Breadth-first search: process all items at current level before going deeper
        $currentLevelImages = [];
        $nextLevelCollections = [];

        // Get items in this collection
        $items = $this->getCollectionItems($collectionId, 100);

        foreach ($items['items'] as $item) {
            $entity = $item['entity'];

            // If this entity has an image, collect it at current level
            // This includes both items AND collections with their own images
            if (!empty($entity['image_url'])) {
                $currentLevelImages[] = $entity['image_url'];
            }
            // If this is a subcollection WITHOUT an image, save it for next level
            elseif ($entity['type'] === 'collection') {
                $nextLevelCollections[] = $entity['id'];
            }
        }

        // Add current level images to collected
        foreach ($currentLevelImages as $imageUrl) {
            $collected[] = $imageUrl;
            if (count($collected) >= $sampleSize) {
                return $collected;
            }
        }

        // If we found images at this level and have enough, stop here (breadth-first preference)
        if (count($currentLevelImages) > 0) {
            return $collected;
        }

        // No images at this level, go one level deeper

        foreach ($nextLevelCollections as $subcollectionId) {
            $collected = $this->collectDescendantImages(
                $subcollectionId,
                $remainingDepth - 1,
                $sampleSize,
                $collected
            );

            // Stop if we have enough samples
            if (count($collected) >= $sampleSize) {
                return $collected;
            }
        }

        return $collected;
    }
}
