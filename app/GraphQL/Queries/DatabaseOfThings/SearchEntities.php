<?php

namespace App\GraphQL\Queries\DatabaseOfThings;

use App\Services\DatabaseOfThingsService;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class SearchEntities
{
    protected $databaseOfThings;

    public function __construct(DatabaseOfThingsService $databaseOfThings)
    {
        $this->databaseOfThings = $databaseOfThings;
    }

    /**
     * Search entities in Database of Things
     *
     * @param mixed $rootValue
     * @param array $args
     * @param GraphQLContext $context
     * @param ResolveInfo $resolveInfo
     * @return array
     */
    public function __invoke($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $query = $args['query'];
        $type = $args['type'] ?? null;
        $first = $args['first'] ?? 50;

        $result = $this->databaseOfThings->searchEntities($query, $type, $first);

        // Transform Database of Things response to match GraphQL Entity type
        return array_map(function ($entity) {
            return $this->transformEntity($entity);
        }, $result['items']);
    }

    /**
     * Transform Database of Things entity to GraphQL Entity format
     *
     * @param array $entity
     * @return array
     */
    private function transformEntity(array $entity): array
    {
        return [
            'id' => $entity['id'],
            'type' => $entity['type'],
            'name' => $entity['name'],
            'year' => $entity['year'],
            'country' => $entity['country'],
            'attributes' => json_decode($entity['attributes'] ?? '{}', true),
            'image_url' => $entity['image_url'] ?? null,
            'thumbnail_url' => $entity['thumbnail_url'] ?? null,
            'external_ids' => json_decode($entity['external_ids'] ?? '{}', true),
            'created_at' => $entity['created_at'] ?? null,
            'updated_at' => $entity['updated_at'] ?? null,
        ];
    }
}
