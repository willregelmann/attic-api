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

        // Fetch DBoT collection entity
        $dbotCollection = $this->databaseOfThings->getEntity($collection->linked_dbot_collection_id);

        // Return thumbnail_url (preferred for cards), fallback to image_url
        return $dbotCollection['thumbnail_url'] ?? $dbotCollection['image_url'] ?? null;
    }
}
