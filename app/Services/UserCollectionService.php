<?php

namespace App\Services;

use App\Models\UserCollection;
use Illuminate\Support\Collection;

class UserCollectionService
{
    /**
     * Get collection tree for user
     *
     * @param string $userId
     * @param string|null $parentId
     * @return Collection
     */
    public function getCollectionTree(string $userId, ?string $parentId = null): Collection
    {
        return UserCollection::where('user_id', $userId)
            ->where('parent_collection_id', $parentId)
            ->orderBy('name')
            ->get();
    }
}
