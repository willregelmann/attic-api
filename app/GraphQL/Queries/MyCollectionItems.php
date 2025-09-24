<?php

namespace App\GraphQL\Queries;

use App\Models\UserItem;
use Illuminate\Support\Facades\Auth;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class MyCollectionItems
{
    public function __invoke($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $user = Auth::guard('sanctum')->user();

        if (!$user) {
            throw new \Exception('Unauthenticated');
        }

        return UserItem::where('user_id', $user->id)
            ->whereHas('item.parents', function ($query) use ($args) {
                $query->where('items.id', $args['collection_id'])
                    ->where('relationship_type', 'contains');
            })
            ->with('item')
            ->get();
    }
}