<?php

namespace Tests\Feature\GraphQL;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\UserCollection;

class MoveUserCollectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_move_collection_to_different_parent()
    {
        $user = User::factory()->create();

        $parent1 = UserCollection::create([
            'user_id' => $user->id,
            'name' => 'Parent 1',
        ]);

        $parent2 = UserCollection::create([
            'user_id' => $user->id,
            'name' => 'Parent 2',
        ]);

        $child = UserCollection::create([
            'user_id' => $user->id,
            'name' => 'Child Collection',
            'parent_collection_id' => $parent1->id,
        ]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/graphql', [
            'query' => '
                mutation($id: ID!, $newParentId: ID!) {
                    moveUserCollection(
                        id: $id
                        new_parent_id: $newParentId
                    ) {
                        id
                        name
                        parent_collection_id
                    }
                }
            ',
            'variables' => [
                'id' => $child->id,
                'newParentId' => $parent2->id,
            ],
        ]);

        $response->assertJson([
            'data' => [
                'moveUserCollection' => [
                    'id' => $child->id,
                    'name' => 'Child Collection',
                    'parent_collection_id' => $parent2->id,
                ],
            ],
        ]);

        $this->assertDatabaseHas('user_collections', [
            'id' => $child->id,
            'parent_collection_id' => $parent2->id,
        ]);
    }

    public function test_move_collection_to_root()
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

        $response = $this->actingAs($user, 'sanctum')->postJson('/graphql', [
            'query' => '
                mutation($id: ID!) {
                    moveUserCollection(id: $id) {
                        id
                        parent_collection_id
                    }
                }
            ',
            'variables' => [
                'id' => $child->id,
            ],
        ]);

        $response->assertJson([
            'data' => [
                'moveUserCollection' => [
                    'parent_collection_id' => null,
                ],
            ],
        ]);
    }

    public function test_move_collection_prevents_circular_reference()
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

        // Try to move parent into child (circular reference)
        $response = $this->actingAs($user, 'sanctum')->postJson('/graphql', [
            'query' => '
                mutation($id: ID!, $newParentId: ID!) {
                    moveUserCollection(
                        id: $id
                        new_parent_id: $newParentId
                    ) {
                        id
                    }
                }
            ',
            'variables' => [
                'id' => $parent->id,
                'newParentId' => $child->id,
            ],
        ]);

        $response->assertJsonStructure(['errors']);
    }

    public function test_move_collection_validates_ownership()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $user1Collection = UserCollection::create([
            'user_id' => $user1->id,
            'name' => 'User 1 Collection',
        ]);

        $user2Collection = UserCollection::create([
            'user_id' => $user2->id,
            'name' => 'User 2 Collection',
        ]);

        // User1 tries to move User2's collection
        $response = $this->actingAs($user1, 'sanctum')->postJson('/graphql', [
            'query' => '
                mutation($id: ID!) {
                    moveUserCollection(id: $id) {
                        id
                    }
                }
            ',
            'variables' => [
                'id' => $user2Collection->id,
            ],
        ]);

        $response->assertJsonStructure(['errors']);
    }

    public function test_move_collection_validates_new_parent_ownership()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $user1Collection = UserCollection::create([
            'user_id' => $user1->id,
            'name' => 'User 1 Collection',
        ]);

        $user2Collection = UserCollection::create([
            'user_id' => $user2->id,
            'name' => 'User 2 Collection',
        ]);

        // User1 tries to move their collection into User2's collection
        $response = $this->actingAs($user1, 'sanctum')->postJson('/graphql', [
            'query' => '
                mutation($id: ID!, $newParentId: ID!) {
                    moveUserCollection(
                        id: $id
                        new_parent_id: $newParentId
                    ) {
                        id
                    }
                }
            ',
            'variables' => [
                'id' => $user1Collection->id,
                'newParentId' => $user2Collection->id,
            ],
        ]);

        $response->assertJsonStructure(['errors']);
    }
}
