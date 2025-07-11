<?php

namespace App\GraphQL\Queries;

use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class MyCollectionItems
{
    public function __invoke($rootValue, array $args, GraphQLContext $context, $resolveInfo)
    {
        $user = $context->user();
        $collectionId = $args['collection_id'];
        
        return $user->items()
            ->whereHas('collectible', function ($query) use ($collectionId) {
                $query->whereHas('collections', function ($collectionQuery) use ($collectionId) {
                    $collectionQuery->where('collections.id', $collectionId);
                });
            })
            ->orWhere(function ($query) use ($collectionId) {
                // Include custom items that don't have a collectible_id
                $query->whereNull('collectible_id');
            })
            ->with(['collectible', 'collectible.collections'])
            ->get();
    }
}