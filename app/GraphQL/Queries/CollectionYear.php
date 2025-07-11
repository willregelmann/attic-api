<?php

namespace App\GraphQL\Queries;

class CollectionYear
{
    public function __invoke($rootValue, array $args, $context, $resolveInfo)
    {
        $collection = $rootValue;
        return $collection->metadata['year'] ?? null;
    }
}