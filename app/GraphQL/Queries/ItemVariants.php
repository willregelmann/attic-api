<?php

namespace App\GraphQL\Queries;

use App\Models\Item;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class ItemVariants
{
    public function __invoke($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $item = Item::findOrFail($args['item_id']);

        return $item->variants()->get();
    }
}