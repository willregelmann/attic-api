<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class ImageUploadService
{
    public const MAX_FILE_SIZE = 10240; // 10MB in KB

    public const MAX_IMAGES = 10;

    public const ALLOWED_TYPES = ['jpg', 'jpeg', 'png', 'webp'];

    public const MAX_DIMENSION = 2000; // Max width/height for originals

    public const THUMBNAIL_SIZE = 200; // Square thumbnail size

    /**
     * Validate uploaded image files
     *
     * @param  array  $files  Array of UploadedFile instances
     *
     * @throws ValidationException
     */
    public function validateFiles(array $files): void
    {
        if (count($files) > self::MAX_IMAGES) {
            throw ValidationException::withMessages([
                'images' => ['You can upload a maximum of '.self::MAX_IMAGES.' images.'],
            ]);
        }

        $validator = Validator::make(
            ['images' => $files],
            [
                'images.*' => [
                    'image',
                    'mimes:'.implode(',', self::ALLOWED_TYPES),
                    'max:'.self::MAX_FILE_SIZE,
                ],
            ]
        );

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    /**
     * Process and store uploaded images
     *
     * @param  array  $files  Array of UploadedFile instances
     * @param  string  $userItemId  UUID of the user item
     * @return array Array of ['original' => path, 'thumbnail' => path]
     */
    public function processAndStoreImages(array $files, string $userItemId): array
    {
        $results = [];
        $manager = new ImageManager(new Driver);

        foreach ($files as $index => $file) {
            $filename = Str::uuid();
            $extension = $file->getClientOriginalExtension();
            $basePath = "user_items/{$userItemId}";

            // Process original (resize if needed)
            $image = $manager->read($file);

            if ($image->width() > self::MAX_DIMENSION || $image->height() > self::MAX_DIMENSION) {
                $image->scale(width: self::MAX_DIMENSION, height: self::MAX_DIMENSION);
            }

            // Optimize and save original
            $originalPath = "{$basePath}/{$filename}-original.{$extension}";
            Storage::disk('public')->put(
                $originalPath,
                $image->encodeByExtension($extension, quality: 85)->toString()
            );

            // Generate thumbnail (square crop)
            $thumbnail = $manager->read($file);
            $thumbnail->cover(self::THUMBNAIL_SIZE, self::THUMBNAIL_SIZE);

            $thumbnailPath = "{$basePath}/{$filename}-thumb.{$extension}";
            Storage::disk('public')->put(
                $thumbnailPath,
                $thumbnail->encodeByExtension($extension, quality: 85)->toString()
            );

            $results[] = [
                'id' => (string) $filename,  // UUID as image identifier for reordering
                'original' => $originalPath,
                'thumbnail' => $thumbnailPath,
            ];
        }

        return $results;
    }

    /**
     * Delete images from storage
     *
     * @param  array  $images  Array of ['original' => path, 'thumbnail' => path]
     */
    public function deleteImages(array $images): void
    {
        foreach ($images as $imagePair) {
            try {
                Storage::disk('public')->delete($imagePair['original']);
            } catch (\Exception $e) {
                Log::warning("Failed to delete original image: {$imagePair['original']}", [
                    'error' => $e->getMessage(),
                ]);
            }

            try {
                Storage::disk('public')->delete($imagePair['thumbnail']);
            } catch (\Exception $e) {
                Log::warning("Failed to delete thumbnail: {$imagePair['thumbnail']}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Remove specific images by index from existing images array
     *
     * @param  array  $existingImages  Full images array from user item
     * @param  array  $indicesToRemove  Array of indices to remove
     * @return array Updated images array and deleted images
     */
    public function removeImagesByIndices(array $existingImages, array $indicesToRemove): array
    {
        $toDelete = [];
        $remaining = [];

        foreach ($existingImages as $index => $image) {
            if (in_array($index, $indicesToRemove)) {
                $toDelete[] = $image;
            } else {
                $remaining[] = $image;
            }
        }

        // Delete the files
        $this->deleteImages($toDelete);

        return $remaining;
    }
}
