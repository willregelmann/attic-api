<?php

namespace App\GraphQL\Queries;

use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class OwnedItemsCount
{
    public function __invoke($rootValue, array $args, GraphQLContext $context, $resolveInfo)
    {
        $collection = $rootValue;
        $user = $context->user();
        
        if (!$user) {
            return 0;
        }
        
        return $user->items()
            ->whereHas('collectible', function ($query) use ($collection) {
                $query->whereHas('collections', function ($collectionQuery) use ($collection) {
                    $collectionQuery->where('collections.id', $collection->id);
                });
            })
            ->distinct('collectible_id')
            ->count();
    }
}