<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserCollectionFavorite;
use App\Models\UserItem;
use App\Services\DatabaseOfThingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class MyFavoriteCollectionsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_favorite_collections_uses_single_query_for_owned_items_count(): void
    {
        // Create user with favorite collections
        $user = User::factory()->create();

        // Create 3 favorite collections
        $collectionIds = [
            '11111111-1111-1111-1111-111111111111',
            '22222222-2222-2222-2222-222222222222',
            '33333333-3333-3333-3333-333333333333',
        ];

        foreach ($collectionIds as $collectionId) {
            UserCollectionFavorite::create([
                'user_id' => $user->id,
                'collection_id' => $collectionId,
            ]);
        }

        // Create items for each collection (3 items per collection)
        $itemsPerCollection = [];
        foreach ($collectionIds as $index => $collectionId) {
            $items = [];
            for ($i = 1; $i <= 3; $i++) {
                $itemId = sprintf('%d%d%d%d%d%d%d%d-%d%d%d%d-%d%d%d%d-%d%d%d%d-%d%d%d%d%d%d%d%d%d%d%d%d',
                    $index, $index, $index, $index, $index, $index, $index, $index,
                    $i, $i, $i, $i,
                    $i, $i, $i, $i,
                    $i, $i, $i, $i,
                    $i, $i, $i, $i, $i, $i, $i, $i, $i, $i, $i, $i
                );
                $items[] = ['id' => $itemId, 'name' => "Item $i", 'type' => 'trading_card'];
            }
            $itemsPerCollection[$collectionId] = $items;
        }

        // Create user items (owns 2 items from first collection, 1 from second, 0 from third)
        UserItem::create([
            'user_id' => $user->id,
            'entity_id' => $itemsPerCollection[$collectionIds[0]][0]['id'],
        ]);
        UserItem::create([
            'user_id' => $user->id,
            'entity_id' => $itemsPerCollection[$collectionIds[0]][1]['id'],
        ]);
        UserItem::create([
            'user_id' => $user->id,
            'entity_id' => $itemsPerCollection[$collectionIds[1]][0]['id'],
        ]);

        // Mock DatabaseOfThingsService
        $mockService = Mockery::mock(DatabaseOfThingsService::class);

        // Mock getEntitiesByIds (called once for all collections)
        $mockService->shouldReceive('getEntitiesByIds')
            ->once()
            ->with($collectionIds)
            ->andReturn([
                $collectionIds[0] => ['id' => $collectionIds[0], 'name' => 'Collection 1', 'type' => 'collection'],
                $collectionIds[1] => ['id' => $collectionIds[1], 'name' => 'Collection 2', 'type' => 'collection'],
                $collectionIds[2] => ['id' => $collectionIds[2], 'name' => 'Collection 3', 'type' => 'collection'],
            ]);

        // Mock getMultipleCollectionItemsInParallel
        $mockService->shouldReceive('getMultipleCollectionItemsInParallel')
            ->once()
            ->with($collectionIds, 1000)
            ->andReturn([
                $collectionIds[0] => [
                    'items' => array_map(fn($item) => ['entity' => $item, 'order' => 0], $itemsPerCollection[$collectionIds[0]]),
                ],
                $collectionIds[1] => [
                    'items' => array_map(fn($item) => ['entity' => $item, 'order' => 0], $itemsPerCollection[$collectionIds[1]]),
                ],
                $collectionIds[2] => [
                    'items' => array_map(fn($item) => ['entity' => $item, 'order' => 0], $itemsPerCollection[$collectionIds[2]]),
                ],
            ]);

        $this->app->instance(DatabaseOfThingsService::class, $mockService);

        // Enable query logging
        DB::enableQueryLog();

        // Act as authenticated user
        $this->actingAs($user, 'sanctum');

        // Execute the GraphQL query
        $response = $this->postJson('/graphql', [
            'query' => '
                query {
                    myFavoriteCollections {
                        collection {
                            id
                            name
                        }
                        stats {
                            totalItems
                            ownedItems
                            completionPercentage
                        }
                    }
                }
            ',
        ]);

        $response->assertOk();

        // Get query log
        $queries = DB::getQueryLog();

        // Filter to only count queries to user_items table for counting owned items
        $userItemCountQueries = array_filter($queries, function($query) {
            return str_contains($query['query'], 'user_items') &&
                   str_contains($query['query'], 'count');
        });

        // Assert: Should be only 1 query for counting owned items across ALL collections
        // NOT 3 queries (one per collection) - this is the N+1 fix
        $this->assertCount(1, $userItemCountQueries,
            'Should use a single query to count owned items for all collections, not N+1 queries'
        );

        // Verify the response data is correct
        $data = $response->json('data.myFavoriteCollections');
        $this->assertCount(3, $data);

        // Collection 1: 2 owned out of 3
        $this->assertEquals(2, $data[0]['stats']['ownedItems']);
        $this->assertEquals(3, $data[0]['stats']['totalItems']);
        $this->assertEquals(66.67, $data[0]['stats']['completionPercentage']);

        // Collection 2: 1 owned out of 3
        $this->assertEquals(1, $data[1]['stats']['ownedItems']);
        $this->assertEquals(3, $data[1]['stats']['totalItems']);
        $this->assertEquals(33.33, $data[1]['stats']['completionPercentage']);

        // Collection 3: 0 owned out of 3
        $this->assertEquals(0, $data[2]['stats']['ownedItems']);
        $this->assertEquals(3, $data[2]['stats']['totalItems']);
        $this->assertEquals(0, $data[2]['stats']['completionPercentage']);
    }
}
