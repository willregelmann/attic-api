<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;

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

    private $tracer;

    public function __construct()
    {
        $this->baseUrl = config('services.database_of_things.url');
        $this->graphqlUrl = $this->baseUrl.'/graphql/v1';
        $this->apiKey = config('services.database_of_things.api_key');

        $this->client = new Client([
            'timeout' => 10.0,
            'http_errors' => false, // We'll handle errors manually
        ]);

        $this->tracer = Globals::tracerProvider()->getTracer('dbot');
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
        // Handle multiple DBoT data formats:
        // 1. GraphQL queries: entities have flat image_url + images { thumbnail_url } relationship
        // 2. REST API (semantic search): images is a raw JSONB array from database
        // - Normalize to flat image_url and thumbnail_url for backward compatibility

        // image_url can be a flat field on entities OR come from the images relationship/array
        if (isset($entity['image_url']) && $entity['image_url']) {
            $entity['image_url'] = $this->normalizeImageUrl($entity['image_url']);
        } elseif (isset($entity['images']) && is_array($entity['images'])) {
            // Handle images as either object (GraphQL) or sequential array (REST API)
            // Use array_is_list to check if it's a sequential array (REST) vs associative (GraphQL)
            if (array_is_list($entity['images'])) {
                // images is a sequential array (REST API format) - use first image
                if (! empty($entity['images'])) {
                    $firstImage = $entity['images'][0];
                    if (is_string($firstImage)) {
                        // Array of URL strings
                        $entity['image_url'] = $this->normalizeImageUrl($firstImage);
                    } elseif (is_array($firstImage) && isset($firstImage['url'])) {
                        // Array of image objects with url/thumbnail
                        $entity['image_url'] = $this->normalizeImageUrl($firstImage['url']);
                        if (isset($firstImage['thumbnail'])) {
                            $entity['thumbnail_url'] = $this->normalizeImageUrl($firstImage['thumbnail']);
                        }
                    }
                }
            } elseif (isset($entity['images']['image_url']) && $entity['images']['image_url']) {
                // Associative array from GraphQL images relationship
                $entity['image_url'] = $this->normalizeImageUrl($entity['images']['image_url']);
            }
        }

        // thumbnail_url comes from the images relationship
        if (isset($entity['images']['thumbnail_url'])) {
            $entity['thumbnail_url'] = $this->normalizeImageUrl($entity['images']['thumbnail_url']);
        } elseif (isset($entity['thumbnail_url']) && $entity['thumbnail_url']) {
            // Direct thumbnail_url field (exists on variants, not entities)
            $entity['thumbnail_url'] = $this->normalizeImageUrl($entity['thumbnail_url']);
        }

        // Remove images relationship/array to maintain flat structure
        if (isset($entity['images'])) {
            unset($entity['images']);
        }

        // Flatten entity_variants from GraphQL connection structure to JSON array
        if (isset($entity['entity_variants'])) {
            $entity['entity_variants'] = $this->normalizeEntityVariants($entity['entity_variants']);
        }

        // Flatten entity_components from GraphQL connection structure to JSON array
        if (isset($entity['entity_components'])) {
            $entity['entity_components'] = $this->normalizeEntityComponents($entity['entity_components']);
        }

        // Flatten entity_additional_images from GraphQL connection structure to JSON array
        if (isset($entity['entity_additional_images'])) {
            $entity['additional_images'] = $this->normalizeAdditionalImages($entity['entity_additional_images']);
            unset($entity['entity_additional_images']);
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

                    // Variants have flat image_url and thumbnail_url fields
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
     * Normalize entity_components from GraphQL connection structure to array
     *
     * @param  array  $componentsConnection  GraphQL connection structure with edges/node
     * @return array Array of component objects sorted by order field
     */
    private function normalizeEntityComponents(array $componentsConnection): array
    {
        $components = [];

        if (isset($componentsConnection['edges']) && is_array($componentsConnection['edges'])) {
            foreach ($componentsConnection['edges'] as $edge) {
                if (isset($edge['node'])) {
                    $component = $edge['node'];

                    // Normalize image URLs in images relationship
                    if (isset($component['images']['image_url'])) {
                        $component['image_url'] = $this->normalizeImageUrl($component['images']['image_url']);
                    }
                    if (isset($component['images']['thumbnail_url'])) {
                        $component['thumbnail_url'] = $this->normalizeImageUrl($component['images']['thumbnail_url']);
                    }

                    // Remove images relationship to maintain flat structure
                    if (isset($component['images'])) {
                        unset($component['images']);
                    }

                    $components[] = $component;
                }
            }
        }

        // Sort components by order field
        usort($components, function ($a, $b) {
            return ($a['order'] ?? 0) <=> ($b['order'] ?? 0);
        });

        return $components;
    }

    /**
     * Normalize entity_additional_images from GraphQL connection structure to array
     *
     * @param  array  $additionalImagesConnection  GraphQL connection structure with edges/node
     * @return array Array of additional image objects with {id, image_url, thumbnail_url}
     */
    private function normalizeAdditionalImages(array $additionalImagesConnection): array
    {
        $additionalImages = [];

        if (isset($additionalImagesConnection['edges']) && is_array($additionalImagesConnection['edges'])) {
            foreach ($additionalImagesConnection['edges'] as $edge) {
                if (isset($edge['node'])) {
                    $image = $edge['node'];

                    // Normalize image URLs
                    if (isset($image['image_url'])) {
                        $image['image_url'] = $this->normalizeImageUrl($image['image_url']);
                    }
                    if (isset($image['thumbnail_url'])) {
                        $image['thumbnail_url'] = $this->normalizeImageUrl($image['thumbnail_url']);
                    }

                    $additionalImages[] = $image;
                }
            }
        }

        return $additionalImages;
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
        // Extract operation name from query for span naming
        $operationName = 'graphql';
        if (preg_match('/^\s*(query|mutation)\s*(\w+)?/i', $query, $matches)) {
            $operationName = $matches[2] ?? $matches[1];
        }

        $span = $this->tracer->spanBuilder("dbot.{$operationName}")
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->setAttribute('graphql.operation.type', 'query')
            ->setAttribute('graphql.operation.name', $operationName)
            ->setAttribute('http.url', $this->graphqlUrl)
            ->startSpan();

        $scope = $span->activate();

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

            $span->setAttribute('http.status_code', $statusCode);

            if ($statusCode !== 200) {
                Log::error('Database of Things API request failed', [
                    'status' => $statusCode,
                    'body' => $body,
                ]);
                $span->setStatus(StatusCode::STATUS_ERROR, "HTTP {$statusCode}");
                throw new \Exception("Database of Things API returned status {$statusCode}");
            }

            if (isset($data['errors'])) {
                Log::error('Database of Things GraphQL errors', [
                    'errors' => $data['errors'],
                    'query' => $query,
                    'variables' => $variables,
                ]);
                $span->setStatus(StatusCode::STATUS_ERROR, 'GraphQL errors');
                throw new \Exception('GraphQL query returned errors: '.json_encode($data['errors']));
            }

            return $data;

        } catch (GuzzleException $e) {
            Log::error('Database of Things API connection failed', [
                'message' => $e->getMessage(),
                'query' => $query,
            ]);
            $span->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());
            $span->recordException($e);
            throw new \Exception('Failed to connect to Database of Things API: '.$e->getMessage());
        } finally {
            $scope->detach();
            $span->end();
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
                            attributes
                            year
                            country
                            external_ids
                            images {
                                image_url
                                thumbnail_url
                            }
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
        // Step 1: Fetch relationship edges to get to_id and order
        $query = '
            query($collectionId: UUID!, $first: Int!, $after: Cursor) {
                relationshipsCollection(
                    filter: {
                        from_id: {eq: $collectionId}
                    },
                    first: $first,
                    after: $after
                ) {
                    edges {
                        node {
                            to_id
                            order
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
        $allRelationships = [];
        $currentCursor = $after;
        $pageSize = 30; // Supabase GraphQL default max is 30 per page

        do {
            $variables = [
                'collectionId' => $collectionId,
                'first' => $pageSize,
            ];

            if ($currentCursor !== null) {
                $variables['after'] = $currentCursor;
            }

            $result = $this->query($query, $variables);
            $relationships = $result['data']['relationshipsCollection'] ?? ['edges' => [], 'pageInfo' => ['hasNextPage' => false, 'hasPreviousPage' => false, 'endCursor' => null]];

            // Collect relationships from this page
            $allRelationships = array_merge($allRelationships, $relationships['edges']);

            $hasNextPage = $relationships['pageInfo']['hasNextPage'] ?? false;
            $currentCursor = $relationships['pageInfo']['endCursor'] ?? null;

            // Continue fetching until we have enough items or no more pages
        } while ($hasNextPage && count($allRelationships) < $first);

        // Step 2: Extract entity IDs and batch fetch entities
        $entityIds = array_map(fn ($edge) => $edge['node']['to_id'], $allRelationships);
        $entities = $this->getEntitiesByIds($entityIds);

        // Step 3: Match entities with their order from relationships
        $allItems = array_map(function ($edge) use ($entities) {
            $toId = $edge['node']['to_id'];
            $entity = $entities[$toId] ?? null;

            return [
                'entity' => $entity,
                'order' => $edge['node']['order'] ?? 0,
            ];
        }, $allRelationships);

        // Filter out any items where entity wasn't found
        $allItems = array_filter($allItems, fn ($item) => $item['entity'] !== null);

        // Sort items by order field
        usort($allItems, function ($a, $b) {
            return ($a['order'] ?? 0) <=> ($b['order'] ?? 0);
        });

        return [
            'items' => $allItems,
            'pageInfo' => [
                'hasNextPage' => false, // We fetched everything
                'hasPreviousPage' => false,
                'endCursor' => null,
            ],
        ];
    }

    /**
     * Search for entities by name
     *
     * @param  string  $searchTerm  Term to search for
     * @param  string|null  $type  Optional entity type filter (e.g., "collection", "item")
     * @param  string|null  $category  Optional category filter
     * @param  int  $first  Number of results to return
     * @param  string|null  $after  Cursor for pagination
     * @return array Matching entities with pagination info
     */
    public function searchEntities(string $searchTerm, ?string $type = null, ?string $category = null, int $first = 50, ?string $after = null): array
    {
        $filters = ['name' => ['ilike' => '%'.$searchTerm.'%']];

        if ($type !== null) {
            $filters['type'] = ['eq' => $type];
        }

        if ($category !== null) {
            $filters['category'] = ['eq' => $category];
        }

        $query = '
            query($filters: entitiesFilter!, $first: Int!, $after: Cursor) {
                entitiesCollection(
                    filter: $filters,
                    first: $first,
                    after: $after,
                    orderBy: {name: AscNullsLast}
                ) {
                    edges {
                        node {
                            id
                            name
                            type
                            category
                            attributes
                            year
                            country
                            language
                            external_ids
                            source_url
                            images {
                                image_url
                                thumbnail_url
                            }
                        }
                    }
                    pageInfo {
                        hasNextPage
                        hasPreviousPage
                        startCursor
                        endCursor
                    }
                }
            }
        ';

        $variables = ['filters' => $filters, 'first' => $first];
        if ($after !== null) {
            $variables['after'] = $after;
        }

        $result = $this->query($query, $variables);

        return [
            'items' => array_map(
                fn ($edge) => $this->normalizeEntityImages($edge['node']),
                $result['data']['entitiesCollection']['edges'] ?? []
            ),
            'pageInfo' => $result['data']['entitiesCollection']['pageInfo'] ?? [
                'hasNextPage' => false,
                'hasPreviousPage' => false,
                'startCursor' => null,
                'endCursor' => null,
            ],
        ];
    }

    /**
     * List all collections
     *
     * @param  int  $first  Number of collections to return
     * @param  string|null  $after  Cursor for pagination
     * @param  string|null  $category  Category filter
     * @return array Collections with pagination info
     */
    public function listCollections(int $first = 50, ?string $after = null, ?string $category = null): array
    {
        $filters = ['type' => ['eq' => 'collection']];

        if ($category !== null) {
            $filters['category'] = ['eq' => $category];
        }

        $query = '
            query($filters: entitiesFilter!, $first: Int!, $after: Cursor) {
                entitiesCollection(
                    filter: $filters,
                    first: $first,
                    after: $after,
                    orderBy: {name: AscNullsLast}
                ) {
                    edges {
                        node {
                            id
                            name
                            type
                            category
                            attributes
                            year
                            country
                            language
                            external_ids
                            source_url
                            images {
                                image_url
                                thumbnail_url
                            }
                        }
                    }
                    pageInfo {
                        hasNextPage
                        hasPreviousPage
                        startCursor
                        endCursor
                    }
                }
            }
        ';

        $variables = ['filters' => $filters, 'first' => $first];
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
                'hasPreviousPage' => false,
                'startCursor' => null,
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
                            category
                            attributes
                            year
                            country
                            language
                            external_ids
                            source_url
                            images {
                                image_url
                                thumbnail_url
                            }
                            entity_variants {
                                edges {
                                    node {
                                        id
                                        name
                                        attributes
                                        images {
                                            image_url
                                            thumbnail_url
                                        }
                                    }
                                }
                            }
                            entity_components {
                                edges {
                                    node {
                                        id
                                        name
                                        quantity
                                        order
                                        attributes
                                        images {
                                            image_url
                                            thumbnail_url
                                        }
                                    }
                                }
                            }
                            entity_additional_images {
                                edges {
                                    node {
                                        id
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
            query($ids: [UUID!]!, $first: Int!) {
                entitiesCollection(filter: {id: {in: $ids}}, first: $first) {
                    edges {
                        node {
                            id
                            name
                            type
                            category
                            attributes
                            year
                            country
                            language
                            external_ids
                            source_url
                            images {
                                image_url
                                thumbnail_url
                            }
                            entity_variants {
                                edges {
                                    node {
                                        id
                                        name
                                        attributes
                                        images {
                                            image_url
                                            thumbnail_url
                                        }
                                    }
                                }
                            }
                            entity_components {
                                edges {
                                    node {
                                        id
                                        name
                                        quantity
                                        order
                                        attributes
                                        images {
                                            image_url
                                            thumbnail_url
                                        }
                                    }
                                }
                            }
                            entity_additional_images {
                                edges {
                                    node {
                                        id
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

        // Supabase GraphQL has a default max of 30 items per page
        // Batch IDs into groups of 30 to ensure all entities are fetched
        $batchSize = 30;
        $entities = [];
        $batches = array_chunk($entityIds, $batchSize);

        foreach ($batches as $batchIds) {
            $result = $this->query($query, [
                'ids' => $batchIds,
                'first' => $batchSize,
            ]);

            foreach ($result['data']['entitiesCollection']['edges'] ?? [] as $edge) {
                $entities[$edge['node']['id']] = $this->normalizeEntityImages($edge['node']);
            }
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

        $span = $this->tracer->spanBuilder('dbot.semantic_search')
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->setAttribute('search.query', $queryText)
            ->setAttribute('search.limit', $limit)
            ->setAttribute('search.entity_type', $entityType ?? 'all')
            ->setAttribute('http.url', $url)
            ->startSpan();

        $scope = $span->activate();

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

            $span->setAttribute('http.status_code', $statusCode);

            Log::info('Semantic search response', [
                'status' => $statusCode,
                'body' => $body,
            ]);

            if ($statusCode !== 200) {
                Log::error('Database of Things REST API request failed', [
                    'status' => $statusCode,
                    'body' => $body,
                ]);
                $span->setStatus(StatusCode::STATUS_ERROR, "HTTP {$statusCode}");
                throw new \Exception("Database of Things REST API returned status {$statusCode}");
            }

            // Normalize image URLs in search results
            $results = $data ?? [];
            $span->setAttribute('search.result_count', count($results));

            return array_map(fn ($entity) => $this->normalizeEntityImages($entity), $results);

        } catch (GuzzleException $e) {
            Log::error('Database of Things REST API connection failed', [
                'message' => $e->getMessage(),
                'query' => $queryText,
            ]);
            $span->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());
            $span->recordException($e);
            throw new \Exception('Failed to connect to Database of Things REST API: '.$e->getMessage());
        } finally {
            $scope->detach();
            $span->end();
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
                        to_id: {eq: $itemId}
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

        // Build GraphQL query for fetching collection relationship IDs
        $query = '
            query($collectionId: UUID!, $first: Int!) {
                relationshipsCollection(
                    filter: {
                        from_id: {eq: $collectionId}
                    },
                    first: $first
                ) {
                    edges {
                        node {
                            to_id
                            order
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

        // Store relationships temporarily (will fetch entities after)
        $relationshipData = [];

        // Execute requests in parallel
        $pool = new Pool($this->client, $requests(), [
            'concurrency' => 5, // Limit concurrent requests to avoid overwhelming the API
            'fulfilled' => function (Response $response, $collectionId) use (&$relationshipData) {
                $body = json_decode($response->getBody()->getContents(), true);

                if (isset($body['data']['relationshipsCollection'])) {
                    $relationships = $body['data']['relationshipsCollection'];
                    $relationshipData[$collectionId] = [
                        'edges' => $relationships['edges'] ?? [],
                        'pageInfo' => $relationships['pageInfo'] ?? ['hasNextPage' => false, 'hasPreviousPage' => false, 'endCursor' => null],
                    ];
                } else {
                    Log::warning("Failed to fetch collection items for {$collectionId}", [
                        'response' => $body,
                    ]);
                    $relationshipData[$collectionId] = ['edges' => [], 'pageInfo' => ['hasNextPage' => false, 'hasPreviousPage' => false, 'endCursor' => null]];
                }
            },
            'rejected' => function ($reason, $collectionId) use (&$relationshipData) {
                Log::error("Failed to fetch collection items for {$collectionId}", [
                    'reason' => (string) $reason,
                ]);
                $relationshipData[$collectionId] = ['edges' => [], 'pageInfo' => ['hasNextPage' => false, 'hasPreviousPage' => false, 'endCursor' => null]];
            },
        ]);

        // Execute the pool
        $promise = $pool->promise();
        $promise->wait();

        // Step 2: Collect all entity IDs and batch fetch
        $allEntityIds = [];
        foreach ($relationshipData as $collectionId => $data) {
            foreach ($data['edges'] as $edge) {
                $allEntityIds[] = $edge['node']['to_id'];
            }
        }
        $allEntityIds = array_unique($allEntityIds);
        $entities = $this->getEntitiesByIds($allEntityIds);

        // Step 3: Match entities to collections and build results
        foreach ($relationshipData as $collectionId => $data) {
            $items = array_map(function ($edge) use ($entities) {
                $toId = $edge['node']['to_id'];

                return [
                    'entity' => $entities[$toId] ?? null,
                    'order' => $edge['node']['order'] ?? 0,
                ];
            }, $data['edges']);

            // Filter out nulls and sort
            $items = array_filter($items, fn ($item) => $item['entity'] !== null);
            usort($items, fn ($a, $b) => ($a['order'] ?? 0) <=> ($b['order'] ?? 0));

            $result = [
                'items' => $items,
                'pageInfo' => $data['pageInfo'],
            ];

            $results[$collectionId] = $result;

            // Cache the result for 1 hour
            $cacheKey = "collection_items:{$collectionId}:{$first}:null";
            Cache::put($cacheKey, $result, 3600);
        }

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
                        from_id: {eq: $collectionId}
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
        $pageSize = 30; // Supabase GraphQL default max is 30 per page
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
                        to_id: {in: $itemIds}
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
        $pageSize = 30; // Supabase GraphQL default max is 30 per page

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

            if (! isset($parentToItems[$parentId])) {
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
        if ($collection && ! empty($collection['image_url'])) {
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
            if (! empty($entity['image_url'])) {
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

    /**
     * Generate image embedding using CLIP service
     *
     * @param  string  $imagePath  Path to image file
     * @param  string|null  $mimeType  MIME type of the image (optional)
     * @return array 512-dimensional embedding
     *
     * @throws \Exception If CLIP service fails
     */
    public function generateImageEmbedding(string $imagePath, ?string $mimeType = null): array
    {
        $clipServiceUrl = config('services.clip.url', 'http://clip-service:8001');
        $url = $clipServiceUrl.'/embed';

        // Detect MIME type if not provided
        if ($mimeType === null) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $imagePath);
            finfo_close($finfo);
        }

        $span = $this->tracer->spanBuilder('clip.generate_embedding')
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->setAttribute('http.url', $url)
            ->setAttribute('file.path', $imagePath)
            ->startSpan();

        $scope = $span->activate();

        try {
            $response = $this->client->post($url, [
                'multipart' => [
                    [
                        'name' => 'file',
                        'contents' => fopen($imagePath, 'r'),
                        'filename' => basename($imagePath),
                        'headers' => [
                            'Content-Type' => $mimeType,
                        ],
                    ],
                ],
                'timeout' => 30.0,
            ]);

            $statusCode = $response->getStatusCode();
            $body = $response->getBody()->getContents();
            $data = json_decode($body, true);

            $span->setAttribute('http.status_code', $statusCode);

            if ($statusCode !== 200) {
                Log::error('CLIP service request failed', [
                    'status' => $statusCode,
                    'body' => $body,
                ]);
                $span->setStatus(StatusCode::STATUS_ERROR, "HTTP {$statusCode}");
                throw new \Exception("CLIP service returned status {$statusCode}");
            }

            if (! isset($data['embedding'])) {
                $span->setStatus(StatusCode::STATUS_ERROR, 'Missing embedding');
                throw new \Exception('CLIP service response missing embedding');
            }

            if (count($data['embedding']) !== 512) {
                $span->setStatus(StatusCode::STATUS_ERROR, 'Invalid dimensions');
                throw new \Exception('CLIP service returned invalid embedding dimensions: '.count($data['embedding']));
            }

            $span->setAttribute('embedding.dimensions', $data['dimensions']);

            Log::info('Generated image embedding', [
                'dimensions' => $data['dimensions'],
                'filename' => $data['filename'] ?? basename($imagePath),
            ]);

            return $data['embedding'];

        } catch (GuzzleException $e) {
            Log::error('CLIP service connection failed', [
                'message' => $e->getMessage(),
                'path' => $imagePath,
            ]);
            $span->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());
            $span->recordException($e);
            throw new \Exception('Failed to connect to CLIP service: '.$e->getMessage());
        } finally {
            $scope->detach();
            $span->end();
        }
    }

    /**
     * Search for visually similar images using CLIP embedding
     *
     * @param  array  $embedding  512-dimensional CLIP embedding
     * @param  int  $limit  Number of results to return
     * @param  float  $minSimilarity  Minimum similarity threshold (0.0 to 1.0)
     * @return array Search results with similarity scores
     */
    public function searchByImageEmbedding(array $embedding, int $limit = 20, float $minSimilarity = 0.75): array
    {
        $url = $this->baseUrl.'/rest/v1/rpc/image_search';

        $payload = [
            'query_embedding' => $embedding,
            'result_limit' => $limit,
        ];

        Log::info('Image similarity search request', [
            'url' => $url,
            'limit' => $limit,
            'min_similarity' => $minSimilarity,
        ]);

        $span = $this->tracer->spanBuilder('dbot.image_search')
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->setAttribute('http.url', $url)
            ->setAttribute('search.limit', $limit)
            ->setAttribute('search.min_similarity', $minSimilarity)
            ->startSpan();

        $scope = $span->activate();

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

            $span->setAttribute('http.status_code', $statusCode);

            Log::info('Image similarity search response', [
                'status' => $statusCode,
                'result_count' => count($data ?? []),
            ]);

            if ($statusCode !== 200) {
                Log::error('Database of Things image search failed', [
                    'status' => $statusCode,
                    'body' => $body,
                ]);
                $span->setStatus(StatusCode::STATUS_ERROR, "HTTP {$statusCode}");
                throw new \Exception("Database of Things image search returned status {$statusCode}");
            }

            // Filter by minimum similarity and normalize image URLs
            $results = array_filter($data ?? [], function ($result) use ($minSimilarity) {
                return ($result['similarity'] ?? 0) >= $minSimilarity;
            });

            // Normalize image URLs in results
            $results = array_map(function ($result) {
                if (isset($result['image_url'])) {
                    $result['image_url'] = $this->normalizeImageUrl($result['image_url']);
                }
                if (isset($result['thumbnail_url'])) {
                    $result['thumbnail_url'] = $this->normalizeImageUrl($result['thumbnail_url']);
                }

                return $result;
            }, $results);

            $span->setAttribute('search.result_count', count($results));

            return array_values($results);

        } catch (GuzzleException $e) {
            Log::error('Database of Things image search connection failed', [
                'message' => $e->getMessage(),
            ]);
            $span->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());
            $span->recordException($e);
            throw new \Exception('Failed to connect to Database of Things for image search: '.$e->getMessage());
        } finally {
            $scope->detach();
            $span->end();
        }
    }

    /**
     * Search for items by uploading an image file
     *
     * @param  string  $imagePath  Path to uploaded image
     * @param  int  $limit  Number of results to return
     * @param  float  $minSimilarity  Minimum similarity threshold
     * @param  string|null  $mimeType  MIME type of the image (optional)
     * @return array Search results
     */
    public function searchByImageFile(string $imagePath, int $limit = 20, float $minSimilarity = 0.75, ?string $mimeType = null): array
    {
        // Step 1: Generate embedding from image
        $embedding = $this->generateImageEmbedding($imagePath, $mimeType);

        // Step 2: Search DBoT using the embedding
        return $this->searchByImageEmbedding($embedding, $limit, $minSimilarity);
    }

    /**
     * Fetch multiple entities by IDs in parallel using Guzzle Pool
     *
     * Uses concurrent HTTP requests for much better performance when fetching
     * many entities (e.g., for collection tree with linked DBoT collections).
     *
     * Performance: 60 entities fetched in ~0.3s instead of ~3s (10x improvement)
     *
     * @param  array  $entityIds  Array of entity UUIDs
     * @param  int  $concurrency  Number of concurrent requests (default 10)
     * @return array Entities indexed by ID
     */
    public function getEntitiesByIdsInParallel(array $entityIds, int $concurrency = 10): array
    {
        if (empty($entityIds)) {
            return [];
        }

        $span = $this->tracer->spanBuilder('dbot.getEntitiesByIdsInParallel')
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->setAttribute('entity_ids.count', count($entityIds))
            ->setAttribute('concurrency', $concurrency)
            ->startSpan();

        $scope = $span->activate();

        try {
            $query = '
                query($ids: [UUID!]!, $first: Int!) {
                    entitiesCollection(filter: {id: {in: $ids}}, first: $first) {
                        edges {
                            node {
                                id
                                name
                                type
                                category
                                attributes
                                year
                                country
                                language
                                external_ids
                                source_url
                                images {
                                    image_url
                                    thumbnail_url
                                }
                            }
                        }
                    }
                }
            ';

            // Supabase GraphQL has a default max of 30 items per page
            // Batch IDs into groups of 30
            $batchSize = 30;
            $batches = array_chunk($entityIds, $batchSize);
            $entities = [];

            // Create request generator for Guzzle Pool
            $requests = function () use ($batches, $query, $batchSize) {
                foreach ($batches as $index => $batchIds) {
                    $payload = json_encode([
                        'query' => $query,
                        'variables' => [
                            'ids' => $batchIds,
                            'first' => $batchSize,
                        ],
                    ]);

                    yield $index => new Request(
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
                'concurrency' => $concurrency,
                'fulfilled' => function (Response $response, $index) use (&$entities) {
                    $body = json_decode($response->getBody()->getContents(), true);

                    if (isset($body['data']['entitiesCollection']['edges'])) {
                        foreach ($body['data']['entitiesCollection']['edges'] as $edge) {
                            $entities[$edge['node']['id']] = $this->normalizeEntityImages($edge['node']);
                        }
                    }
                },
                'rejected' => function ($reason, $index) {
                    Log::error("Batch {$index} failed in parallel entity fetch", [
                        'reason' => (string) $reason,
                    ]);
                },
            ]);

            // Execute the pool
            $promise = $pool->promise();
            $promise->wait();

            $span->setAttribute('entities.fetched', count($entities));

            return $entities;

        } finally {
            $scope->detach();
            $span->end();
        }
    }

    /**
     * Pre-fetch all DBoT data needed for a collection tree in parallel
     *
     * This method gathers all linked_dbot_collection_ids from user collections
     * and fetches all necessary DBoT data in parallel, then returns it in a
     * format that field resolvers can use directly.
     *
     * @param  array  $linkedDbotCollectionIds  Array of linked DBoT collection UUIDs
     * @return array Pre-fetched data with keys: 'entities', 'collectionItemCounts'
     */
    public function prefetchCollectionTreeData(array $linkedDbotCollectionIds): array
    {
        if (empty($linkedDbotCollectionIds)) {
            return [
                'entities' => [],
                'collectionItemCounts' => [],
            ];
        }

        $span = $this->tracer->spanBuilder('dbot.prefetchCollectionTreeData')
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->setAttribute('collection_ids.count', count($linkedDbotCollectionIds))
            ->startSpan();

        $scope = $span->activate();

        try {
            // Step 1: Fetch all linked DBoT collection entities in parallel
            $entities = $this->getEntitiesByIdsInParallel($linkedDbotCollectionIds);

            // Step 2: Fetch item counts for all linked collections in parallel
            // We use a lightweight query that only gets the count, not the actual items
            $collectionItemCounts = $this->getCollectionItemCountsInParallel($linkedDbotCollectionIds);

            $span->setAttribute('entities.fetched', count($entities));
            $span->setAttribute('counts.fetched', count($collectionItemCounts));

            return [
                'entities' => $entities,
                'collectionItemCounts' => $collectionItemCounts,
            ];

        } finally {
            $scope->detach();
            $span->end();
        }
    }

    /**
     * Fetch item counts for multiple collections in parallel
     *
     * @param  array  $collectionIds  Array of collection UUIDs
     * @return array Associative array of collectionId => item count
     */
    public function getCollectionItemCountsInParallel(array $collectionIds): array
    {
        if (empty($collectionIds)) {
            return [];
        }

        // Query to count relationships (items) for a collection
        // We fetch all pages to get accurate counts
        $query = '
            query($collectionId: UUID!, $first: Int!, $after: Cursor) {
                relationshipsCollection(
                    filter: {
                        from_id: {eq: $collectionId}
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

        $pageSize = 30;
        $counts = [];

        // Create request generator for first page of each collection
        $requests = function () use ($collectionIds, $query, $pageSize) {
            foreach ($collectionIds as $collectionId) {
                $payload = json_encode([
                    'query' => $query,
                    'variables' => [
                        'collectionId' => $collectionId,
                        'first' => $pageSize,
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

        // Track collections that need more pages
        $pendingPages = [];

        // Execute first page requests in parallel
        $pool = new Pool($this->client, $requests(), [
            'concurrency' => 10,
            'fulfilled' => function (Response $response, $collectionId) use (&$counts, &$pendingPages) {
                $body = json_decode($response->getBody()->getContents(), true);

                if (isset($body['data']['relationshipsCollection'])) {
                    $data = $body['data']['relationshipsCollection'];
                    $edgeCount = count($data['edges'] ?? []);
                    $counts[$collectionId] = $edgeCount;

                    // Track if we need to fetch more pages
                    if ($data['pageInfo']['hasNextPage'] ?? false) {
                        $pendingPages[$collectionId] = [
                            'cursor' => $data['pageInfo']['endCursor'],
                            'count' => $edgeCount,
                        ];
                    }
                } else {
                    $counts[$collectionId] = 0;
                }
            },
            'rejected' => function ($reason, $collectionId) use (&$counts) {
                Log::error("Failed to fetch item count for collection {$collectionId}", [
                    'reason' => (string) $reason,
                ]);
                $counts[$collectionId] = 0;
            },
        ]);

        $pool->promise()->wait();

        // Fetch remaining pages sequentially (usually not many)
        // This could be parallelized further, but most collections have <30 items
        while (! empty($pendingPages)) {
            $nextPendingPages = [];

            foreach ($pendingPages as $collectionId => $pageData) {
                try {
                    $result = $this->query($query, [
                        'collectionId' => $collectionId,
                        'first' => $pageSize,
                        'after' => $pageData['cursor'],
                    ]);

                    $data = $result['data']['relationshipsCollection'] ?? [];
                    $edgeCount = count($data['edges'] ?? []);
                    $counts[$collectionId] += $edgeCount;

                    if ($data['pageInfo']['hasNextPage'] ?? false) {
                        $nextPendingPages[$collectionId] = [
                            'cursor' => $data['pageInfo']['endCursor'],
                            'count' => $counts[$collectionId],
                        ];
                    }
                } catch (\Exception $e) {
                    Log::error("Failed to fetch additional pages for collection {$collectionId}", [
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $pendingPages = $nextPendingPages;
        }

        return $counts;
    }
}
