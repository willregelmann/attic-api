<?php

namespace App\GraphQL\Queries;

use App\Models\UserItem;
use App\Services\DatabaseOfThingsService;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Support\Facades\Auth;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class MyCollectionItems
{
    protected $databaseOfThings;

    public function __construct(DatabaseOfThingsService $databaseOfThings)
    {
        $this->databaseOfThings = $databaseOfThings;
    }

    /**
     * Get all items in the user's collection with entity details from Database of Things
     *
     * @param  mixed  $rootValue
     * @return array
     */
    public function __invoke($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $user = Auth::guard('sanctum')->user();

        if (! $user) {
            throw new \Exception('Unauthenticated');
        }

        // Get user's items
        $userItems = UserItem::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        if ($userItems->isEmpty()) {
            return [];
        }

        // Extract entity IDs
        $entityIds = $userItems->pluck('entity_id')->toArray();

        // Batch fetch entities from Database of Things
        $entitiesById = $this->databaseOfThings->getEntitiesByIds($entityIds);

        // Return entities in the order they were added to collection
        $orderedEntities = [];
        foreach ($userItems as $userItem) {
            if (isset($entitiesById[$userItem->entity_id])) {
                $orderedEntities[] = $entitiesById[$userItem->entity_id];
            }
        }

        return $orderedEntities;
    }
}
