<?php

namespace App\GraphQL\Mutations;

use App\Models\Item;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class ToggleItemFavorite
{
    public function __invoke($rootValue, array $args, GraphQLContext $context, $resolveInfo)
    {
        $user = $context->user();
        $item = Item::where('id', $args['id'])
                   ->where('user_id', $user->id)
                   ->firstOrFail();
        
        $item->is_favorite = !$item->is_favorite;
        $item->save();
        
        return $item;
    }
}