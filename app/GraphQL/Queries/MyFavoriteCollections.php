<?php

namespace App\GraphQL\Queries;

use App\Services\DatabaseOfThingsService;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class MyFavoriteCollections
{
    private DatabaseOfThingsService $databaseOfThingsService;

    public function __construct(DatabaseOfThingsService $databaseOfThingsService)
    {
        $this->databaseOfThingsService = $databaseOfThingsService;
    }

    public function __invoke($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $user = Auth::guard('sanctum')->user();

        if (! $user) {
            throw new \Exception('Unauthenticated');
        }

        // Get favorited collection IDs from pivot table
        $favoriteCollectionIds = DB::table('user_collection_favorites')
            ->where('user_id', $user->id)
            ->pluck('collection_id')
            ->toArray();

        if (empty($favoriteCollectionIds)) {
            return [];
        }

        // Fetch collection data from Database of Things API
        $collections = $this->databaseOfThingsService->getEntitiesByIds($favoriteCollectionIds);

        // Fetch all collection items in parallel (with caching) - PERFORMANCE OPTIMIZATION
        // This replaces the N+1 query problem where each collection made a separate API call
        // Performance improvement: 10 collections in ~0.5s (parallel) vs ~5s (sequential)
        // Subsequent requests are nearly instant due to 1-hour cache
        $allCollectionItems = $this->databaseOfThingsService->getMultipleCollectionItemsInParallel(
            $favoriteCollectionIds,
            1000 // Fetch up to 1000 items per collection
        );

        $result = [];

        foreach ($favoriteCollectionIds as $collectionId) {
            $collection = $collections[$collectionId] ?? null;

            if (! $collection) {
                continue; // Skip if collection not found in Database of Things
            }

            // Get pre-fetched collection items (already loaded in parallel above)
            $collectionItemsData = $allCollectionItems[$collectionId] ?? ['items' => []];
            $collectionItems = array_map(fn ($item) => $item['entity'], $collectionItemsData['items']);
            $collectionItemIds = array_map(fn ($item) => $item['id'], $collectionItems);

            // Get user's owned items in this collection
            $ownedItemsCount = $user->userItems()
                ->whereIn('entity_id', $collectionItemIds)
                ->count();

            $totalItems = count($collectionItems);
            $completionPercentage = $totalItems > 0
                ? round(($ownedItemsCount / $totalItems) * 100, 2)
                : 0;

            $result[] = [
                'collection' => [
                    'id' => $collection['id'],
                    'name' => $collection['name'],
                    'type' => $collection['type'],
                    'year' => $collection['year'] ?? null,
                    'image_url' => $collection['image_url'] ?? null,
                ],
                'stats' => [
                    'totalItems' => $totalItems,
                    'ownedItems' => $ownedItemsCount,
                    'completionPercentage' => $completionPercentage,
                ],
            ];
        }

        return $result;
    }
}
