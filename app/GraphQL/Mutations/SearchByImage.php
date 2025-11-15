<?php

namespace App\GraphQL\Mutations;

use App\Services\DatabaseOfThingsService;
use Illuminate\Support\Facades\Log;

class SearchByImage
{
    /**
     * Search for visually similar items by uploading an image
     *
     * @param  null  $_
     * @param  array  $args
     * @return array
     */
    public function __invoke($_, array $args)
    {
        $image = $args['image'];
        $limit = $args['limit'] ?? 20;
        $minSimilarity = $args['min_similarity'] ?? 0.75;

        Log::info('Image search requested', [
            'filename' => $image->getClientOriginalName(),
            'size' => $image->getSize(),
            'mime' => $image->getMimeType(),
            'limit' => $limit,
            'min_similarity' => $minSimilarity,
        ]);

        // Validate image
        if (! $image->isValid()) {
            throw new \Exception('Uploaded file is not valid');
        }

        // Store image temporarily
        $tempPath = $image->getRealPath();
        $mimeType = $image->getMimeType();

        try {
            // Use DatabaseOfThingsService to search by image
            $dbotService = app(DatabaseOfThingsService::class);
            $results = $dbotService->searchByImageFile($tempPath, $limit, $minSimilarity, $mimeType);

            Log::info('Image search completed', [
                'result_count' => count($results),
                'filename' => $image->getClientOriginalName(),
            ]);

            return $results;

        } catch (\Exception $e) {
            Log::error('Image search failed', [
                'error' => $e->getMessage(),
                'filename' => $image->getClientOriginalName(),
            ]);

            throw new \Exception('Image search failed: '.$e->getMessage());
        }
    }
}
