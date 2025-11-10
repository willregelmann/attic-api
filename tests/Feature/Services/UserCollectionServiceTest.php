<?php

namespace Tests\Feature\Services;

use App\Models\User;
use App\Models\UserCollection;
use App\Models\UserItem;
use App\Models\Wishlist;
use App\Services\DatabaseOfThingsService;
use App\Services\UserCollectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserCollectionServiceTest extends TestCase
{
    use RefreshDatabase;

    private UserCollectionService $service;

    private DatabaseOfThingsService $dbotService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');

        // Create real instances of the services
        $this->dbotService = $this->createMock(DatabaseOfThingsService::class);
        $this->service = new UserCollectionService($this->dbotService);
    }

    public function test_get_items_to_add_to_wishlist_returns_all_items_when_target_empty(): void
    {
        // Given: A user and a DBoT collection with 5 items
        $user = User::factory()->create();
        $dbotCollectionId = '11111111-1111-1111-1111-111111111111';

        // Mock DBoT service to return 5 item IDs
        $dbotItemIds = [
            '22222222-2222-2222-2222-222222222221',
            '22222222-2222-2222-2222-222222222222',
            '22222222-2222-2222-2222-222222222223',
            '22222222-2222-2222-2222-222222222224',
            '22222222-2222-2222-2222-222222222225',
        ];

        $this->dbotService
            ->expects($this->once())
            ->method('getCollectionItems')
            ->with($dbotCollectionId)
            ->willReturn([
                'items' => array_map(fn ($id) => [
                    'entity' => ['id' => $id, 'name' => "Item {$id}"],
                ], $dbotItemIds),
            ]);

        // And: An empty target collection
        $targetCollection = UserCollection::create([
            'user_id' => $user->id,
            'name' => 'My Target Collection',
        ]);

        // When: Getting items to add
        $result = $this->service->getItemsToAddToWishlist(
            $user->id,
            $dbotCollectionId,
            $targetCollection->id
        );

        // Then: All 5 items should be returned
        $this->assertCount(5, $result['items_to_add']);
        $this->assertEquals(0, $result['already_owned_count']);
        $this->assertEquals(0, $result['already_wishlisted_count']);
    }

    public function test_get_items_to_add_to_wishlist_filters_owned_items_in_target(): void
    {
        // Given: A user and a DBoT collection with 5 items
        $user = User::factory()->create();
        $dbotCollectionId = '11111111-1111-1111-1111-111111111111';

        $dbotItemIds = [
            '22222222-2222-2222-2222-222222222221',
            '22222222-2222-2222-2222-222222222222',
            '22222222-2222-2222-2222-222222222223',
            '22222222-2222-2222-2222-222222222224',
            '22222222-2222-2222-2222-222222222225',
        ];

        $this->dbotService
            ->expects($this->once())
            ->method('getCollectionItems')
            ->with($dbotCollectionId)
            ->willReturn([
                'items' => array_map(fn ($id) => [
                    'entity' => ['id' => $id, 'name' => "Item {$id}"],
                ], $dbotItemIds),
            ]);

        $targetCollection = UserCollection::create([
            'user_id' => $user->id,
            'name' => 'My Target Collection',
        ]);

        // And: User owns 2 of those items in target collection
        UserItem::create([
            'user_id' => $user->id,
            'entity_id' => $dbotItemIds[0],
            'parent_collection_id' => $targetCollection->id,
        ]);
        UserItem::create([
            'user_id' => $user->id,
            'entity_id' => $dbotItemIds[1],
            'parent_collection_id' => $targetCollection->id,
        ]);

        // When: Getting items to add
        $result = $this->service->getItemsToAddToWishlist(
            $user->id,
            $dbotCollectionId,
            $targetCollection->id
        );

        // Then: Only 3 items should be returned
        $this->assertCount(3, $result['items_to_add']);
        $this->assertEquals(2, $result['already_owned_count']);
        $this->assertEquals(0, $result['already_wishlisted_count']);

        // Verify correct items are returned
        $returnedIds = array_map(fn ($item) => $item['entity']['id'], $result['items_to_add']);
        $this->assertContains($dbotItemIds[2], $returnedIds);
        $this->assertContains($dbotItemIds[3], $returnedIds);
        $this->assertContains($dbotItemIds[4], $returnedIds);
    }

    public function test_get_items_to_add_to_wishlist_filters_wishlisted_items_in_target(): void
    {
        // Given: A user and a DBoT collection with 5 items
        $user = User::factory()->create();
        $dbotCollectionId = '11111111-1111-1111-1111-111111111111';

        $dbotItemIds = [
            '22222222-2222-2222-2222-222222222221',
            '22222222-2222-2222-2222-222222222222',
            '22222222-2222-2222-2222-222222222223',
            '22222222-2222-2222-2222-222222222224',
            '22222222-2222-2222-2222-222222222225',
        ];

        $this->dbotService
            ->expects($this->once())
            ->method('getCollectionItems')
            ->with($dbotCollectionId)
            ->willReturn([
                'items' => array_map(fn ($id) => [
                    'entity' => ['id' => $id, 'name' => "Item {$id}"],
                ], $dbotItemIds),
            ]);

        $targetCollection = UserCollection::create([
            'user_id' => $user->id,
            'name' => 'My Target Collection',
        ]);

        // And: User has wishlisted 2 of those items in target collection
        Wishlist::create([
            'user_id' => $user->id,
            'entity_id' => $dbotItemIds[0],
            'parent_collection_id' => $targetCollection->id,
        ]);
        Wishlist::create([
            'user_id' => $user->id,
            'entity_id' => $dbotItemIds[1],
            'parent_collection_id' => $targetCollection->id,
        ]);

        // When: Getting items to add
        $result = $this->service->getItemsToAddToWishlist(
            $user->id,
            $dbotCollectionId,
            $targetCollection->id
        );

        // Then: Only 3 items should be returned
        $this->assertCount(3, $result['items_to_add']);
        $this->assertEquals(0, $result['already_owned_count']);
        $this->assertEquals(2, $result['already_wishlisted_count']);

        // Verify correct items are returned
        $returnedIds = array_map(fn ($item) => $item['entity']['id'], $result['items_to_add']);
        $this->assertContains($dbotItemIds[2], $returnedIds);
        $this->assertContains($dbotItemIds[3], $returnedIds);
        $this->assertContains($dbotItemIds[4], $returnedIds);
    }

    public function test_get_items_to_add_to_wishlist_does_not_filter_items_in_other_collections(): void
    {
        // Given: A user and a DBoT collection with 5 items
        $user = User::factory()->create();
        $dbotCollectionId = '11111111-1111-1111-1111-111111111111';

        $dbotItemIds = [
            '22222222-2222-2222-2222-222222222221',
            '22222222-2222-2222-2222-222222222222',
            '22222222-2222-2222-2222-222222222223',
            '22222222-2222-2222-2222-222222222224',
            '22222222-2222-2222-2222-222222222225',
        ];

        $this->dbotService
            ->expects($this->once())
            ->method('getCollectionItems')
            ->with($dbotCollectionId)
            ->willReturn([
                'items' => array_map(fn ($id) => [
                    'entity' => ['id' => $id, 'name' => "Item {$id}"],
                ], $dbotItemIds),
            ]);

        $targetCollection = UserCollection::create([
            'user_id' => $user->id,
            'name' => 'My Target Collection',
        ]);

        // Create a different collection
        $otherCollection = UserCollection::create([
            'user_id' => $user->id,
            'name' => 'My Other Collection',
        ]);

        // And: User owns 2 of those items in DIFFERENT collection
        UserItem::create([
            'user_id' => $user->id,
            'entity_id' => $dbotItemIds[0],
            'parent_collection_id' => $otherCollection->id,
        ]);
        UserItem::create([
            'user_id' => $user->id,
            'entity_id' => $dbotItemIds[1],
            'parent_collection_id' => $otherCollection->id,
        ]);

        // When: Getting items to add to target collection
        $result = $this->service->getItemsToAddToWishlist(
            $user->id,
            $dbotCollectionId,
            $targetCollection->id
        );

        // Then: All 5 items should be returned (not filtered)
        $this->assertCount(5, $result['items_to_add']);
        $this->assertEquals(0, $result['already_owned_count']);
        $this->assertEquals(0, $result['already_wishlisted_count']);
    }

    public function test_create_tracked_collection_creates_collection_with_correct_fields(): void
    {
        // Given: A user and a valid DBoT collection ID
        $user = User::factory()->create();
        $dbotCollectionId = '11111111-1111-1111-1111-111111111111';
        $collectionName = 'Pokemon Cards';

        // Mock DBoT service to return a valid collection
        $this->dbotService
            ->expects($this->once())
            ->method('getCollection')
            ->with($dbotCollectionId)
            ->willReturn([
                'id' => $dbotCollectionId,
                'name' => 'Pokemon TCG',
                'description' => 'Pokemon Trading Card Game',
            ]);

        // When: Creating a tracked collection
        $result = $this->service->createTrackedCollection(
            $user->id,
            $dbotCollectionId,
            $collectionName
        );

        // Then: Collection should be created with correct fields
        $this->assertInstanceOf(UserCollection::class, $result);
        $this->assertEquals($user->id, $result->user_id);
        $this->assertEquals($collectionName, $result->name);
        $this->assertEquals($dbotCollectionId, $result->linked_dbot_collection_id);
        $this->assertNull($result->parent_collection_id);

        // Verify it's persisted in database
        $this->assertDatabaseHas('user_collections', [
            'id' => $result->id,
            'user_id' => $user->id,
            'name' => $collectionName,
            'linked_dbot_collection_id' => $dbotCollectionId,
            'parent_collection_id' => null,
        ]);
    }

    public function test_create_tracked_collection_sets_parent_when_provided(): void
    {
        // Given: A user and a parent collection
        $user = User::factory()->create();
        $parentCollection = UserCollection::create([
            'user_id' => $user->id,
            'name' => 'Trading Cards',
        ]);

        $dbotCollectionId = '11111111-1111-1111-1111-111111111111';
        $collectionName = 'Pokemon Cards';

        // Mock DBoT service to return a valid collection
        $this->dbotService
            ->expects($this->once())
            ->method('getCollection')
            ->with($dbotCollectionId)
            ->willReturn([
                'id' => $dbotCollectionId,
                'name' => 'Pokemon TCG',
            ]);

        // When: Creating tracked collection with parent
        $result = $this->service->createTrackedCollection(
            $user->id,
            $dbotCollectionId,
            $collectionName,
            $parentCollection->id
        );

        // Then: parent_collection_id should be set
        $this->assertEquals($parentCollection->id, $result->parent_collection_id);
        $this->assertDatabaseHas('user_collections', [
            'id' => $result->id,
            'parent_collection_id' => $parentCollection->id,
        ]);
    }

    public function test_create_tracked_collection_allows_null_parent(): void
    {
        // Given: A user
        $user = User::factory()->create();
        $dbotCollectionId = '11111111-1111-1111-1111-111111111111';
        $collectionName = 'Pokemon Cards';

        // Mock DBoT service to return a valid collection
        $this->dbotService
            ->expects($this->once())
            ->method('getCollection')
            ->with($dbotCollectionId)
            ->willReturn([
                'id' => $dbotCollectionId,
                'name' => 'Pokemon TCG',
            ]);

        // When: Creating tracked collection without parent (null)
        $result = $this->service->createTrackedCollection(
            $user->id,
            $dbotCollectionId,
            $collectionName,
            null
        );

        // Then: parent_collection_id should be null (root level)
        $this->assertNull($result->parent_collection_id);
        $this->assertDatabaseHas('user_collections', [
            'id' => $result->id,
            'parent_collection_id' => null,
        ]);
    }

    public function test_create_tracked_collection_throws_exception_for_invalid_dbot_collection(): void
    {
        // Given: An invalid DBoT collection ID that doesn't exist
        $user = User::factory()->create();
        $invalidDbotCollectionId = '99999999-9999-9999-9999-999999999999';
        $collectionName = 'Invalid Collection';

        // Mock DBoT service to throw exception (collection not found)
        $this->dbotService
            ->expects($this->once())
            ->method('getCollection')
            ->with($invalidDbotCollectionId)
            ->willThrowException(new \Exception('Collection not found'));

        // When: Creating tracked collection
        // Then: Should throw exception
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('DBoT collection not found');

        $this->service->createTrackedCollection(
            $user->id,
            $invalidDbotCollectionId,
            $collectionName
        );
    }

    public function test_bulk_add_to_wishlist_creates_wishlist_records(): void
    {
        // Given: A user and 5 entity IDs
        $user = User::factory()->create();
        $entityIds = [
            '11111111-1111-1111-1111-111111111111',
            '22222222-2222-2222-2222-222222222222',
            '33333333-3333-3333-3333-333333333333',
            '44444444-4444-4444-4444-444444444444',
            '55555555-5555-5555-5555-555555555555',
        ];

        // When: Bulk adding to wishlist
        $result = $this->service->bulkAddToWishlist($user->id, $entityIds);

        // Then: 5 wishlist records should be created
        $this->assertEquals(5, Wishlist::where('user_id', $user->id)->count());

        // And: All should have correct user_id and entity_id
        foreach ($entityIds as $entityId) {
            $this->assertDatabaseHas('wishlists', [
                'user_id' => $user->id,
                'entity_id' => $entityId,
            ]);
        }

        // And: Result should show 5 added, 0 skipped
        $this->assertEquals(5, $result['items_added']);
        $this->assertEquals(0, $result['items_skipped']);
    }

    public function test_bulk_add_to_wishlist_sets_parent_collection_correctly(): void
    {
        // Given: A user, entity IDs, and a parent collection
        $user = User::factory()->create();
        $parentCollection = UserCollection::create([
            'user_id' => $user->id,
            'name' => 'Pokemon Cards',
        ]);

        $entityIds = [
            '11111111-1111-1111-1111-111111111111',
            '22222222-2222-2222-2222-222222222222',
            '33333333-3333-3333-3333-333333333333',
        ];

        // When: Bulk adding with parent_collection_id
        $result = $this->service->bulkAddToWishlist($user->id, $entityIds, $parentCollection->id);

        // Then: All created wishlist records should have parent_collection_id set
        foreach ($entityIds as $entityId) {
            $this->assertDatabaseHas('wishlists', [
                'user_id' => $user->id,
                'entity_id' => $entityId,
                'parent_collection_id' => $parentCollection->id,
            ]);
        }

        $this->assertEquals(3, $result['items_added']);
    }

    public function test_bulk_add_to_wishlist_allows_null_parent(): void
    {
        // Given: A user and entity IDs
        $user = User::factory()->create();
        $entityIds = [
            '11111111-1111-1111-1111-111111111111',
            '22222222-2222-2222-2222-222222222222',
        ];

        // When: Bulk adding without parent (null)
        $result = $this->service->bulkAddToWishlist($user->id, $entityIds, null);

        // Then: Wishlist records should be created with parent_collection_id = null
        foreach ($entityIds as $entityId) {
            $this->assertDatabaseHas('wishlists', [
                'user_id' => $user->id,
                'entity_id' => $entityId,
                'parent_collection_id' => null,
            ]);
        }

        $this->assertEquals(2, $result['items_added']);
    }

    public function test_bulk_add_to_wishlist_skips_existing_wishlists(): void
    {
        // Given: A user with 2 items already in wishlist
        $user = User::factory()->create();
        $parentCollection = UserCollection::create([
            'user_id' => $user->id,
            'name' => 'Pokemon Cards',
        ]);

        $entityIds = [
            '11111111-1111-1111-1111-111111111111',
            '22222222-2222-2222-2222-222222222222',
            '33333333-3333-3333-3333-333333333333',
            '44444444-4444-4444-4444-444444444444',
            '55555555-5555-5555-5555-555555555555',
        ];

        // Create 2 existing wishlist records
        Wishlist::create([
            'user_id' => $user->id,
            'entity_id' => $entityIds[0],
            'parent_collection_id' => $parentCollection->id,
        ]);
        Wishlist::create([
            'user_id' => $user->id,
            'entity_id' => $entityIds[1],
            'parent_collection_id' => $parentCollection->id,
        ]);

        // And: 5 entity IDs (including the 2 existing)
        // When: Bulk adding all 5
        $result = $this->service->bulkAddToWishlist($user->id, $entityIds, $parentCollection->id);

        // Then: Only 3 new wishlist records should be created
        $this->assertEquals(5, Wishlist::where('user_id', $user->id)->count());

        // And: Should return correct counts (added: 3, skipped: 2)
        $this->assertEquals(3, $result['items_added']);
        $this->assertEquals(2, $result['items_skipped']);
    }

    public function test_bulk_add_to_wishlist_returns_correct_counts(): void
    {
        // Given: Various scenarios
        $user = User::factory()->create();

        // Scenario 1: All new items
        $entityIds1 = [
            '11111111-1111-1111-1111-111111111111',
            '22222222-2222-2222-2222-222222222222',
        ];
        $result1 = $this->service->bulkAddToWishlist($user->id, $entityIds1);
        $this->assertEquals(2, $result1['items_added']);
        $this->assertEquals(0, $result1['items_skipped']);

        // Scenario 2: All existing items
        $result2 = $this->service->bulkAddToWishlist($user->id, $entityIds1);
        $this->assertEquals(0, $result2['items_added']);
        $this->assertEquals(2, $result2['items_skipped']);

        // Scenario 3: Mix of new and existing
        $entityIds3 = [
            '11111111-1111-1111-1111-111111111111', // existing
            '33333333-3333-3333-3333-333333333333', // new
            '44444444-4444-4444-4444-444444444444', // new
        ];
        $result3 = $this->service->bulkAddToWishlist($user->id, $entityIds3);
        $this->assertEquals(2, $result3['items_added']);
        $this->assertEquals(1, $result3['items_skipped']);

        // When: Bulk adding
        // Then: Return array should include:
        //   - items_added: count of new records created
        //   - items_skipped: count of duplicates skipped
        $this->assertArrayHasKey('items_added', $result1);
        $this->assertArrayHasKey('items_skipped', $result1);
    }
}
