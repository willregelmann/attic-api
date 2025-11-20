<?php

namespace App\GraphQL\Queries\DatabaseOfThings;

use App\Services\DatabaseOfThingsService;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class GetEntityComponents
{
    protected $databaseOfThings;

    public function __construct(DatabaseOfThingsService $databaseOfThings)
    {
        $this->databaseOfThings = $databaseOfThings;
    }

    /**
     * Get components for an entity from Database of Things
     *
     * @param  mixed  $rootValue
     * @return array
     */
    public function __invoke($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $entityId = $args['entity_id'];

        $entity = $this->databaseOfThings->getEntity($entityId);

        if (! $entity || empty($entity['entity_components'])) {
            return [];
        }

        return array_map(function ($component) {
            return [
                'id' => $component['id'],
                'name' => $component['name'],
                'quantity' => $component['quantity'] ?? null,
                'order' => $component['order'] ?? null,
                'attributes' => is_string($component['attributes'] ?? null)
                    ? json_decode($component['attributes'], true)
                    : ($component['attributes'] ?? []),
                'image_url' => $component['image_url'] ?? null,
                'thumbnail_url' => $component['thumbnail_url'] ?? null,
                'additional_images' => $component['additional_images'] ?? [],
            ];
        }, $entity['entity_components']);
    }
}
