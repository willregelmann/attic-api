<?php

namespace App\GraphQL\FieldResolvers;

use App\Models\UserCollection;
use App\Services\DatabaseOfThingsService;
use App\Services\DbotDataCache;

class UserCollectionImageResolver
{
    protected DatabaseOfThingsService $databaseOfThings;

    protected DbotDataCache $dbotCache;

    public function __construct(DatabaseOfThingsService $databaseOfThings, DbotDataCache $dbotCache)
    {
        $this->databaseOfThings = $databaseOfThings;
        $this->dbotCache = $dbotCache;
    }

    public function __invoke(UserCollection $collection)
    {
        // If no linked DBoT collection, return null
        if (! $collection->linked_dbot_collection_id) {
            return null;
        }

        try {
            // Try to use cached data first (from MyCollectionTree pre-fetch)
            $dbotCollection = $this->dbotCache->getEntity($collection->linked_dbot_collection_id);

            // If not in cache, fetch from DBoT (fallback for non-tree queries)
            if ($dbotCollection === null) {
                $dbotCollection = $this->databaseOfThings->getEntity($collection->linked_dbot_collection_id);
            }

            // Return thumbnail_url (preferred for cards), fallback to image_url
            return $dbotCollection['thumbnail_url'] ?? $dbotCollection['image_url'] ?? null;
        } catch (\Exception $e) {
            \Log::error('UserCollectionImageResolver failed', [
                'collection_id' => $collection->id,
                'linked_dbot_collection_id' => $collection->linked_dbot_collection_id,
                'error' => $e->getMessage(),
            ]);

            // Return null instead of throwing to prevent breaking the entire response
            return null;
        }
    }
}
