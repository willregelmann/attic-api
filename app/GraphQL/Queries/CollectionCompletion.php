<?php

namespace App\GraphQL\Queries;

use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class CollectionCompletion
{
    public function __invoke($rootValue, array $args, GraphQLContext $context, $resolveInfo)
    {
        $collection = $rootValue;
        $user = $context->user();
        
        if (!$user) {
            return 0;
        }
        
        $totalCollectibles = $collection->collectibles()->count();
        if ($totalCollectibles === 0) {
            return 0;
        }
        
        $ownedItems = $user->items()
            ->whereHas('collectible', function ($query) use ($collection) {
                $query->whereHas('collections', function ($collectionQuery) use ($collection) {
                    $collectionQuery->where('collections.id', $collection->id);
                });
            })
            ->distinct('collectible_id')
            ->count();
            
        return round(($ownedItems / $totalCollectibles) * 100);
    }
}