<?php

namespace App\GraphQL\Queries;

class ItemCollectionName
{
    public function __invoke($rootValue, array $args, $context, $resolveInfo)
    {
        $item = $rootValue;
        
        if ($item->collectible) {
            $collection = $item->collectible->collections()->first();
            return $collection ? $collection->name : null;
        }
        
        return null;
    }
}