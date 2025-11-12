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

        // Get first 4 items (owned + wishlisted)
        $items = UserItem::where('parent_collection_id', $collection->id)
            ->take(4)
            ->get();

        $wishlists = Wishlist::where('parent_collection_id', $collection->id)
            ->take(max(0, 4 - $items->count()))
            ->get();

        // Collect entity IDs
        $entityIds = [];
        foreach ($items as $item) {
            $entityIds[] = $item->entity_id;
            if (count($entityIds) >= 4) break;
        }

        foreach ($wishlists as $wishlist) {
            $entityIds[] = $wishlist->entity_id;
            if (count($entityIds) >= 4) break;
        }

        // Fetch entities from Database of Things for items/wishlists
        $images = [];
        if (!empty($entityIds)) {
            try {
                $entities = $this->databaseOfThings->getEntitiesByIds($entityIds);

                // Extract image URLs
                foreach ($entityIds as $entityId) {
                    $entity = $entities[$entityId] ?? null;
                    if ($entity && isset($entity['image_url']) && $entity['image_url']) {
                        $images[] = $entity['image_url'];
                    }
                    if (count($images) >= 4) break;
                }
            } catch (\Exception $e) {
                \Log::error('UserCollectionRepresentativeImagesResolver: getEntitiesByIds failed', [
                    'collection_id' => $collection->id,
                    'entity_ids' => $entityIds,
                    'error' => $e->getMessage(),
                ]);
                // Continue with empty images array
            }
        }

        // If we don't have enough images, look at subcollections
        if (count($images) < 4) {
            $subcollections = UserCollection::where('parent_collection_id', $collection->id)
                ->take(4 - count($images))
                ->get();

            foreach ($subcollections as $subcollection) {
                // If subcollection is linked, get its DBoT collection image
                if ($subcollection->linked_dbot_collection_id) {
                    try {
                        $dbotCollection = $this->databaseOfThings->getEntity($subcollection->linked_dbot_collection_id);
                        $image = $dbotCollection['thumbnail_url'] ?? $dbotCollection['image_url'] ?? null;
                        if ($image) {
                            $images[] = $image;
                        }
                    } catch (\Exception $e) {
                        // Skip if DBoT collection fetch fails
                    }
                } elseif ($subcollection->custom_image) {
                    // Use custom image if set
                    $images[] = $subcollection->custom_image;
                }

                if (count($images) >= 4) break;
            }
        }

        // Ensure we return maximum 4 images
        return array_slice($images, 0, 4);
    }
}
