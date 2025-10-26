<?php

namespace App\GraphQL\Queries\DatabaseOfThings;

use App\Services\DatabaseOfThingsService;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class GetEntity
{
    protected $databaseOfThings;

    public function __construct(DatabaseOfThingsService $databaseOfThings)
    {
        $this->databaseOfThings = $databaseOfThings;
    }

    /**
     * Fetch a single entity from Database of Things
     *
     * @param mixed $rootValue
     * @param array $args
     * @param GraphQLContext $context
     * @param ResolveInfo $resolveInfo
     * @return array|null
     */
    public function __invoke($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $entityId = $args['id'];

        $entity = $this->databaseOfThings->getEntity($entityId);

        if (!$entity) {
            return null;
        }

        return $this->transformEntity($entity);
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
            'external_ids' => json_decode($entity['external_ids'] ?? '{}', true),
            'created_at' => $entity['created_at'] ?? null,
            'updated_at' => $entity['updated_at'] ?? null,
        ];
    }
}
