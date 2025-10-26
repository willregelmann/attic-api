<?php

namespace App\GraphQL\Queries\DatabaseOfThings;

use App\Services\DatabaseOfThingsService;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class GetItemParents
{
    protected $databaseOfThings;

    public function __construct(DatabaseOfThingsService $databaseOfThings)
    {
        $this->databaseOfThings = $databaseOfThings;
    }

    /**
     * Fetch parent collections for an item from Database of Things
     *
     * @param mixed $rootValue
     * @param array $args
     * @param GraphQLContext $context
     * @param ResolveInfo $resolveInfo
     * @return array
     */
    public function __invoke($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $itemId = $args['item_id'];

        $parents = $this->databaseOfThings->getItemParents($itemId);

        // Transform Database of Things response to match GraphQL Entity type
        return array_map(function ($parent) {
            return $this->transformEntity($parent);
        }, array_values($parents));
    }

    /**
     * Transform Database of Things entity to GraphQL Entity format
     * Recursively transforms nested parents
     *
     * @param array $entity
     * @return array
     */
    private function transformEntity(array $entity): array
    {
        $transformed = [
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

        // Recursively transform parent entities
        if (isset($entity['parents']) && is_array($entity['parents'])) {
            $transformed['parents'] = array_map(function ($parent) {
                return $this->transformEntity($parent);
            }, $entity['parents']);
        } else {
            $transformed['parents'] = null;
        }

        return $transformed;
    }
}
