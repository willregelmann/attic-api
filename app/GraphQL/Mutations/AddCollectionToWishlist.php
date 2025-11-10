<?php

namespace App\GraphQL\Mutations;

use App\Models\User;
use App\Models\UserCollection;
use App\Services\DatabaseOfThingsService;
use App\Services\UserCollectionService;
use GraphQL\Error\Error as GraphQLError;

/**
 * Add an entire DBoT collection to the user's wishlist
 *
 * Supports two modes:
 * - TRACK: Creates a new custom collection linked to the DBoT collection
 * - ADD_TO_EXISTING: Adds items to an existing user collection (Task 6)
 */
class AddCollectionToWishlist
{
    protected UserCollectionService $collectionService;

    protected DatabaseOfThingsService $dbotService;

    public function __construct(
        UserCollectionService $collectionService,
        DatabaseOfThingsService $dbotService
    ) {
        $this->collectionService = $collectionService;
        $this->dbotService = $dbotService;
    }

    /**
     * Handle the addCollectionToWishlist mutation
     *
     * @param  mixed  $_  Root value (unused)
     * @param  array  $args  GraphQL mutation arguments
     * @return array AddCollectionResult
     *
     * @throws GraphQLError
     */
    public function __invoke($_, array $args): array
    {
        $user = auth()->user();
        $mode = $args['mode'];

        if ($mode === 'track') {
            return $this->handleTrackMode($user, $args);
        }

        if ($mode === 'add_to_existing') {
            return $this->handleAddToExistingMode($user, $args);
        }

        throw new GraphQLError('Invalid mode');
    }

    protected function handleTrackMode(User $user, array $args): array
    {
        // 1. Validate required fields
        if (empty($args['new_collection_name']) || trim($args['new_collection_name']) === '') {
            throw new GraphQLError('new_collection_name is required and cannot be empty for TRACK mode');
        }

        // 2. Validate DBoT collection exists
        $this->validateDbotCollection($args['dbot_collection_id']);

        // 3. Validate parent collection (if provided)
        $this->validateParentCollection($user, $args['target_collection_id'] ?? null);

        // 4. Create tracked collection
        $createdCollection = $this->collectionService->createTrackedCollection(
            $user->id,
            $args['dbot_collection_id'],
            $args['new_collection_name'],
            $args['target_collection_id'] ?? null // target_collection_id is parent in TRACK mode
        );

        // 5. Get all items from DBoT collection
        $dbotResult = $this->dbotService->getCollectionItems($args['dbot_collection_id']);
        $dbotItems = $dbotResult['items'] ?? [];

        // Extract entity IDs from items
        $entityIds = array_map(fn ($item) => $item['entity']['id'], $dbotItems);

        // 6. Bulk add items to wishlist
        $result = $this->collectionService->bulkAddToWishlist(
            $user->id,
            $entityIds,
            $createdCollection->id
        );

        // 7. Return AddCollectionResult
        return [
            'created_collection' => $createdCollection,
            'items_added' => $result['items_added'],
            'items_already_owned' => 0, // No filtering in TRACK mode
            'items_skipped' => $result['items_skipped'],
        ];
    }

    protected function handleAddToExistingMode(User $user, array $args): array
    {
        // 1. Validate required fields
        if (empty($args['target_collection_id'])) {
            throw new GraphQLError('target_collection_id is required for ADD_TO_EXISTING mode');
        }

        // 2. Validate DBoT collection exists
        $this->validateDbotCollection($args['dbot_collection_id']);

        // 3. Validate collection ownership
        $targetCollection = UserCollection::find($args['target_collection_id']);
        if (! $targetCollection || $targetCollection->user_id !== $user->id) {
            throw new GraphQLError('Collection not found or you do not have permission to modify it');
        }

        // 4. Get filtered items (skip items already in target collection)
        $filteredResult = $this->collectionService->getItemsToAddToWishlist(
            $user->id,
            $args['dbot_collection_id'],
            $args['target_collection_id']
        );

        // 5. Extract entity IDs from filtered items
        $entityIds = array_map(fn ($item) => $item['entity']['id'], $filteredResult['items_to_add']);

        // 6. Bulk add filtered items to wishlist
        $result = $this->collectionService->bulkAddToWishlist(
            $user->id,
            $entityIds,
            $args['target_collection_id']
        );

        // 7. Return AddCollectionResult
        return [
            'created_collection' => null, // No collection created in ADD_TO_EXISTING mode
            'items_added' => $result['items_added'],
            'items_already_owned' => $filteredResult['already_owned_count'],
            'items_skipped' => $filteredResult['already_wishlisted_count'] + $result['items_skipped'],
        ];
    }

    /**
     * Validate parent collection exists and belongs to user
     *
     * @param  User  $user  The authenticated user
     * @param  string|null  $parentCollectionId  The parent collection ID to validate
     * @return void
     *
     * @throws GraphQLError If parent collection doesn't exist or doesn't belong to user
     */
    protected function validateParentCollection(User $user, ?string $parentCollectionId): void
    {
        if ($parentCollectionId === null) {
            return; // Null parent is valid (root level)
        }

        $parentCollection = UserCollection::find($parentCollectionId);
        if (! $parentCollection || $parentCollection->user_id !== $user->id) {
            throw new GraphQLError('Parent collection not found or you do not have permission to use it');
        }
    }

    /**
     * Validate DBoT collection exists
     *
     * @param  string  $dbotCollectionId  The DBoT collection ID to validate
     * @return void
     *
     * @throws GraphQLError If DBoT collection doesn't exist
     */
    protected function validateDbotCollection(string $dbotCollectionId): void
    {
        $dbotCollection = $this->dbotService->getCollection($dbotCollectionId);
        if ($dbotCollection === null) {
            throw new GraphQLError('Database of Things collection not found');
        }
    }
}
