<?php

namespace App\GraphQL\Queries;

use App\Models\Item;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class SearchItems
{
    public function __invoke($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        // Use whereRaw with LOWER() for case-insensitive search
        // This works across both PostgreSQL and MySQL
        return Item::whereRaw('LOWER(name) LIKE LOWER(?)', ['%' . $args['name'] . '%'])
            ->limit(50)  // Limit results to prevent overwhelming the UI
            ->get();
    }
}