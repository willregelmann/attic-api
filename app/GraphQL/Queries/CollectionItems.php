<?php

namespace App\GraphQL\Queries;

use App\Models\Item;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class CollectionItems
{
    public function __invoke($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $collection = Item::findOrFail($args['collection_id']);

        return $collection->children()
            ->wherePivot('relationship_type', 'contains')
            ->get();
    }
}