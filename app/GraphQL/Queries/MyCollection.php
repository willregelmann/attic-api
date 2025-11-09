<?php

namespace App\GraphQL\Queries;

use App\Models\UserItem;
use App\Services\DatabaseOfThingsService;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Support\Facades\Auth;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class MyCollection
{
    protected $databaseOfThings;

    public function __construct(DatabaseOfThingsService $databaseOfThings)
    {
        $this->databaseOfThings = $databaseOfThings;
    }

    /**
     * Get all owned entities for the authenticated user
     * Returns UserItemWithEntity combining user data with entity data
     */
    public function __invoke($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $user = Auth::guard('sanctum')->user();

        if (! $user) {
            throw new \Exception('Unauthenticated');
        }

        // Get user's owned items (UserItem records with entity_id references)
        $userItems = UserItem::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        if ($userItems->isEmpty()) {
            return [];
        }

        // Extract entity IDs
        $entityIds = $userItems->pluck('entity_id')->unique()->values()->toArray();

        // Fetch full entity data from Database of Things
        $entities = $this->databaseOfThings->getEntitiesByIds($entityIds);

        // Merge UserItem data with Entity data
        $result = [];
        foreach ($userItems as $userItem) {
            $entityId = $userItem->entity_id;
            $entity = $entities[$entityId] ?? null;

            if ($entity) {
                // Combine UserItem fields (prefixed with user_) and Entity fields
                $result[] = [
                    // UserItem fields
                    'user_item_id' => $userItem->id,
                    'user_id' => $userItem->user_id,
                    'user_metadata' => $userItem->metadata,
                    'user_notes' => $userItem->notes,
                    'user_images' => $userItem->images,
                    'user_created_at' => $userItem->created_at,
                    'user_updated_at' => $userItem->updated_at,

                    // Entity fields (from Database of Things)
                    'id' => $entity['id'],
                    'type' => $entity['type'],
                    'name' => $entity['name'],
                    'year' => $entity['year'] ?? null,
                    'country' => $entity['country'] ?? null,
                    'attributes' => $entity['attributes'] ?? null,
                    'image_url' => $entity['image_url'] ?? null,
                    'thumbnail_url' => $entity['thumbnail_url'] ?? null,
                    'representative_image_urls' => $entity['representative_image_urls'] ?? [],
                    'external_ids' => $entity['external_ids'] ?? null,
                    'created_at' => $entity['created_at'] ?? null,
                    'updated_at' => $entity['updated_at'] ?? null,
                ];
            }
        }

        return $result;
    }
}
