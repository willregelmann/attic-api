<?php

namespace App\GraphQL\Queries;

use App\Services\DatabaseOfThingsService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use GraphQL\Type\Definition\ResolveInfo;
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

        if (!$user) {
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

        $result = [];

        foreach ($favoriteCollectionIds as $collectionId) {
            $collection = $collections[$collectionId] ?? null;

            if (!$collection) {
                continue; // Skip if collection not found in Database of Things
            }

            // Get all items in the collection from Database of Things
            $collectionItemsData = $this->databaseOfThingsService->getCollectionItems($collectionId, 1000);
            $collectionItems = array_map(fn($item) => $item['entity'], $collectionItemsData['items']);
            $collectionItemIds = array_map(fn($item) => $item['id'], $collectionItems);

            // Get user's owned items in this collection
            $ownedItemsCount = $user->items()
                ->whereIn('items.id', $collectionItemIds)
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