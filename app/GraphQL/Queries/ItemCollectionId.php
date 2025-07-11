<?php

namespace App\GraphQL\Queries;

class ItemCollectionId
{
    public function __invoke($rootValue, array $args, $context, $resolveInfo)
    {
        $item = $rootValue;
        
        if ($item->collectible) {
            $collection = $item->collectible->collections()->first();
            return $collection ? $collection->id : null;
        }
        
        return null;
    }
}