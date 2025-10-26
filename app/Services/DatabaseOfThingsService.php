<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
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
        $this->graphqlUrl = $this->baseUrl . '/graphql/v1';
        $this->apiKey = config('services.database_of_things.api_key');

        $this->client = new Client([
            'timeout' => 10.0,
            'http_errors' => false, // We'll handle errors manually
        ]);
    }

    /**
     * Normalize an image URL/path to a full URL
     *
     * @param string|null $imageUrl The image URL or path from the API
     * @return string|null Full image URL or null
     */
    private function normalizeImageUrl(?string $imageUrl): ?string
    {
        if (empty($imageUrl)) {
            return null;
        }

        // If it's already a full URL (starts with http:// or https://), return as-is
        if (preg_match('/^https?:\/\//', $imageUrl)) {
            return str_replace('host.docker.internal', '127.0.0.1', $imageUrl);
        }

        // It's a path, prepend the base URL
        // Remove leading slash if present to avoid double slashes
        $path = ltrim($imageUrl, '/');
        $fullUrl = $this->baseUrl . '/' . $path;

        // Replace Docker internal hostname for browser compatibility
        return str_replace('host.docker.internal', '127.0.0.1', $fullUrl);
    }

    /**
     * Normalize image URLs in an entity array
     *
     * @param array $entity Entity data
     * @return array Entity with normalized image_url
     */
    private function normalizeEntityImages(array $entity): array
    {
        if (isset($entity['image_url'])) {
            $entity['image_url'] = $this->normalizeImageUrl($entity['image_url']);
        }
        return $entity;
    }

    /**
     * Execute a GraphQL query against the Database of Things API
     *
     * @param string $query The GraphQL query string
     * @param array $variables Query variables
     * @return array The decoded response data
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
                throw new \Exception('GraphQL query returned errors: ' . json_encode($data['errors']));
            }

            return $data;

        } catch (GuzzleException $e) {
            Log::error('Database of Things API connection failed', [
                'message' => $e->getMessage(),
                'query' => $query,
            ]);
            throw new \Exception('Failed to connect to Database of Things API: ' . $e->getMessage());
        }
    }

    /**
     * Fetch a collection by ID
     *
     * @param string $collectionId UUID of the collection
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
                            external_ids
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
     * @param string $collectionId UUID of the collection
     * @param int $first Number of items to fetch (will fetch all pages if needed)
     * @param string|null $after Cursor for pagination
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
                                external_ids
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
     * @param string $searchTerm Term to search for
     * @param string|null $type Optional entity type filter (e.g., "collection", "trading_card")
     * @param int $first Number of results to return
     * @return array Matching entities
     */
    public function searchEntities(string $searchTerm, ?string $type = null, int $first = 50): array
    {
        $filters = ['name' => ['ilike' => '%' . $searchTerm . '%']];

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
                            external_ids
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
                fn($edge) => $this->normalizeEntityImages($edge['node']),
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
     * @param int $first Number of collections to return
     * @param string|null $after Cursor for pagination
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
                            external_ids
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
                fn($edge) => $this->normalizeEntityImages($edge['node']),
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
     * @param string $entityId UUID of the entity
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
                            external_ids
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
     * @param array $entityIds Array of entity UUIDs
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
                            external_ids
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
     * @param string $queryText The search query text
     * @param string|null $entityType Optional entity type filter (e.g., "collection", "trading_card")
     * @param int $limit Number of results to return
     * @return array Search results with similarity scores
     */
    public function semanticSearch(string $queryText, ?string $entityType = null, int $limit = 20): array
    {
        $url = $this->baseUrl . '/rest/v1/rpc/search_by_text';

        $payload = [
            'query_text' => $queryText,
            'result_limit' => $limit,
        ];

        if ($entityType !== null) {
            $payload['entity_type_filter'] = $entityType;
        }

        try {
            $response = $this->client->post($url, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'apikey' => $this->apiKey,
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Accept' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $statusCode = $response->getStatusCode();
            $body = $response->getBody()->getContents();
            $data = json_decode($body, true);

            if ($statusCode !== 200) {
                Log::error('Database of Things REST API request failed', [
                    'status' => $statusCode,
                    'body' => $body,
                ]);
                throw new \Exception("Database of Things REST API returned status {$statusCode}");
            }

            // Normalize image URLs in search results
            $results = $data ?? [];
            return array_map(fn($entity) => $this->normalizeEntityImages($entity), $results);

        } catch (GuzzleException $e) {
            Log::error('Database of Things REST API connection failed', [
                'message' => $e->getMessage(),
                'query' => $queryText,
            ]);
            throw new \Exception('Failed to connect to Database of Things REST API: ' . $e->getMessage());
        }
    }

    /**
     * Get parent collections for an item with full hierarchy
     *
     * @param string $itemId UUID of the item
     * @param int $maxDepth Maximum depth to traverse (default 10)
     * @param array $visited Track visited nodes to prevent infinite loops
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
}
