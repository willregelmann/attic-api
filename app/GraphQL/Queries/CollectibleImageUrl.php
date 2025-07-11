<?php

namespace App\GraphQL\Queries;

class CollectibleImageUrl
{
    public function __invoke($rootValue, array $args, $context, $resolveInfo)
    {
        $collectible = $rootValue;
        $imageUrls = $collectible->image_urls;
        
        if (is_array($imageUrls) && !empty($imageUrls)) {
            return $imageUrls[0];
        }
        
        return null;
    }
}