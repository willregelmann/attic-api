<?php

namespace App\GraphQL\Queries;

use App\Models\Item;
use Illuminate\Support\Facades\Auth;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class MyCollectionStats
{
    public function __invoke($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $user = Auth::guard('sanctum')->user();

        if (!$user) {
            throw new \Exception('Unauthenticated');
        }

        $collection = Item::findOrFail($args['collection_id']);

        // Total items in collection
        $totalItems = $collection->children()
            ->wherePivot('relationship_type', 'contains')
            ->count();

        // Cataloged items (items that exist in the database)
        $catalogedItems = $totalItems;

        // Items owned by the user
        $ownedItemIds = $collection->children()
            ->wherePivot('relationship_type', 'contains')
            ->pluck('items.id');

        $ownedItems = $user->items()
            ->whereIn('items.id', $ownedItemIds)
            ->count();

        // Completion percentage
        $completionPercentage = $totalItems > 0
            ? round(($ownedItems / $totalItems) * 100, 2)
            : 0;

        return [
            'totalItems' => $totalItems,
            'catalogedItems' => $catalogedItems,
            'ownedItems' => $ownedItems,
            'completionPercentage' => $completionPercentage,
        ];
    }
}