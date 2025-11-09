<?php

namespace App\GraphQL\Queries;

use App\Models\UserCollection;
use App\Models\UserItem;
use App\Models\Wishlist;
use App\Services\DatabaseOfThingsService;
use App\Services\UserCollectionService;

class MyCollectionTree
{
    protected UserCollectionService $service;
    protected DatabaseOfThingsService $databaseOfThings;

    public function __construct(
        UserCollectionService $service,
        DatabaseOfThingsService $databaseOfThings
    ) {
        $this->service = $service;
        $this->databaseOfThings = $databaseOfThings;
    }

    public function __invoke($root, array $args)
    {
        $user = auth()->user();
        $parentId = $args['parent_id'] ?? null;

        // Get collections at this level
        $collections = $this->service->getCollectionTree($user->id, $parentId);

        // Get items at this level
        $items = UserItem::where('user_id', $user->id)
            ->where('parent_collection_id', $parentId)
            ->get();

        // Get wishlists at this level
        $wishlists = Wishlist::where('user_id', $user->id)
            ->where('parent_collection_id', $parentId)
            ->get();

        // Get current collection (if not root) - verify ownership
        $currentCollection = null;
        if ($parentId) {
            $currentCollection = UserCollection::where('id', $parentId)
                ->where('user_id', $user->id)
                ->first();
        }

        // Batch fetch entity data for items and wishlists
        $entityIds = $items->pluck('entity_id')
            ->merge($wishlists->pluck('entity_id'))
            ->unique()
            ->values()
            ->toArray();

        $entities = [];
        if (!empty($entityIds)) {
            $entities = $this->databaseOfThings->getEntitiesByIds($entityIds);
        }

        // Transform items to include entity data
        $transformedItems = [];
        foreach ($items as $item) {
            $entityId = $item->entity_id;
            $entity = $entities[$entityId] ?? null;

            if ($entity) {
                $transformedItems[] = [
                    // UserItem fields
                    'user_item_id' => $item->id,
                    'user_id' => $item->user_id,
                    'user_metadata' => $item->metadata,
                    'user_notes' => $item->notes,
                    'user_images' => $item->images,
                    'user_created_at' => $item->created_at,
                    'user_updated_at' => $item->updated_at,

                    // Entity fields (from Database of Things)
                    'id' => $entity['id'],
                    'type' => $entity['type'],
                    'name' => $entity['name'],
                    'year' => $entity['year'] ?? null,
                    'country' => $entity['country'] ?? null,
                    'attributes' => $entity['attributes'] ?? null,
                    'image_url' => $entity['image_url'] ?? null,
                    'thumbnail_url' => $entity['thumbnail_url'] ?? null,
                    'representative_image_urls' => $entity['representative_image_urls'] ?? [],
                    'external_ids' => $entity['external_ids'] ?? null,
                    'created_at' => $entity['created_at'] ?? null,
                    'updated_at' => $entity['updated_at'] ?? null,
                ];
            }
        }

        // Transform wishlists to include entity data
        $transformedWishlists = [];
        foreach ($wishlists as $wishlist) {
            $entityId = $wishlist->entity_id;
            $entity = $entities[$entityId] ?? null;

            if ($entity) {
                $transformedWishlists[] = [
                    // Wishlist fields
                    'wishlist_id' => $wishlist->id,
                    'user_id' => $wishlist->user_id,
                    'wishlist_created_at' => $wishlist->created_at,
                    'wishlist_updated_at' => $wishlist->updated_at,

                    // Entity fields (from Database of Things)
                    'id' => $entity['id'],
                    'type' => $entity['type'],
                    'name' => $entity['name'],
                    'year' => $entity['year'] ?? null,
                    'country' => $entity['country'] ?? null,
                    'attributes' => $entity['attributes'] ?? null,
                    'image_url' => $entity['image_url'] ?? null,
                    'thumbnail_url' => $entity['thumbnail_url'] ?? null,
                    'representative_image_urls' => $entity['representative_image_urls'] ?? [],
                    'external_ids' => $entity['external_ids'] ?? null,
                    'created_at' => $entity['created_at'] ?? null,
                    'updated_at' => $entity['updated_at'] ?? null,
                ];
            }
        }

        return [
            'collections' => $collections,
            'items' => $transformedItems,
            'wishlists' => $transformedWishlists,
            'current_collection' => $currentCollection,
        ];
    }
}
