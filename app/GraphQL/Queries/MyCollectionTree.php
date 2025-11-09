<?php

namespace App\GraphQL\Queries;

use App\Models\UserCollection;
use App\Models\UserItem;
use App\Models\Wishlist;
use App\Services\UserCollectionService;

class MyCollectionTree
{
    protected UserCollectionService $service;

    public function __construct(UserCollectionService $service)
    {
        $this->service = $service;
    }

    public function resolve($root, array $args)
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

        // Get current collection (if not root)
        $currentCollection = $parentId ? UserCollection::find($parentId) : null;

        return [
            'collections' => $collections,
            'items' => $items,
            'wishlists' => $wishlists,
            'current_collection' => $currentCollection,
        ];
    }
}
