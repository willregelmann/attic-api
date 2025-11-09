<?php

namespace Tests\Feature\GraphQL;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\UserCollection;
use App\Models\UserItem;
use App\Models\Wishlist;

class DeleteUserCollectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_delete_empty_collection()
    {
        $user = User::factory()->create();

        $collection = UserCollection::create([
            'user_id' => $user->id,
            'name' => 'Empty Collection',
        ]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/graphql', [
            'query' => '
                mutation($id: ID!, $deleteContents: Boolean!) {
                    deleteUserCollection(
                        id: $id
                        delete_contents: $deleteContents
                    ) {
                        success
                        message
                    }
                }
            ',
            'variables' => [
                'id' => $collection->id,
                'deleteContents' => false,
            ],
        ]);

        $response->assertJson([
            'data' => [
                'deleteUserCollection' => [
                    'success' => true,
                ],
            ],
        ]);

        $this->assertDatabaseMissing('user_collections', [
            'id' => $collection->id,
        ]);
    }

    public function test_delete_collection_moves_contents_to_parent()
    {
        $user = User::factory()->create();

        $parent = UserCollection::create([
            'user_id' => $user->id,
            'name' => 'Parent',
        ]);

        $child = UserCollection::create([
            'user_id' => $user->id,
            'name' => 'Child',
            'parent_collection_id' => $parent->id,
        ]);

        $grandchild = UserCollection::create([
            'user_id' => $user->id,
            'name' => 'Grandchild',
            'parent_collection_id' => $child->id,
        ]);

        $item = UserItem::create([
            'user_id' => $user->id,
            'entity_id' => '11111111-1111-1111-1111-111111111111',
            'parent_collection_id' => $child->id,
        ]);

        $wishlist = Wishlist::create([
            'user_id' => $user->id,
            'entity_id' => '22222222-2222-2222-2222-222222222222',
            'parent_collection_id' => $child->id,
        ]);

        // Delete child with delete_contents: false
        $response = $this->actingAs($user, 'sanctum')->postJson('/graphql', [
            'query' => '
                mutation($id: ID!, $deleteContents: Boolean!) {
                    deleteUserCollection(
                        id: $id
                        delete_contents: $deleteContents
                    ) {
                        success
                    }
                }
            ',
            'variables' => [
                'id' => $child->id,
                'deleteContents' => false,
            ],
        ]);

        $response->assertJson([
            'data' => [
                'deleteUserCollection' => [
                    'success' => true,
                ],
            ],
        ]);

        // Child collection should be deleted
        $this->assertDatabaseMissing('user_collections', [
            'id' => $child->id,
        ]);

        // Contents should move to parent
        $this->assertDatabaseHas('user_collections', [
            'id' => $grandchild->id,
            'parent_collection_id' => $parent->id,
        ]);

        $this->assertDatabaseHas('user_items', [
            'id' => $item->id,
            'parent_collection_id' => $parent->id,
        ]);

        $this->assertDatabaseHas('wishlists', [
            'id' => $wishlist->id,
            'parent_collection_id' => $parent->id,
        ]);
    }

    public function test_delete_root_collection_moves_contents_to_root()
    {
        $user = User::factory()->create();

        $collection = UserCollection::create([
            'user_id' => $user->id,
            'name' => 'Root Collection',
            'parent_collection_id' => null,
        ]);

        $item = UserItem::create([
            'user_id' => $user->id,
            'entity_id' => '11111111-1111-1111-1111-111111111111',
            'parent_collection_id' => $collection->id,
        ]);

        // Delete root collection with delete_contents: false
        $response = $this->actingAs($user, 'sanctum')->postJson('/graphql', [
            'query' => '
                mutation($id: ID!, $deleteContents: Boolean!) {
                    deleteUserCollection(
                        id: $id
                        delete_contents: $deleteContents
                    ) {
                        success
                    }
                }
            ',
            'variables' => [
                'id' => $collection->id,
                'deleteContents' => false,
            ],
        ]);

        $response->assertJson([
            'data' => [
                'deleteUserCollection' => [
                    'success' => true,
                ],
            ],
        ]);

        // Item should move to root (null parent)
        $this->assertDatabaseHas('user_items', [
            'id' => $item->id,
            'parent_collection_id' => null,
        ]);
    }

    public function test_delete_collection_with_delete_contents_moves_to_root()
    {
        $user = User::factory()->create();

        $parent = UserCollection::create([
            'user_id' => $user->id,
            'name' => 'Parent',
        ]);

        $child = UserCollection::create([
            'user_id' => $user->id,
            'name' => 'Child',
            'parent_collection_id' => $parent->id,
        ]);

        $grandchild = UserCollection::create([
            'user_id' => $user->id,
            'name' => 'Grandchild',
            'parent_collection_id' => $child->id,
        ]);

        $item = UserItem::create([
            'user_id' => $user->id,
            'entity_id' => '11111111-1111-1111-1111-111111111111',
            'parent_collection_id' => $child->id,
        ]);

        // Delete child with delete_contents: true
        $response = $this->actingAs($user, 'sanctum')->postJson('/graphql', [
            'query' => '
                mutation($id: ID!, $deleteContents: Boolean!) {
                    deleteUserCollection(
                        id: $id
                        delete_contents: $deleteContents
                    ) {
                        success
                    }
                }
            ',
            'variables' => [
                'id' => $child->id,
                'deleteContents' => true,
            ],
        ]);

        $response->assertJson([
            'data' => [
                'deleteUserCollection' => [
                    'success' => true,
                ],
            ],
        ]);

        // Child collection should be deleted
        $this->assertDatabaseMissing('user_collections', [
            'id' => $child->id,
        ]);

        // Subcollections should be deleted
        $this->assertDatabaseMissing('user_collections', [
            'id' => $grandchild->id,
        ]);

        // Items move to root (user data preserved)
        $this->assertDatabaseHas('user_items', [
            'id' => $item->id,
            'parent_collection_id' => null,
        ]);
    }

    public function test_delete_collection_validates_ownership()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $user2Collection = UserCollection::create([
            'user_id' => $user2->id,
            'name' => 'User 2 Collection',
        ]);

        // User1 tries to delete User2's collection
        $response = $this->actingAs($user1, 'sanctum')->postJson('/graphql', [
            'query' => '
                mutation($id: ID!, $deleteContents: Boolean!) {
                    deleteUserCollection(
                        id: $id
                        delete_contents: $deleteContents
                    ) {
                        success
                    }
                }
            ',
            'variables' => [
                'id' => $user2Collection->id,
                'deleteContents' => false,
            ],
        ]);

        $response->assertJsonStructure(['errors']);
    }
}
