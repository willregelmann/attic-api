<?php

namespace App\GraphQL\FieldResolvers;

use App\Models\UserCollection;
use App\Services\UserCollectionService;

class UserCollectionProgressResolver
{
    protected UserCollectionService $service;

    public function __construct(UserCollectionService $service)
    {
        $this->service = $service;
    }

    public function __invoke(UserCollection $collection)
    {
        try {
            // Use recursive progress calculation by default
            return $this->service->calculateProgress($collection->id);
        } catch (\Exception $e) {
            \Log::error('UserCollectionProgressResolver failed', [
                'collection_id' => $collection->id,
                'error' => $e->getMessage(),
            ]);

            // Return empty progress to prevent breaking the entire response
            return [
                'owned_count' => 0,
                'wishlist_count' => 0,
                'total_count' => 0,
                'percentage' => 0.0,
                'official_owned_count' => null,
                'official_total_count' => null,
                'official_percentage' => null,
            ];
        }
    }
}
