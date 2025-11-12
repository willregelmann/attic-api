<?php

namespace App\GraphQL\FieldResolvers;

use App\Models\UserCollection;
use App\Services\DatabaseOfThingsService;

class UserCollectionImageResolver
{
    protected DatabaseOfThingsService $databaseOfThings;

    public function __construct(DatabaseOfThingsService $databaseOfThings)
    {
        $this->databaseOfThings = $databaseOfThings;
    }

    public function __invoke(UserCollection $collection)
    {
        // If no linked DBoT collection, return null
        if (!$collection->linked_dbot_collection_id) {
            return null;
        }

        try {
            // Fetch DBoT collection entity
            $dbotCollection = $this->databaseOfThings->getEntity($collection->linked_dbot_collection_id);

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
