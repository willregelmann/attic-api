<?php

namespace App\GraphQL\Queries\DatabaseOfThings;

use App\Services\DatabaseOfThingsService;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class SemanticSearch
{
    protected $databaseOfThings;

    public function __construct(DatabaseOfThingsService $databaseOfThings)
    {
        $this->databaseOfThings = $databaseOfThings;
    }

    /**
     * Semantic search using vector embeddings
     *
     * @param  mixed  $rootValue
     * @return array EntityConnection
     */
    public function __invoke($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $query = $args['query'];
        $type = $args['type'] ?? null;
        $category = $args['category'] ?? null;
        $first = $args['first'] ?? 20;
        // Note: semantic search doesn't support cursor pagination in DBoT REST API

        $results = $this->databaseOfThings->semanticSearch($query, $type, $first);

        // Filter by category client-side if needed
        if ($category !== null) {
            $results = array_filter($results, fn($entity) => ($entity['category'] ?? null) === $category);
            $results = array_values($results);
        }

        // Transform to EntityConnection format
        return [
            'edges' => array_map(function ($entity) {
                return [
                    'node' => $this->transformEntity($entity),
                    'cursor' => base64_encode('cursor:' . $entity['id']),
                ];
            }, $results),
            'pageInfo' => [
                'hasNextPage' => false,  // Semantic search doesn't paginate
                'hasPreviousPage' => false,
                'startCursor' => null,
                'endCursor' => null,
            ],
        ];
    }

    /**
     * Transform Database of Things entity to GraphQL Entity format
     */
    private function transformEntity(array $entity): array
    {
        return [
            'id' => $entity['id'],
            'type' => $entity['type'],
            'name' => $entity['name'],
            'category' => $entity['category'] ?? null,
            'year' => $entity['year'] ?? null,
            'country' => $entity['country'] ?? null,
            'language' => $entity['language'] ?? null,
            'attributes' => is_string($entity['attributes'] ?? null)
                ? json_decode($entity['attributes'], true)
                : ($entity['attributes'] ?? []),
            'image_url' => $entity['image_url'] ?? null,
            'thumbnail_url' => $entity['thumbnail_url'] ?? null,
            'additional_images' => $entity['additional_images'] ?? [],
            'external_ids' => is_string($entity['external_ids'] ?? null)
                ? json_decode($entity['external_ids'], true)
                : ($entity['external_ids'] ?? []),
            'source_url' => $entity['source_url'] ?? null,
            'entity_variants' => $entity['entity_variants'] ?? [],
            'entity_components' => $entity['entity_components'] ?? [],
            'similarity' => $entity['similarity'] ?? null,
            'created_at' => $entity['created_at'] ?? null,
            'updated_at' => $entity['updated_at'] ?? null,
        ];
    }
}
