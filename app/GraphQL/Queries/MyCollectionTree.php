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

        // For linked collections, fetch ALL DBoT items and overlay ownership
        $isLinkedCollection = $currentCollection && $currentCollection->linked_dbot_collection_id;
        $dbotOrder = [];
        $allDbotItems = [];

        if ($isLinkedCollection) {
            // Fetch all items by passing a very large limit (no pagination limit)
            $dbotResponse = $this->databaseOfThings->getCollectionItems(
                $currentCollection->linked_dbot_collection_id,
                PHP_INT_MAX  // Fetch all items, no limit
            );

            // Build ordering map and collect all DBoT items
            foreach ($dbotResponse['items'] as $index => $item) {
                $entity = $item['entity'];
                $entityId = $entity['id'];
                $dbotOrder[$entityId] = $item['order'] ?? $index;
                $allDbotItems[$entityId] = $entity;
            }
        }

        // Determine which entity IDs to fetch
        $entityIds = [];
        if ($isLinkedCollection) {
            // For linked collections, we already have all entities from DBoT
            $entityIds = array_keys($allDbotItems);
        } else {
            // For regular collections, fetch entities for owned/wishlisted items
            // Filter out null entity_ids (custom items)
            $entityIds = $items->pluck('entity_id')
                ->merge($wishlists->pluck('entity_id'))
                ->filter()  // Remove null values (custom items)
                ->unique()
                ->values()
                ->toArray();
        }

        // Get entity data
        $entities = [];
        if ($isLinkedCollection) {
            // Use entities from DBoT response
            $entities = $allDbotItems;
        } elseif (!empty($entityIds)) {
            // Fetch from DBoT service
            $entities = $this->databaseOfThings->getEntitiesByIds($entityIds);
        }

        // Create lookup maps for owned and wishlisted items
        $ownedItemsMap = [];
        foreach ($items as $item) {
            $ownedItemsMap[$item->entity_id] = $item;
        }

        $wishlistedItemsMap = [];
        foreach ($wishlists as $wishlist) {
            $wishlistedItemsMap[$wishlist->entity_id] = $wishlist;
        }

        // Transform items to include entity data
        $transformedItems = [];
        $transformedWishlists = [];

        if ($isLinkedCollection) {
            // For linked collections, iterate over ALL DBoT entities
            foreach ($entities as $entityId => $entity) {
                $ownedItem = $ownedItemsMap[$entityId] ?? null;
                $wishlistItem = $wishlistedItemsMap[$entityId] ?? null;

                if ($ownedItem) {
                    // Item is owned - add to items list
                    $transformedItems[] = [
                        // UserItem fields
                        'user_item_id' => $ownedItem->id,
                        'user_id' => $ownedItem->user_id,
                        'parent_collection_id' => $ownedItem->parent_collection_id,
                        'variant_id' => $ownedItem->variant_id,
                        'user_metadata' => $ownedItem->metadata,
                        'user_notes' => $ownedItem->notes,
                        'user_images' => $ownedItem->images,
                        'user_created_at' => $ownedItem->created_at,
                        'user_updated_at' => $ownedItem->updated_at,

                        // Entity fields
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
                        'entity_variants' => $entity['entity_variants'] ?? null,
                        'created_at' => $entity['created_at'] ?? null,
                        'updated_at' => $entity['updated_at'] ?? null,
                    ];
                } elseif ($wishlistItem) {
                    // Item is wishlisted - add to wishlist
                    $transformedWishlists[] = [
                        // Wishlist fields
                        'wishlist_id' => $wishlistItem->id,
                        'user_id' => $wishlistItem->user_id,
                        'variant_id' => $wishlistItem->variant_id,
                        'wishlist_created_at' => $wishlistItem->created_at,
                        'wishlist_updated_at' => $wishlistItem->updated_at,

                        // Entity fields
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
                        'entity_variants' => $entity['entity_variants'] ?? null,
                        'created_at' => $entity['created_at'] ?? null,
                        'updated_at' => $entity['updated_at'] ?? null,
                    ];
                } else {
                    // Item is untracked - add to wishlist as "available to add"
                    $transformedWishlists[] = [
                        // No wishlist fields (not actually wishlisted)
                        'wishlist_id' => null,
                        'user_id' => $user->id,
                        'variant_id' => null,
                        'wishlist_created_at' => null,
                        'wishlist_updated_at' => null,

                        // Entity fields
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
                        'entity_variants' => $entity['entity_variants'] ?? null,
                        'created_at' => $entity['created_at'] ?? null,
                        'updated_at' => $entity['updated_at'] ?? null,
                    ];
                }
            }
        } else {
            // For regular collections, only show owned and wishlisted items
            foreach ($items as $item) {
                if ($item->isCustomItem()) {
                    // Custom item - use data from user_items table
                    $transformedItems[] = [
                        // UserItem fields
                        'user_item_id' => $item->id,
                        'user_id' => $item->user_id,
                        'parent_collection_id' => $item->parent_collection_id,
                        'variant_id' => $item->variant_id,
                        'user_metadata' => $item->metadata,
                        'user_notes' => $item->notes,
                        'user_images' => $item->images,
                        'user_created_at' => $item->created_at,
                        'user_updated_at' => $item->updated_at,

                        // Entity fields (mostly null for custom items)
                        'id' => null,
                        'type' => 'custom',
                        'name' => $item->name,
                        'year' => null,
                        'country' => null,
                        'attributes' => null,
                        'image_url' => $item->images[0]['original'] ?? null,
                        'thumbnail_url' => $item->images[0]['thumbnail'] ?? null,
                        'representative_image_urls' => [],
                        'external_ids' => null,
                        'entity_variants' => [],
                        'created_at' => null,
                        'updated_at' => null,
                    ];
                } else {
                    // DBoT item - fetch entity data
                    $entityId = $item->entity_id;
                    $entity = $entities[$entityId] ?? null;

                    if ($entity) {
                        $transformedItems[] = [
                            // UserItem fields
                            'user_item_id' => $item->id,
                            'user_id' => $item->user_id,
                            'parent_collection_id' => $item->parent_collection_id,
                            'variant_id' => $item->variant_id,
                            'user_metadata' => $item->metadata,
                            'user_notes' => $item->notes,
                            'user_images' => $item->images,
                            'user_created_at' => $item->created_at,
                            'user_updated_at' => $item->updated_at,

                            // Entity fields
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
                            'entity_variants' => $entity['entity_variants'] ?? null,
                            'created_at' => $entity['created_at'] ?? null,
                            'updated_at' => $entity['updated_at'] ?? null,
                        ];
                    }
                }
            }

            foreach ($wishlists as $wishlist) {
                $entityId = $wishlist->entity_id;
                $entity = $entities[$entityId] ?? null;

                if ($entity) {
                    $transformedWishlists[] = [
                        // Wishlist fields
                        'wishlist_id' => $wishlist->id,
                        'user_id' => $wishlist->user_id,
                        'variant_id' => $wishlist->variant_id,
                        'wishlist_created_at' => $wishlist->created_at,
                        'wishlist_updated_at' => $wishlist->updated_at,

                        // Entity fields
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
                        'entity_variants' => $entity['entity_variants'] ?? null,
                        'created_at' => $entity['created_at'] ?? null,
                        'updated_at' => $entity['updated_at'] ?? null,
                    ];
                }
            }
        }

        // Sort items and wishlists by DBoT order if this is a linked collection
        if (!empty($dbotOrder)) {
            usort($transformedItems, function ($a, $b) use ($dbotOrder) {
                $orderA = $dbotOrder[$a['id']] ?? PHP_INT_MAX;
                $orderB = $dbotOrder[$b['id']] ?? PHP_INT_MAX;
                return $orderA <=> $orderB;
            });

            usort($transformedWishlists, function ($a, $b) use ($dbotOrder) {
                $orderA = $dbotOrder[$a['id']] ?? PHP_INT_MAX;
                $orderB = $dbotOrder[$b['id']] ?? PHP_INT_MAX;
                return $orderA <=> $orderB;
            });
        }

        return [
            'collections' => $collections,
            'items' => $transformedItems,
            'wishlists' => $transformedWishlists,
            'current_collection' => $currentCollection,
        ];
    }
}
