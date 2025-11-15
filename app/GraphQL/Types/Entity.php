<?php

namespace App\GraphQL\Types;

use App\Services\DatabaseOfThingsService;

class Entity
{
    protected $databaseOfThings;

    public function __construct(DatabaseOfThingsService $databaseOfThings)
    {
        $this->databaseOfThings = $databaseOfThings;
    }

    /**
     * Resolve representative_image_urls field
     *
     * Returns empty array if collection has its own image_url,
     * otherwise finds up to 5 random images using breadth-first search (prefers direct children)
     * Only searches deeper levels if current level has no images (up to 3 levels deep)
     * Fetch 5 to know if there are more than 4
     *
     * Note: entity data is normalized by DatabaseOfThingsService, so image_url is already
     * flattened from entity_primary_image to maintain backward compatibility
     *
     * @param  array  $entity  The entity data
     * @return array
     */
    public function representativeImageUrls(array $entity): array
    {
        // If entity already has an image_url, return empty array
        // (image_url is flattened from entity_primary_image by normalizeEntityImages)
        if (!empty($entity['image_url'])) {
            return [];
        }

        // Only compute representative images for collections
        if ($entity['type'] !== 'collection') {
            return [];
        }

        // Get representative images from descendants
        return $this->databaseOfThings->getRepresentativeImages($entity['id']);
    }
}
