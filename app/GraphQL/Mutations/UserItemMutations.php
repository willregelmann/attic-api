<?php

namespace App\GraphQL\Mutations;

use App\Models\UserItem;
use App\Services\ImageUploadService;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class UserItemMutations
{
    /**
     * Add an item to user's collection
     */
    public function addItemToMyCollection($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        Log::info('Adding item to collection', $args);

        // Get the authenticated user
        $user = Auth::guard('sanctum')->user();

        if (! $user) {
            throw new \Exception('Unauthenticated');
        }

        $userId = $user->id;

        // Note: entity_id references Supabase entity UUID - no local validation possible

        // Create the UserItem record (without images initially)
        $userItem = new UserItem;
        $userItem->user_id = $userId;
        $userItem->entity_id = $args['entity_id'];
        $userItem->metadata = $args['metadata'] ?? null;
        $userItem->notes = $args['notes'] ?? null;
        $userItem->save();

        // Handle image uploads if provided
        if (isset($args['images']) && count($args['images']) > 0) {
            $imageService = app(ImageUploadService::class);

            try {
                // Validate files
                $imageService->validateFiles($args['images']);

                // Process and store images (returns array of [{original, thumbnail}])
                $processedImages = $imageService->processAndStoreImages($args['images'], $userItem->id);

                // Update UserItem with image paths
                $userItem->images = $processedImages;
                $userItem->save();

                Log::info('Images uploaded for UserItem', [
                    'user_item_id' => $userItem->id,
                    'image_count' => count($processedImages)
                ]);
            } catch (\Exception $e) {
                Log::error('Image upload failed', [
                    'user_item_id' => $userItem->id,
                    'error' => $e->getMessage()
                ]);
                throw new \Exception("Image upload failed: {$e->getMessage()}");
            }
        }

        // Load user relationship for GraphQL response
        $userItem->load(['user']);

        Log::info('UserItem created', [
            'id' => $userItem->id,
            'images_count' => count($userItem->images ?? [])
        ]);

        return $userItem;
    }

    /**
     * Update user's item metadata and images
     */
    public function updateMyItem($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $user = Auth::guard('sanctum')->user();

        if (! $user) {
            throw new \Exception('Unauthenticated');
        }

        $userItem = UserItem::where('user_id', $user->id)
            ->where('id', $args['user_item_id'])
            ->firstOrFail();

        if (isset($args['metadata'])) {
            $userItem->metadata = array_merge(
                $userItem->metadata ?? [],
                $args['metadata']
            );
        }

        if (isset($args['notes'])) {
            $userItem->notes = $args['notes'];
        }

        // Handle image removal
        if (isset($args['remove_image_indices']) && count($args['remove_image_indices']) > 0) {
            $imageService = app(ImageUploadService::class);
            $existingImages = $userItem->images ?? [];

            try {
                // Remove images by indices (service handles deletion and re-indexing)
                $updatedImages = $imageService->removeImagesByIndices(
                    $existingImages,
                    $args['remove_image_indices']
                );

                $userItem->images = $updatedImages;

                Log::info('Images removed from UserItem', [
                    'user_item_id' => $userItem->id,
                    'indices_removed' => $args['remove_image_indices'],
                    'remaining_count' => count($updatedImages)
                ]);
            } catch (\Exception $e) {
                Log::error('Image removal failed', [
                    'user_item_id' => $userItem->id,
                    'error' => $e->getMessage()
                ]);
                throw new \Exception("Image removal failed: {$e->getMessage()}");
            }
        }

        // Handle new image uploads
        if (isset($args['images']) && count($args['images']) > 0) {
            $imageService = app(ImageUploadService::class);
            $currentImages = $userItem->images ?? [];

            try {
                // Validate files
                $imageService->validateFiles($args['images']);

                // Process and store new images
                $newImages = $imageService->processAndStoreImages($args['images'], $userItem->id);

                // Append new images to existing ones
                $userItem->images = array_merge($currentImages, $newImages);

                Log::info('Images added to UserItem', [
                    'user_item_id' => $userItem->id,
                    'new_images_count' => count($newImages),
                    'total_images_count' => count($userItem->images)
                ]);
            } catch (\Exception $e) {
                Log::error('Image upload failed', [
                    'user_item_id' => $userItem->id,
                    'error' => $e->getMessage()
                ]);
                throw new \Exception("Image upload failed: {$e->getMessage()}");
            }
        }

        $userItem->save();
        $userItem->load(['user']);

        return $userItem;
    }

    /**
     * Remove item from user's collection
     */
    public function removeItemFromMyCollection($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $user = Auth::guard('sanctum')->user();

        if (! $user) {
            throw new \Exception('Unauthenticated');
        }

        $deleted = UserItem::where('user_id', $user->id)
            ->where('entity_id', $args['entity_id'])
            ->delete();

        if ($deleted === 0) {
            throw new \Exception('Item not found in your collection');
        }

        return 'Item removed from collection';
    }

    /**
     * Reorder images for a user item
     */
    public function reorderItemImages($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $user = Auth::guard('sanctum')->user();

        if (! $user) {
            throw new \Exception('Unauthenticated');
        }

        $userItem = UserItem::where('user_id', $user->id)
            ->where('id', $args['user_item_id'])
            ->firstOrFail();

        $currentImages = $userItem->images ?? [];
        $newOrder = $args['image_ids'];

        // Create a map of id => image data
        $imageMap = [];
        foreach ($currentImages as $image) {
            if (isset($image['id'])) {
                $imageMap[$image['id']] = $image;
            }
        }

        // Rebuild array in new order
        $reorderedImages = [];
        foreach ($newOrder as $imageId) {
            if (isset($imageMap[$imageId])) {
                $reorderedImages[] = $imageMap[$imageId];
            }
        }

        // Validate all images are accounted for
        if (count($reorderedImages) !== count($currentImages)) {
            throw new \Exception('Invalid image IDs provided for reordering');
        }

        $userItem->images = $reorderedImages;
        $userItem->save();
        $userItem->load(['user']);

        Log::info('Images reordered for UserItem', [
            'user_item_id' => $userItem->id,
            'new_order' => $newOrder
        ]);

        return $userItem;
    }
}
