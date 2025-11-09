<?php

namespace Tests\Feature\GraphQL;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\UserCollection;
use App\Models\UserItem;
use App\Models\Wishlist;

class MoveUserItemTest extends TestCase
{
    use RefreshDatabase;

    public function test_move_user_item_to_collection()
    {
        $user = User::factory()->create();

        $collection = UserCollection::create([
            'user_id' => $user->id,
            'name' => 'Test Collection',
        ]);

        $item = UserItem::create([
            'user_id' => $user->id,
            'entity_id' => '11111111-1111-1111-1111-111111111111',
            'parent_collection_id' => null,
        ]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/graphql', [
            'query' => '
                mutation($itemId: ID!, $collectionId: ID!) {
                    moveUserItem(
                        item_id: $itemId
                        new_parent_collection_id: $collectionId
                    ) {
                        id
                        parent_collection_id
                    }
                }
            ',
            'variables' => [
                'itemId' => $item->id,
                'collectionId' => $collection->id,
            ],
        ]);

        $response->assertJson([
            'data' => [
                'moveUserItem' => [
                    'id' => $item->id,
                    'parent_collection_id' => $collection->id,
                ],
            ],
        ]);

        $this->assertDatabaseHas('user_items', [
            'id' => $item->id,
            'parent_collection_id' => $collection->id,
        ]);
    }

    public function test_move_user_item_to_root()
    {
        $user = User::factory()->create();

        $collection = UserCollection::create([
            'user_id' => $user->id,
            'name' => 'Test Collection',
        ]);

        $item = UserItem::create([
            'user_id' => $user->id,
            'entity_id' => '11111111-1111-1111-1111-111111111111',
            'parent_collection_id' => $collection->id,
        ]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/graphql', [
            'query' => '
                mutation($itemId: ID!) {
                    moveUserItem(item_id: $itemId) {
                        id
                        parent_collection_id
                    }
                }
            ',
            'variables' => [
                'itemId' => $item->id,
            ],
        ]);

        $response->assertJson([
            'data' => [
                'moveUserItem' => [
                    'parent_collection_id' => null,
                ],
            ],
        ]);
    }

    public function test_move_user_item_validates_ownership()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $item = UserItem::create([
            'user_id' => $user2->id,
            'entity_id' => '11111111-1111-1111-1111-111111111111',
        ]);

        $response = $this->actingAs($user1, 'sanctum')->postJson('/graphql', [
            'query' => '
                mutation($itemId: ID!) {
                    moveUserItem(item_id: $itemId) {
                        id
                    }
                }
            ',
            'variables' => [
                'itemId' => $item->id,
            ],
        ]);

        $response->assertJsonStructure(['errors']);
    }

    public function test_move_user_item_validates_collection_ownership()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $item = UserItem::create([
            'user_id' => $user1->id,
            'entity_id' => '11111111-1111-1111-1111-111111111111',
        ]);

        $collection = UserCollection::create([
            'user_id' => $user2->id,
            'name' => 'User 2 Collection',
        ]);

        $response = $this->actingAs($user1, 'sanctum')->postJson('/graphql', [
            'query' => '
                mutation($itemId: ID!, $collectionId: ID!) {
                    moveUserItem(
                        item_id: $itemId
                        new_parent_collection_id: $collectionId
                    ) {
                        id
                    }
                }
            ',
            'variables' => [
                'itemId' => $item->id,
                'collectionId' => $collection->id,
            ],
        ]);

        $response->assertJsonStructure(['errors']);
    }

    public function test_move_wishlist_item_to_collection()
    {
        $user = User::factory()->create();

        $collection = UserCollection::create([
            'user_id' => $user->id,
            'name' => 'Test Collection',
        ]);

        $wishlist = Wishlist::create([
            'user_id' => $user->id,
            'entity_id' => '11111111-1111-1111-1111-111111111111',
            'parent_collection_id' => null,
        ]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/graphql', [
            'query' => '
                mutation($wishlistId: ID!, $collectionId: ID!) {
                    moveWishlistItem(
                        wishlist_id: $wishlistId
                        new_parent_collection_id: $collectionId
                    ) {
                        id
                        parent_collection_id
                    }
                }
            ',
            'variables' => [
                'wishlistId' => $wishlist->id,
                'collectionId' => $collection->id,
            ],
        ]);

        $response->assertJson([
            'data' => [
                'moveWishlistItem' => [
                    'id' => $wishlist->id,
                    'parent_collection_id' => $collection->id,
                ],
            ],
        ]);

        $this->assertDatabaseHas('wishlists', [
            'id' => $wishlist->id,
            'parent_collection_id' => $collection->id,
        ]);
    }
}
