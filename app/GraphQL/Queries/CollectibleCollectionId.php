<?php

namespace App\GraphQL\Queries;

class CollectibleCollectionId
{
    public function __invoke($rootValue, array $args, $context, $resolveInfo)
    {
        $collectible = $rootValue;
        $firstCollection = $collectible->collections()->first();
        return $firstCollection ? $firstCollection->id : null;
    }
}