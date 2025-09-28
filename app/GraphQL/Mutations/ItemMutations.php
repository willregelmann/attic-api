<?php

namespace App\GraphQL\Mutations;

use App\Models\Item;
use Illuminate\Support\Str;

class ItemMutations
{
    /**
     * Create a new item (collection or collectible)
     */
    public function createItem($_, array $args)
    {
        $item = Item::create([
            'id' => Str::ulid(),
            'name' => $args['name'],
            'type' => $args['type'],
            'metadata' => $args['metadata'] ?? []
        ]);

        return $item;
    }

    /**
     * Update an existing item
     */
    public function updateItem($_, array $args)
    {
        $item = Item::findOrFail($args['id']);

        if (isset($args['name'])) {
            $item->name = $args['name'];
        }

        if (isset($args['metadata'])) {
            $item->metadata = array_merge($item->metadata ?? [], $args['metadata']);
        }

        $item->save();

        return $item;
    }

    /**
     * Delete an item
     */
    public function deleteItem($_, array $args)
    {
        $item = Item::findOrFail($args['id']);
        $item->delete();

        return "Item deleted successfully";
    }
}