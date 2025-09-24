<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\ItemImage;

class ImageStorageService
{
    /**
     * Download and store an image locally
     */
    public function storeImageFromUrl($url, $itemType = 'general', $itemName = '')
    {
        try {
            // Skip if already a local URL
            if (strpos($url, 'localhost:8888') !== false || strpos($url, '/storage/') !== false) {
                return $url;
            }

            // Create directory structure
            $directory = 'public/images/' . Str::slug($itemType);
            if (!Storage::exists($directory)) {
                Storage::makeDirectory($directory);
            }

            // Generate filename from URL or item name
            $filename = $this->generateFilename($url, $itemName);
            $path = $directory . '/' . $filename;

            // Check if we already have this image with valid content
            if (Storage::exists($path) && Storage::size($path) > 1000) {
                return $this->generatePublicUrl($path);
            }

            // Download the image
            $context = stream_context_create([
                'http' => [
                    'timeout' => 30,
                    'header' => "User-Agent: Mozilla/5.0 (compatible; AtticCollector/1.0)\r\n",
                    'ignore_errors' => true // Get content even on HTTP errors to check status
                ]
            ]);

            $imageContent = @file_get_contents($url, false, $context);

            // Check HTTP response code
            $httpResponseHeader = $http_response_header ?? [];
            $statusLine = $httpResponseHeader[0] ?? '';
            if (!preg_match('/HTTP\/\d\.\d\s+200/', $statusLine)) {
                \Log::warning("Failed to download image - HTTP error", [
                    'url' => $url,
                    'status' => $statusLine
                ]);
                return null;
            }

            if ($imageContent === false || strlen($imageContent) < 100) {
                \Log::warning("Failed to download image - empty or too small", [
                    'url' => $url,
                    'size' => strlen($imageContent)
                ]);
                return null;
            }

            // Verify it's actually an image
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->buffer($imageContent);
            if (!in_array($mimeType, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'])) {
                \Log::warning("Downloaded content is not an image", [
                    'url' => $url,
                    'mime' => $mimeType
                ]);
                return null;
            }

            // Store the image
            Storage::put($path, $imageContent);

            // Return the local URL
            return $this->generatePublicUrl($path);
        } catch (\Exception $e) {
            \Log::error('Failed to store image', ['url' => $url, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Generate a filename from URL or item name
     */
    private function generateFilename($url, $itemName)
    {
        if ($itemName) {
            $base = Str::slug($itemName);
        } else {
            $parsed = parse_url($url);
            $base = pathinfo($parsed['path'], PATHINFO_FILENAME);
        }

        // Get extension from URL
        $extension = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION);
        if (!$extension) {
            $extension = 'jpg'; // Default to jpg
        }

        return $base . '.' . $extension;
    }

    /**
     * Generate public URL for stored image
     */
    private function generatePublicUrl($path)
    {
        // Use port 8888 which is mapped to the Laravel container
        $relativePath = str_replace('public/', '', $path);
        return 'http://localhost:8888/storage/' . $relativePath;
    }

    /**
     * Process all images for an item
     */
    public function processItemImages($item)
    {
        $images = $item->images;
        $updated = 0;

        foreach ($images as $image) {
            $localUrl = $this->storeImageFromUrl(
                $image->url,
                $item->type,
                $item->name
            );

            if ($localUrl && $localUrl !== $image->url) {
                $image->url = $localUrl;
                $image->save();
                $updated++;
            }
        }

        return $updated;
    }
}