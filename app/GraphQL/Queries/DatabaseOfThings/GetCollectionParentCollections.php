<?php

namespace App\GraphQL\Queries\DatabaseOfThings;

use App\Services\DatabaseOfThingsService;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class GetCollectionParentCollections
{
    protected $databaseOfThings;

    public function __construct(DatabaseOfThingsService $databaseOfThings)
    {
        $this->databaseOfThings = $databaseOfThings;
    }

    /**
     * Fetch parent collections for items in a collection from Database of Things
     *
     * @param  mixed  $rootValue
     * @return array
     */
    public function __invoke($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $collectionId = $args['collection_id'];

        $parents = $this->databaseOfThings->getCollectionParentCollections($collectionId);

        // Transform Database of Things response to match GraphQL Entity type
        return array_map(function ($parent) {
            return $this->transformEntity($parent);
        }, $parents);
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
