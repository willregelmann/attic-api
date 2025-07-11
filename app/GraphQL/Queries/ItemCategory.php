<?php

namespace App\GraphQL\Queries;

class ItemCategory
{
    public function __invoke($rootValue, array $args, $context, $resolveInfo)
    {
        $item = $rootValue;
        
        if ($item->collectible) {
            return $item->collectible->category ?? 'Collectible';
        }
        
        // For custom items, derive category from name or default
        return 'Custom Item';
    }
}