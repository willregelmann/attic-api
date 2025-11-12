<?php

namespace App\GraphQL\Mutations;

use App\Models\UserCollection;
use GraphQL\Error\UserError;
use Illuminate\Support\Facades\Storage;

class CreateUserCollection
{
    public function __invoke($rootValue, array $args)
    {
        $user = auth()->user();

        // Validate parent ownership if parent_id provided
        if (isset($args['parent_id'])) {
            $parent = UserCollection::where('id', $args['parent_id'])
                ->where('user_id', $user->id)
                ->first();

            if (!$parent) {
                throw new UserError('Parent collection not found or access denied');
            }
        }

        // Handle custom_image upload if provided
        $customImage = null;
        if (isset($args['custom_image'])) {
            // Store uploaded file (Laravel handles UploadedFile)
            $path = $args['custom_image']->store('collection-images', 'public');
            $customImage = Storage::disk('public')->url($path);
        }

        // Determine collection type based on whether it's linked to DBoT
        $type = isset($args['linked_dbot_collection_id']) ? 'linked' : 'custom';

        // Create collection
        $collection = UserCollection::create([
            'user_id' => $user->id,
            'name' => $args['name'],
            'type' => $type,
            'description' => $args['description'] ?? null,
            'parent_collection_id' => $args['parent_id'] ?? null,
            'linked_dbot_collection_id' => $args['linked_dbot_collection_id'] ?? null,
            'custom_image' => $customImage,
        ]);

        return $collection;
    }
}
