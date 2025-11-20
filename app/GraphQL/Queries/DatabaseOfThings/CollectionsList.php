<?php

namespace App\GraphQL\Queries\DatabaseOfThings;

use App\Services\DatabaseOfThingsService;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class CollectionsList
{
    protected $databaseOfThings;

    public function __construct(DatabaseOfThingsService $databaseOfThings)
    {
        $this->databaseOfThings = $databaseOfThings;
    }

    /**
     * Fetch collections from Database of Things
     *
     * @param  mixed  $rootValue
     * @return array EntityConnection
     */
    public function __invoke($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $first = $args['first'] ?? 50;
        $after = $args['after'] ?? null;
        $category = $args['category'] ?? null;

        $result = $this->databaseOfThings->listCollections($first, $after, $category);

        // Transform to EntityConnection format
        return [
            'edges' => array_map(function ($collection) {
                return [
                    'node' => $this->transformEntity($collection),
                    'cursor' => base64_encode('cursor:' . $collection['id']),
                ];
            }, $result['collections']),
            'pageInfo' => $result['pageInfo'],
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
            'created_at' => $entity['created_at'] ?? null,
            'updated_at' => $entity['updated_at'] ?? null,
        ];
    }
}
