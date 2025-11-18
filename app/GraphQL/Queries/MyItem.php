<?php

namespace App\GraphQL\Queries;

use App\Models\UserItem;
use App\Services\DatabaseOfThingsService;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Support\Facades\Auth;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class MyItem
{
    protected $databaseOfThings;

    public function __construct(DatabaseOfThingsService $databaseOfThings)
    {
        $this->databaseOfThings = $databaseOfThings;
    }

    /**
     * Get a single owned entity for the authenticated user by user_item_id
     * Returns UserItemWithEntity combining user data with entity data
     */
    public function __invoke($rootValue, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $user = Auth::guard('sanctum')->user();

        if (! $user) {
            throw new \Exception('Unauthenticated');
        }

        $userItemId = $args['user_item_id'];

        // Find the user item and verify ownership
        $userItem = UserItem::where('id', $userItemId)
            ->where('user_id', $user->id)
            ->first();

        if (! $userItem) {
            return null; // Item not found or doesn't belong to user
        }

        // Check if this is a custom item (no entity_id)
        if ($userItem->isCustomItem()) {
            // Return custom item data directly from user_items table
            return [
                // UserItem fields
                'user_item_id' => $userItem->id,
                'user_id' => $userItem->user_id,
                'parent_collection_id' => $userItem->parent_collection_id,
                'variant_id' => $userItem->variant_id,
                'user_metadata' => $userItem->metadata,
                'user_notes' => $userItem->notes,
                'user_images' => $userItem->images,
                'user_created_at' => $userItem->created_at,
                'user_updated_at' => $userItem->updated_at,

                // Entity fields (mostly null for custom items)
                'id' => null,
                'type' => 'custom',
                'name' => $userItem->name,
                'year' => null,
                'country' => null,
                'attributes' => null,
                'image_url' => $userItem->images[0]['original'] ?? null,
                'thumbnail_url' => $userItem->images[0]['thumbnail'] ?? null,
                'representative_image_urls' => [],
                'external_ids' => null,
                'entity_variants' => [],
                'created_at' => null,
                'updated_at' => null,
            ];
        }

        // Fetch entity data from Database of Things
        $entity = $this->databaseOfThings->getEntity($userItem->entity_id);

        if (! $entity) {
            return null; // Entity not found in Database of Things
        }

        // Combine UserItem fields (prefixed with user_) and Entity fields
        return [
            // UserItem fields
            'user_item_id' => $userItem->id,
            'user_id' => $userItem->user_id,
            'parent_collection_id' => $userItem->parent_collection_id,
            'variant_id' => $userItem->variant_id,
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
            'entity_variants' => $entity['entity_variants'] ?? null,
            'created_at' => $entity['created_at'] ?? null,
            'updated_at' => $entity['updated_at'] ?? null,
        ];
    }
}
