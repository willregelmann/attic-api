<?php

namespace App\GraphQL\Queries;

class CollectibleNumber
{
    public function __invoke($rootValue, array $args, $context, $resolveInfo)
    {
        $collectible = $rootValue;
        return $collectible->base_attributes['number'] ?? $collectible->base_attributes['id'] ?? '#' . $collectible->id;
    }
}