<?php

namespace App\GraphQL\Queries;

use Illuminate\Support\Facades\Auth;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class MyFavoriteCollections
{
    public function __invoke($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $user = Auth::guard('sanctum')->user();

        if (!$user) {
            throw new \Exception('Unauthenticated');
        }

        $collections = $user->favoriteCollections()->get();

        $result = [];

        foreach ($collections as $collection) {
            // Get all items in the collection
            $collectionItems = $collection->children()
                ->wherePivot('relationship_type', 'contains')
                ->get();

            // Get user's owned items in this collection
            $collectionItemIds = $collectionItems->pluck('id');
            $ownedItems = $user->items()
                ->whereIn('items.id', $collectionItemIds)
                ->get();

            $totalItems = $collection->metadata['total_cards'] ?? $collectionItems->count();
            $catalogedItems = $collectionItems->count();
            $ownedItemsCount = $ownedItems->count();
            $completionPercentage = $totalItems > 0
                ? round(($ownedItemsCount / $totalItems) * 100, 2)
                : 0;

            $result[] = [
                'collection' => $collection,
                'stats' => [
                    'totalItems' => $totalItems,
                    'catalogedItems' => $catalogedItems,
                    'ownedItems' => $ownedItemsCount,
                    'completionPercentage' => $completionPercentage,
                ],
            ];
        }

        return $result;
    }
}