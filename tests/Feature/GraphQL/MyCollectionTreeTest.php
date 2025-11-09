<?php

namespace Tests\Feature\GraphQL;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\UserCollection;
use App\Models\UserItem;
use App\Models\Wishlist;

class MyCollectionTreeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
    }

    public function test_my_collection_tree_returns_root_level()
    {
        $user = User::factory()->create();

        // Create root-level collection
        $collection = UserCollection::create([
            'user_id' => $user->id,
            'name' => 'Root Collection',
            'parent_collection_id' => null,
        ]);

        // Create root-level item
        $item = UserItem::create([
            'user_id' => $user->id,
            'entity_id' => '11111111-1111-1111-1111-111111111111',
            'parent_collection_id' => null,
        ]);

        // Create root-level wishlist
        $wishlist = Wishlist::create([
            'user_id' => $user->id,
            'entity_id' => '22222222-2222-2222-2222-222222222222',
            'parent_collection_id' => null,
        ]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/graphql', [
            'query' => '
                query {
                    myCollectionTree {
                        collections {
                            id
                            name
                        }
                        items {
                            id
                        }
                        wishlists {
                            id
                        }
                        current_collection {
                            id
                        }
                    }
                }
            ',
        ]);

        $response->assertJsonStructure([
            'data' => [
                'myCollectionTree' => [
                    'collections',
                    'items',
                    'wishlists',
                    'current_collection',
                ],
            ],
        ]);

        $data = $response->json('data.myCollectionTree');
        $this->assertCount(1, $data['collections']);
        $this->assertEquals('Root Collection', $data['collections'][0]['name']);
        $this->assertCount(1, $data['items']);
        $this->assertCount(1, $data['wishlists']);
        $this->assertNull($data['current_collection']);
    }

    public function test_my_collection_tree_returns_specific_level()
    {
        $user = User::factory()->create();

        // Create parent collection
        $parent = UserCollection::create([
            'user_id' => $user->id,
            'name' => 'Parent Collection',
        ]);

        // Create child collection
        $child = UserCollection::create([
            'user_id' => $user->id,
            'name' => 'Child Collection',
            'parent_collection_id' => $parent->id,
        ]);

        // Create item in parent
        $item = UserItem::create([
            'user_id' => $user->id,
            'entity_id' => '11111111-1111-1111-1111-111111111111',
            'parent_collection_id' => $parent->id,
        ]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/graphql', [
            'query' => '
                query($parentId: ID!) {
                    myCollectionTree(parent_id: $parentId) {
                        collections {
                            id
                            name
                        }
                        items {
                            id
                        }
                        current_collection {
                            id
                            name
                        }
                    }
                }
            ',
            'variables' => [
                'parentId' => $parent->id,
            ],
        ]);

        $data = $response->json('data.myCollectionTree');
        $this->assertCount(1, $data['collections']);
        $this->assertEquals('Child Collection', $data['collections'][0]['name']);
        $this->assertCount(1, $data['items']);
        $this->assertEquals($parent->id, $data['current_collection']['id']);
        $this->assertEquals('Parent Collection', $data['current_collection']['name']);
    }

    public function test_my_collection_tree_requires_authentication()
    {
        $response = $this->postJson('/graphql', [
            'query' => '
                query {
                    myCollectionTree {
                        collections {
                            id
                        }
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

    public function test_my_collection_tree_filters_by_user()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // Create collection for user2
        UserCollection::create([
            'user_id' => $user2->id,
            'name' => 'User 2 Collection',
        ]);

        // User1 shouldn't see User2's collections
        $response = $this->actingAs($user1, 'sanctum')->postJson('/graphql', [
            'query' => '
                query {
                    myCollectionTree {
                        collections {
                            id
                            name
                        }
                    }
                }
            ',
        ]);

        $data = $response->json('data.myCollectionTree');
        $this->assertCount(0, $data['collections']);
    }
}
