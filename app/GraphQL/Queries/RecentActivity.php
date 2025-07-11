<?php

namespace App\GraphQL\Queries;

use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class RecentActivity
{
    public function __invoke($rootValue, array $args, GraphQLContext $context, $resolveInfo)
    {
        $collection = $rootValue;
        $user = $context->user();
        
        if (!$user) {
            return 'No recent activity';
        }
        
        $recentItems = $user->items()
            ->whereHas('collectible', function ($query) use ($collection) {
                $query->whereHas('collections', function ($collectionQuery) use ($collection) {
                    $collectionQuery->where('collections.id', $collection->id);
                });
            })
            ->where('created_at', '>=', now()->subDays(30))
            ->count();
            
        if ($recentItems === 0) {
            return 'No recent activity';
        } elseif ($recentItems === 1) {
            return '1 item added this month';
        } else {
            return "{$recentItems} items added this month";
        }
    }
}