<?php

namespace App\GraphQL\Mutations;

use App\Models\UserCollection;
use App\Services\UserCollectionService;
use GraphQL\Error\UserError;

class MoveUserCollection
{
    protected UserCollectionService $service;

    public function __construct(UserCollectionService $service)
    {
        $this->service = $service;
    }

    public function __invoke($rootValue, array $args)
    {
        $user = auth()->user();
        $collectionId = $args['id'];
        $newParentId = $args['new_parent_id'] ?? null;

        // Find collection and verify ownership
        $collection = UserCollection::where('id', $collectionId)
            ->where('user_id', $user->id)
            ->first();

        if (!$collection) {
            throw new UserError('Collection not found or access denied');
        }

        // Validate new parent ownership if provided
        if ($newParentId) {
            $newParent = UserCollection::where('id', $newParentId)
                ->where('user_id', $user->id)
                ->first();

            if (!$newParent) {
                throw new UserError('Parent collection not found or access denied');
            }

            // Validate move doesn't create circular reference
            $this->service->validateMove($collectionId, $newParentId);
        }

        // Update collection
        $collection->parent_collection_id = $newParentId;
        $collection->save();

        return $collection;
    }
}
