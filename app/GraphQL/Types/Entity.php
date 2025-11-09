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
     * otherwise finds up to 5 random images from descendant items (up to 3 levels deep)
     * Fetch 5 to know if there are more than 4
     *
     * @param  array  $entity  The entity data
     * @return array
     */
    public function representativeImageUrls(array $entity): array
    {
        // If entity already has an image_url, return empty array
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
