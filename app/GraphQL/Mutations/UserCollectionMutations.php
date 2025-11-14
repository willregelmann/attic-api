<?php

namespace App\GraphQL\Mutations;

use App\Models\UserCollection;
use App\Services\ImageUploadService;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class UserCollectionMutations
{
    /**
     * Upload images for a user collection
     */
    public function uploadCollectionImages($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $user = Auth::guard('sanctum')->user();

        if (! $user) {
            throw new \Exception('Unauthenticated');
        }

        $collection = UserCollection::where('user_id', $user->id)
            ->where('id', $args['collection_id'])
            ->firstOrFail();

        if (! isset($args['images']) || count($args['images']) === 0) {
            throw new \Exception('No images provided');
        }

        $imageService = app(ImageUploadService::class);
        $currentImages = $collection->images ?? [];

        try {
            // Validate files
            $imageService->validateFiles($args['images']);

            // Process and store images using collection-specific method
            $newImages = $imageService->processAndStoreCollectionImages($args['images'], $collection->id);

            // Append new images to existing ones
            $collection->images = array_merge($currentImages, $newImages);
            $collection->save();

            Log::info('Images uploaded for UserCollection', [
                'collection_id' => $collection->id,
                'new_images_count' => count($newImages),
                'total_images_count' => count($collection->images)
            ]);

            return $collection;
        } catch (\Exception $e) {
            Log::error('Collection image upload failed', [
                'collection_id' => $collection->id,
                'error' => $e->getMessage()
            ]);
            throw new \Exception("Image upload failed: {$e->getMessage()}");
        }
    }

    /**
     * Remove images from a user collection
     */
    public function removeCollectionImages($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $user = Auth::guard('sanctum')->user();

        if (! $user) {
            throw new \Exception('Unauthenticated');
        }

        $collection = UserCollection::where('user_id', $user->id)
            ->where('id', $args['collection_id'])
            ->firstOrFail();

        $imageService = app(ImageUploadService::class);
        $existingImages = $collection->images ?? [];

        try {
            // Remove images by indices (service handles deletion and re-indexing)
            $updatedImages = $imageService->removeImagesByIndices(
                $existingImages,
                $args['image_indices']
            );

            $collection->images = $updatedImages;
            $collection->save();

            Log::info('Images removed from UserCollection', [
                'collection_id' => $collection->id,
                'indices_removed' => $args['image_indices'],
                'remaining_count' => count($updatedImages)
            ]);

            return $collection;
        } catch (\Exception $e) {
            Log::error('Collection image removal failed', [
                'collection_id' => $collection->id,
                'error' => $e->getMessage()
            ]);
            throw new \Exception("Image removal failed: {$e->getMessage()}");
        }
    }

    /**
     * Reorder collection images
     */
    public function reorderCollectionImages($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $user = Auth::guard('sanctum')->user();

        if (! $user) {
            throw new \Exception('Unauthenticated');
        }

        $collection = UserCollection::where('user_id', $user->id)
            ->where('id', $args['collection_id'])
            ->firstOrFail();

        $currentImages = $collection->images ?? [];
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

        $collection->images = $reorderedImages;
        $collection->save();

        Log::info('Images reordered for UserCollection', [
            'collection_id' => $collection->id,
            'new_order' => $newOrder
        ]);

        return $collection;
    }
}
