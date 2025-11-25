<?php

namespace App\GraphQL\FieldResolvers;

use App\Models\UserCollection;
use App\Models\UserItem;
use App\Models\Wishlist;
use App\Services\DatabaseOfThingsService;
use App\Services\DbotDataCache;

class UserCollectionRepresentativeImagesResolver
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
        // Priority 1: User uploaded image for the collection itself
        $collectionImages = $collection->images;
        if (is_array($collectionImages) && ! empty($collectionImages)) {
            // Use first user-uploaded image as primary
            $firstImage = $collectionImages[0];
            // Image path needs /storage/ prefix for frontend display
            $imagePath = $firstImage['thumbnail'] ?? $firstImage['original'] ?? null;
            if ($imagePath) {
                return ['/storage/'.$imagePath];
            }
        }

        // Fallback: Legacy custom_image (deprecated)
        if ($collection->custom_image) {
            return [$collection->custom_image];
        }

        // Priority 2: DBoT image for linked collection
        if ($collection->linked_dbot_collection_id) {
            try {
                // Try to use cached data first (from MyCollectionTree pre-fetch)
                $dbotCollection = $this->dbotCache->getEntity($collection->linked_dbot_collection_id);

                // If not in cache, fetch from DBoT (fallback for non-tree queries)
                if ($dbotCollection === null) {
                    $dbotCollection = $this->databaseOfThings->getEntity($collection->linked_dbot_collection_id);
                }

                $image = $dbotCollection['thumbnail_url'] ?? $dbotCollection['image_url'] ?? null;
                if ($image) {
                    return [$image];
                }
            } catch (\Exception $e) {
                \Log::warning('Failed to fetch linked DBoT collection image', [
                    'collection_id' => $collection->id,
                    'linked_dbot_collection_id' => $collection->linked_dbot_collection_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Priority 3: Representative sample grid using child items
        // Get first 4 items (owned + wishlisted)
        $items = UserItem::where('parent_collection_id', $collection->id)
            ->orderBy('created_at', 'desc')
            ->take(4)
            ->get();

        $wishlists = Wishlist::where('parent_collection_id', $collection->id)
            ->orderBy('created_at', 'desc')
            ->take(max(0, 4 - $items->count()))
            ->get();

        // Build images array, prioritizing user_images over DBoT images
        $images = [];
        $entityIds = [];

        // Process owned items first
        foreach ($items as $item) {
            // Check for user-uploaded image first
            $userImages = $item->images;
            if (is_array($userImages) && ! empty($userImages)) {
                $firstImage = $userImages[0];
                $thumbnailPath = $firstImage['thumbnail'] ?? $firstImage['original'] ?? null;
                if ($thumbnailPath) {
                    $images[] = '/storage/'.$thumbnailPath;
                }
            } else {
                // No user image, will fetch DBoT image later
                $entityIds[] = $item->entity_id;
            }

            if (count($images) >= 4) {
                break;
            }
        }

        // Process wishlist items if we need more images
        if (count($images) < 4) {
            foreach ($wishlists as $wishlist) {
                $entityIds[] = $wishlist->entity_id;
                if (count($images) + count($entityIds) >= 4) {
                    break;
                }
            }
        }

        // Fetch DBoT images for items that don't have user images
        if (! empty($entityIds) && count($images) < 4) {
            try {
                $entities = $this->databaseOfThings->getEntitiesByIds($entityIds);

                // Extract DBoT image URLs
                foreach ($entityIds as $entityId) {
                    if (count($images) >= 4) {
                        break;
                    }

                    $entity = $entities[$entityId] ?? null;
                    if ($entity) {
                        // Prefer thumbnail for grid display
                        $image = $entity['thumbnail_url'] ?? $entity['image_url'] ?? null;
                        if ($image) {
                            $images[] = $image;
                        }
                    }
                }
            } catch (\Exception $e) {
                \Log::error('UserCollectionRepresentativeImagesResolver: getEntitiesByIds failed', [
                    'collection_id' => $collection->id,
                    'entity_ids' => $entityIds,
                    'error' => $e->getMessage(),
                ]);
                // Continue with images we have
            }
        }

        // If we still don't have enough images, look at subcollections
        if (count($images) < 4) {
            $subcollections = UserCollection::where('parent_collection_id', $collection->id)
                ->orderBy('created_at', 'desc')
                ->take(4 - count($images))
                ->get();

            foreach ($subcollections as $subcollection) {
                if (count($images) >= 4) {
                    break;
                }

                // Check subcollection's user-uploaded image first
                $subImages = $subcollection->images;
                if (is_array($subImages) && ! empty($subImages)) {
                    $firstImage = $subImages[0];
                    $thumbnailPath = $firstImage['thumbnail'] ?? $firstImage['original'] ?? null;
                    if ($thumbnailPath) {
                        $images[] = '/storage/'.$thumbnailPath;

                        continue;
                    }
                }

                // Fallback to subcollection's custom_image (deprecated)
                if ($subcollection->custom_image) {
                    $images[] = $subcollection->custom_image;

                    continue;
                }

                // If subcollection is linked, get its DBoT collection image
                if ($subcollection->linked_dbot_collection_id) {
                    try {
                        // Try to use cached data first
                        $dbotCollection = $this->dbotCache->getEntity($subcollection->linked_dbot_collection_id);

                        // If not in cache, fetch from DBoT
                        if ($dbotCollection === null) {
                            $dbotCollection = $this->databaseOfThings->getEntity($subcollection->linked_dbot_collection_id);
                        }

                        $image = $dbotCollection['thumbnail_url'] ?? $dbotCollection['image_url'] ?? null;
                        if ($image) {
                            $images[] = $image;
                        }
                    } catch (\Exception $e) {
                        // Skip if DBoT collection fetch fails
                    }
                }
            }
        }

        // Ensure we return maximum 4 images
        return array_slice($images, 0, 4);
    }
}
