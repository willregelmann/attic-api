<?php

namespace App\GraphQL\FieldResolvers;

use App\Models\UserCollection;
use App\Services\UserCollectionService;

class UserCollectionProgressResolver
{
    protected UserCollectionService $service;

    public function __construct(UserCollectionService $service)
    {
        $this->service = $service;
    }

    public function __invoke(UserCollection $collection)
    {
        // Use recursive progress calculation by default
        return $this->service->calculateProgress($collection->id);
    }
}
