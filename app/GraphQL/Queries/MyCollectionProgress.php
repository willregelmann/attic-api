<?php

namespace App\GraphQL\Queries;

use App\Services\DatabaseOfThingsService;
use App\Services\DbotDataCache;
use App\Services\UserCollectionService;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Support\Facades\Auth;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class MyCollectionProgress
{
    protected UserCollectionService $service;

    protected DatabaseOfThingsService $databaseOfThings;

    protected DbotDataCache $dbotCache;

    public function __construct(
        UserCollectionService $service,
        DatabaseOfThingsService $databaseOfThings,
        DbotDataCache $dbotCache
    ) {
        $this->service = $service;
        $this->databaseOfThings = $databaseOfThings;
        $this->dbotCache = $dbotCache;
    }

    /**
     * Fetch progress for multiple collections efficiently
     *
     * This query is designed to be called separately from myCollectionTree
     * so the frontend can load collection structure immediately while
     * progress data loads in the background.
     */
    public function __invoke($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $user = Auth::guard('sanctum')->user();

        if (! $user) {
            throw new \Exception('Unauthenticated');
        }

        $collectionIds = $args['collection_ids'] ?? [];

        if (empty($collectionIds)) {
            return [];
        }

        // Fetch DBoT counts if not already cached
        // This is the expensive operation we moved out of the main tree query
        if (! $this->dbotCache->isPrefetched()) {
            $this->prefetchDbotCounts($user->id);
        }

        // Use efficient bulk calculation (no N+1)
        $progressData = $this->service->calculateBulkProgress(
            $user->id,
            $collectionIds,
            $this->dbotCache
        );

        // Transform to GraphQL response format
        $results = [];
        foreach ($progressData as $collectionId => $progress) {
            $results[] = [
                'collection_id' => $collectionId,
                'progress' => $progress,
            ];
        }

        return $results;
    }

    /**
     * Pre-fetch DBoT item counts for linked collections
     */
    protected function prefetchDbotCounts(string $userId): void
    {
        $linkedDbotIds = \App\Models\UserCollection::where('user_id', $userId)
            ->whereNotNull('linked_dbot_collection_id')
            ->pluck('linked_dbot_collection_id')
            ->unique()
            ->values()
            ->toArray();

        if (empty($linkedDbotIds)) {
            return;
        }

        try {
            // Fetch item counts in parallel
            $counts = $this->databaseOfThings->getCollectionItemCountsInParallel($linkedDbotIds);
            $this->dbotCache->setCollectionItemCounts($counts);
        } catch (\Exception $e) {
            \Log::error('MyCollectionProgress: Failed to fetch DBoT counts', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            // Continue without counts - progress will show 0 for linked collections
        }
    }
}
