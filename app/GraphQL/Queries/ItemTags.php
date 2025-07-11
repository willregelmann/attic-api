<?php

namespace App\GraphQL\Queries;

class ItemTags
{
    public function __invoke($rootValue, array $args, $context, $resolveInfo)
    {
        $item = $rootValue;
        $tags = [];
        
        if ($item->collectible) {
            // Add tags from collectible metadata
            if (isset($item->collectible->base_attributes['tags'])) {
                $tags = array_merge($tags, $item->collectible->base_attributes['tags']);
            }
            
            // Add collection name as a tag
            $collection = $item->collectible->collections()->first();
            if ($collection) {
                $tags[] = strtolower(str_replace(' ', '-', $collection->name));
            }
        }
        
        // Add generic tags
        if ($item->is_favorite) {
            $tags[] = 'favorite';
        }
        
        $tags[] = 'owned';
        
        return array_unique($tags);
    }
}