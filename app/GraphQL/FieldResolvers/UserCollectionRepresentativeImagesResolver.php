<?php

namespace App\GraphQL\FieldResolvers;

use App\Models\UserCollection;
use App\Models\UserItem;
use App\Models\Wishlist;
use App\Services\DatabaseOfThingsService;

class UserCollectionRepresentativeImagesResolver
{
    protected DatabaseOfThingsService $databaseOfThings;

    public function __construct(DatabaseOfThingsService $databaseOfThings)
    {
        $this->databaseOfThings = $databaseOfThings;
    }

    public function __invoke(UserCollection $collection)
    {
        // If custom image is set, return single-item array
        if ($collection->custom_image) {
            return [$collection->custom_image];
        }

        // Get first 5 items (owned + wishlisted) to determine if there are more than 4
        $items = UserItem::where('parent_collection_id', $collection->id)
            ->take(5)
            ->get();

        $wishlists = Wishlist::where('parent_collection_id', $collection->id)
            ->take(max(0, 5 - $items->count()))
            ->get();

        // Collect entity IDs
        $entityIds = [];
        foreach ($items as $item) {
            $entityIds[] = $item->entity_id;
            if (count($entityIds) >= 5) break;
        }

        foreach ($wishlists as $wishlist) {
            $entityIds[] = $wishlist->entity_id;
            if (count($entityIds) >= 5) break;
        }

        // If no entity IDs, return empty array
        if (empty($entityIds)) {
            return [];
        }

        // Fetch entities from Database of Things
        $entities = $this->databaseOfThings->getEntitiesByIds($entityIds);

        // Extract image URLs
        $images = [];
        foreach ($entityIds as $entityId) {
            $entity = $entities[$entityId] ?? null;
            if ($entity && isset($entity['image_url']) && $entity['image_url']) {
                $images[] = $entity['image_url'];
            }
            if (count($images) >= 5) break;
        }

        return $images;
    }
}
