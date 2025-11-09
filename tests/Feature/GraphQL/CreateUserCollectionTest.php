<?php

namespace Tests\Feature\GraphQL;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\UserCollection;

class CreateUserCollectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
    }

    public function test_create_user_collection_with_minimal_fields()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson('/graphql', [
            'query' => '
                mutation($name: String!) {
                    createUserCollection(name: $name) {
                        id
                        name
                        user_id
                        parent_collection_id
                    }
                }
            ',
            'variables' => [
                'name' => 'My New Collection',
            ],
        ]);

        $response->assertJson([
            'data' => [
                'createUserCollection' => [
                    'name' => 'My New Collection',
                    'user_id' => $user->id,
                    'parent_collection_id' => null,
                ],
            ],
        ]);

        $this->assertDatabaseHas('user_collections', [
            'name' => 'My New Collection',
            'user_id' => $user->id,
        ]);
    }

    public function test_create_user_collection_with_all_fields()
    {
        $user = User::factory()->create();

        $parentCollection = UserCollection::create([
            'user_id' => $user->id,
            'name' => 'Parent Collection',
        ]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/graphql', [
            'query' => '
                mutation($name: String!, $description: String, $parentId: ID, $linkedDbotCollectionId: ID) {
                    createUserCollection(
                        name: $name
                        description: $description
                        parent_id: $parentId
                        linked_dbot_collection_id: $linkedDbotCollectionId
                    ) {
                        id
                        name
                        description
                        parent_collection_id
                        linked_dbot_collection_id
                    }
                }
            ',
            'variables' => [
                'name' => 'Full Collection',
                'description' => 'A collection with all fields',
                'parentId' => $parentCollection->id,
                'linkedDbotCollectionId' => '11111111-1111-1111-1111-111111111111',
            ],
        ]);

        $response->assertJson([
            'data' => [
                'createUserCollection' => [
                    'name' => 'Full Collection',
                    'description' => 'A collection with all fields',
                    'parent_collection_id' => $parentCollection->id,
                    'linked_dbot_collection_id' => '11111111-1111-1111-1111-111111111111',
                ],
            ],
        ]);
    }

    public function test_create_user_collection_requires_authentication()
    {
        $response = $this->postJson('/graphql', [
            'query' => '
                mutation {
                    createUserCollection(name: "Test") {
                        id
                    }
                }
            ',
        ]);

        $response->assertJson([
            'errors' => [
                [
                    'message' => 'Unauthenticated.',
                ],
            ],
        ]);
    }

    public function test_create_user_collection_validates_name_required()
    {
        $user = User::factory()->create();

        // Test with empty string - should be rejected by min:1 validation rule
        $response = $this->actingAs($user, 'sanctum')->postJson('/graphql', [
            'query' => '
                mutation {
                    createUserCollection(name: "") {
                        id
                        name
                    }
                }
            ',
        ]);

        // Should have validation error
        $response->assertJsonStructure([
            'errors' => [
                '*' => ['extensions'],
            ],
        ]);
    }

    public function test_create_user_collection_validates_parent_ownership()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // Create collection for user2
        $otherUserCollection = UserCollection::create([
            'user_id' => $user2->id,
            'name' => 'User 2 Collection',
        ]);

        // User1 tries to create collection as child of user2's collection
        $response = $this->actingAs($user1, 'sanctum')->postJson('/graphql', [
            'query' => '
                mutation($parentId: ID!) {
                    createUserCollection(
                        name: "Child Collection"
                        parent_id: $parentId
                    ) {
                        id
                    }
                }
            ',
            'variables' => [
                'parentId' => $otherUserCollection->id,
            ],
        ]);

        // Check for error message
        $response->assertJson([
            'errors' => [
                [
                    'message' => 'Internal server error',
                ],
            ],
        ]);

        // Check the debug message contains our error
        $response->assertJsonFragment([
            'debugMessage' => 'Parent collection not found or access denied',
        ]);
    }
}
