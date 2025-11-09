<?php

namespace Tests\Feature\GraphQL;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\UserCollection;
use App\Models\UserItem;
use App\Models\Wishlist;
use App\Services\DatabaseOfThingsService;
use Mockery;

class MyCollectionTreeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
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

        // Mock DatabaseOfThingsService
        $mockService = Mockery::mock(DatabaseOfThingsService::class);
        $mockService->shouldReceive('getEntitiesByIds')
            ->once()
            ->andReturn([
                '11111111-1111-1111-1111-111111111111' => [
                    'id' => '11111111-1111-1111-1111-111111111111',
                    'name' => 'Test Item',
                    'type' => 'trading_card',
                ],
                '22222222-2222-2222-2222-222222222222' => [
                    'id' => '22222222-2222-2222-2222-222222222222',
                    'name' => 'Test Wishlist',
                    'type' => 'trading_card',
                ],
            ]);

        $this->app->instance(DatabaseOfThingsService::class, $mockService);

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

        // Mock DatabaseOfThingsService
        $mockService = Mockery::mock(DatabaseOfThingsService::class);
        $mockService->shouldReceive('getEntitiesByIds')
            ->once()
            ->andReturn([
                '11111111-1111-1111-1111-111111111111' => [
                    'id' => '11111111-1111-1111-1111-111111111111',
                    'name' => 'Test Item',
                    'type' => 'trading_card',
                ],
            ]);

        $this->app->instance(DatabaseOfThingsService::class, $mockService);

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

        // Mock DatabaseOfThingsService
        $mockService = Mockery::mock(DatabaseOfThingsService::class);
        $mockService->shouldReceive('getEntitiesByIds')
            ->andReturn([]);

        $this->app->instance(DatabaseOfThingsService::class, $mockService);

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

    public function test_my_collection_tree_includes_entity_data()
    {
        $user = User::factory()->create();

        $entityId = '11111111-1111-1111-1111-111111111111';
        $wishlistEntityId = '22222222-2222-2222-2222-222222222222';

        // Create root-level item
        $item = UserItem::create([
            'user_id' => $user->id,
            'entity_id' => $entityId,
            'parent_collection_id' => null,
        ]);

        // Create root-level wishlist
        $wishlist = Wishlist::create([
            'user_id' => $user->id,
            'entity_id' => $wishlistEntityId,
            'parent_collection_id' => null,
        ]);

        // Mock DatabaseOfThingsService
        $mockService = Mockery::mock(DatabaseOfThingsService::class);

        // Mock getEntitiesByIds to return entity data
        $mockService->shouldReceive('getEntitiesByIds')
            ->once()
            ->with([$entityId, $wishlistEntityId])
            ->andReturn([
                $entityId => [
                    'id' => $entityId,
                    'name' => 'Test Item',
                    'type' => 'trading_card',
                    'year' => 2023,
                    'country' => 'USA',
                    'attributes' => ['rarity' => 'rare'],
                    'image_url' => 'https://example.com/image.jpg',
                    'thumbnail_url' => 'https://example.com/thumb.jpg',
                    'representative_image_urls' => [],
                    'external_ids' => null,
                    'created_at' => '2023-01-01T00:00:00Z',
                    'updated_at' => '2023-01-02T00:00:00Z',
                ],
                $wishlistEntityId => [
                    'id' => $wishlistEntityId,
                    'name' => 'Wishlist Item',
                    'type' => 'trading_card',
                    'year' => 2024,
                    'country' => 'Japan',
                    'attributes' => ['rarity' => 'ultra_rare'],
                    'image_url' => 'https://example.com/wishlist.jpg',
                    'thumbnail_url' => 'https://example.com/wishlist_thumb.jpg',
                    'representative_image_urls' => [],
                    'external_ids' => null,
                    'created_at' => '2024-01-01T00:00:00Z',
                    'updated_at' => '2024-01-02T00:00:00Z',
                ],
            ]);

        $this->app->instance(DatabaseOfThingsService::class, $mockService);

        $response = $this->actingAs($user, 'sanctum')->postJson('/graphql', [
            'query' => '
                query {
                    myCollectionTree {
                        items {
                            user_item_id
                            id
                            name
                            type
                            year
                            country
                        }
                        wishlists {
                            wishlist_id
                            id
                            name
                            type
                            year
                            country
                        }
                    }
                }
            ',
        ]);

        $data = $response->json('data.myCollectionTree');

        // Verify items include both user fields and entity fields
        $this->assertCount(1, $data['items']);
        $itemData = $data['items'][0];

        // UserItem fields
        $this->assertEquals($item->id, $itemData['user_item_id']);

        // Entity fields
        $this->assertEquals($entityId, $itemData['id']);
        $this->assertEquals('Test Item', $itemData['name']);
        $this->assertEquals('trading_card', $itemData['type']);
        $this->assertEquals(2023, $itemData['year']);
        $this->assertEquals('USA', $itemData['country']);

        // Verify wishlists include both wishlist fields and entity fields
        $this->assertCount(1, $data['wishlists']);
        $wishlistData = $data['wishlists'][0];

        // Wishlist fields
        $this->assertEquals($wishlist->id, $wishlistData['wishlist_id']);

        // Entity fields
        $this->assertEquals($wishlistEntityId, $wishlistData['id']);
        $this->assertEquals('Wishlist Item', $wishlistData['name']);
        $this->assertEquals('trading_card', $wishlistData['type']);
        $this->assertEquals(2024, $wishlistData['year']);
        $this->assertEquals('Japan', $wishlistData['country']);
    }

    public function test_my_collection_tree_validates_current_collection_ownership()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // Create collection for user2
        $collection = UserCollection::create([
            'user_id' => $user2->id,
            'name' => 'User 2 Collection',
        ]);

        // Mock DatabaseOfThingsService (no entities needed for this test)
        $mockService = Mockery::mock(DatabaseOfThingsService::class);
        $mockService->shouldReceive('getEntitiesByIds')
            ->andReturn([]);

        $this->app->instance(DatabaseOfThingsService::class, $mockService);

        // User1 tries to access User2's collection
        $response = $this->actingAs($user1, 'sanctum')->postJson('/graphql', [
            'query' => '
                query($parentId: ID!) {
                    myCollectionTree(parent_id: $parentId) {
                        current_collection {
                            id
                            name
                        }
                    }
                }
            ',
            'variables' => [
                'parentId' => $collection->id,
            ],
        ]);

        $data = $response->json('data.myCollectionTree');

        // Should not return the collection since user1 doesn't own it
        $this->assertNull($data['current_collection']);
    }
}
