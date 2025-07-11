<?php

namespace App\GraphQL\Queries;

class ItemAcquiredDate
{
    public function __invoke($rootValue, array $args, $context, $resolveInfo)
    {
        $item = $rootValue;
        return $item->created_at->format('Y-m-d');
    }
}