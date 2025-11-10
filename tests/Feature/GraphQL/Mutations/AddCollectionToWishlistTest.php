<?php

namespace Tests\Feature\GraphQL\Mutations;

use Tests\TestCase;
use App\Models\User;
use App\Models\UserCollection;
use App\Models\Wishlist;
use App\Services\DatabaseOfThingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

class AddCollectionToWishlistTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
    }

    /**
     * Create and mock a DBoT collection with items
     *
     * @param  string  $collectionId  DBoT collection UUID
     * @param  array  $itemIds  Array of item UUIDs
     * @param  string  $collectionName  Name of the DBoT collection
     * @param  int  $getCollectionTimes  Expected number of getCollection calls
     * @return void
     */
    protected function mockDbotCollection(
        string $collectionId,
        array $itemIds,
        string $collectionName = 'Pokemon Cards',
        int $getCollectionTimes = 1
    ): void {
        $mockDbotService = Mockery::mock(DatabaseOfThingsService::class);

        $mockDbotService->shouldReceive('getCollection')
            ->with($collectionId)
            ->times($getCollectionTimes)
            ->andReturn([
                'id' => $collectionId,
                'name' => $collectionName,
                'type' => 'collection',
            ]);

        $items = array_map(fn ($id, $index) => [
            'entity' => ['id' => $id],
            'order' => $index + 1,
        ], $itemIds, array_keys($itemIds));

        $mockDbotService->shouldReceive('getCollectionItems')
            ->with($collectionId)
            ->once()
            ->andReturn([
                'items' => $items,
                'pageInfo' => [
                    'hasNextPage' => false,
                    'endCursor' => null,
                ],
            ]);

        $this->app->instance(DatabaseOfThingsService::class, $mockDbotService);
    }

    /**
     * Execute the addCollectionToWishlist GraphQL mutation
     *
     * @param  User  $user  Authenticated user
     * @param  string  $dbotCollectionId  DBoT collection UUID
     * @param  string  $mode  'TRACK' or 'ADD_TO_EXISTING'
     * @param  string|null  $newCollectionName  Name for new collection (TRACK mode)
     * @param  string|null  $targetCollectionId  Target collection ID
     * @return \Illuminate\Testing\TestResponse
     */
    protected function executeAddCollectionMutation(
        User $user,
        string $dbotCollectionId,
        string $mode,
        ?string $newCollectionName = null,
        ?string $targetCollectionId = null
    ) {
        $variables = [
            'dbotCollectionId' => $dbotCollectionId,
            'mode' => $mode,
        ];

        if ($newCollectionName !== null) {
            $variables['newCollectionName'] = $newCollectionName;
        }

        if ($targetCollectionId !== null) {
            $variables['targetCollectionId'] = $targetCollectionId;
        }

        $query = '
            mutation(
                $dbotCollectionId: ID!,
                $mode: WishlistMode!,
                $newCollectionName: String,
                $targetCollectionId: ID
            ) {
                addCollectionToWishlist(
                    dbot_collection_id: $dbotCollectionId
                    mode: $mode
                    new_collection_name: $newCollectionName
                    target_collection_id: $targetCollectionId
                ) {
                    created_collection {
                        id
                        name
                        linked_dbot_collection_id
                        parent_collection_id
                    }
                    items_added
                    items_already_owned
                    items_skipped
                }
            }
        ';

        return $this->actingAs($user, 'sanctum')->postJson('/graphql', [
            'query' => $query,
            'variables' => $variables,
        ]);
    }

    public function test_track_mode_creates_linked_collection(): void
    {
        // Given: A user and a DBoT collection with items
        $user = User::factory()->create();

        // Use proper UUID format
        $dbotCollectionId = '11111111-1111-1111-1111-111111111111';
        $itemId1 = '22222222-2222-2222-2222-222222222222';
        $itemId2 = '33333333-3333-3333-3333-333333333333';
        $itemId3 = '44444444-4444-4444-4444-444444444444';

        // Mock DatabaseOfThingsService
        $mockDbotService = Mockery::mock(DatabaseOfThingsService::class);
        $mockDbotService->shouldReceive('getCollection')
            ->with($dbotCollectionId)
            ->times(2) // Called twice: once in mutation validation, once in createTrackedCollection
            ->andReturn([
                'id' => $dbotCollectionId,
                'name' => 'Pokemon Base Set',
                'type' => 'collection',
            ]);

        $mockDbotService->shouldReceive('getCollectionItems')
            ->with($dbotCollectionId)
            ->once()
            ->andReturn([
                'items' => [
                    [
                        'entity' => ['id' => $itemId1],
                        'order' => 1,
                    ],
                    [
                        'entity' => ['id' => $itemId2],
                        'order' => 2,
                    ],
                    [
                        'entity' => ['id' => $itemId3],
                        'order' => 3,
                    ],
                ],
                'pageInfo' => [
                    'hasNextPage' => false,
                    'endCursor' => null,
                ],
            ]);

        $this->app->instance(DatabaseOfThingsService::class, $mockDbotService);

        // When: Calling mutation with mode=TRACK and new_collection_name
        $response = $this->actingAs($user, 'sanctum')->postJson('/graphql', [
            'query' => '
                mutation($dbotCollectionId: ID!, $mode: WishlistMode!, $newCollectionName: String!) {
                    addCollectionToWishlist(
                        dbot_collection_id: $dbotCollectionId
                        mode: $mode
                        new_collection_name: $newCollectionName
                    ) {
                        created_collection {
                            id
                            name
                            linked_dbot_collection_id
                            parent_collection_id
                        }
                        items_added
                        items_already_owned
                        items_skipped
                    }
                }
            ',
            'variables' => [
                'dbotCollectionId' => $dbotCollectionId,
                'mode' => 'TRACK',
                'newCollectionName' => 'My Pokemon Cards',
            ],
        ]);

        // Then: Should create UserCollection with linked_dbot_collection_id
        $response->assertJson([
            'data' => [
                'addCollectionToWishlist' => [
                    'created_collection' => [
                        'name' => 'My Pokemon Cards',
                        'linked_dbot_collection_id' => $dbotCollectionId,
                        'parent_collection_id' => null,
                    ],
                    'items_added' => 3,
                    'items_already_owned' => 0,
                    'items_skipped' => 0,
                ],
            ],
        ]);

        // And: Should create collection in database
        $this->assertDatabaseHas('user_collections', [
            'user_id' => $user->id,
            'name' => 'My Pokemon Cards',
            'linked_dbot_collection_id' => $dbotCollectionId,
            'parent_collection_id' => null,
        ]);

        // And: Should add all items to wishlist
        $this->assertDatabaseHas('wishlists', [
            'user_id' => $user->id,
            'entity_id' => $itemId1,
        ]);
        $this->assertDatabaseHas('wishlists', [
            'user_id' => $user->id,
            'entity_id' => $itemId2,
        ]);
        $this->assertDatabaseHas('wishlists', [
            'user_id' => $user->id,
            'entity_id' => $itemId3,
        ]);

        // Verify wishlist items are linked to the created collection
        $collection = UserCollection::where('user_id', $user->id)
            ->where('name', 'My Pokemon Cards')
            ->first();

        $this->assertNotNull($collection);

        $wishlistItems = Wishlist::where('user_id', $user->id)
            ->where('parent_collection_id', $collection->id)
            ->get();

        $this->assertCount(3, $wishlistItems);
    }

    public function test_track_mode_sets_parent_collection(): void
    {
        // Given: A user and a parent collection
        $user = User::factory()->create();

        $parentCollection = UserCollection::create([
            'user_id' => $user->id,
            'name' => 'Trading Cards',
        ]);

        // Use proper UUID format
        $dbotCollectionId = '55555555-5555-5555-5555-555555555555';
        $itemId = '66666666-6666-6666-6666-666666666666';

        // Mock DatabaseOfThingsService
        $mockDbotService = Mockery::mock(DatabaseOfThingsService::class);
        $mockDbotService->shouldReceive('getCollection')
            ->with($dbotCollectionId)
            ->times(2) // Called twice: once in mutation validation, once in createTrackedCollection
            ->andReturn([
                'id' => $dbotCollectionId,
                'name' => 'Pokemon Jungle Set',
                'type' => 'collection',
            ]);

        $mockDbotService->shouldReceive('getCollectionItems')
            ->with($dbotCollectionId)
            ->once()
            ->andReturn([
                'items' => [
                    [
                        'entity' => ['id' => $itemId],
                        'order' => 1,
                    ],
                ],
                'pageInfo' => [
                    'hasNextPage' => false,
                    'endCursor' => null,
                ],
            ]);

        $this->app->instance(DatabaseOfThingsService::class, $mockDbotService);

        // When: Calling mutation with target_collection_id (as parent)
        $response = $this->actingAs($user, 'sanctum')->postJson('/graphql', [
            'query' => '
                mutation($dbotCollectionId: ID!, $mode: WishlistMode!, $newCollectionName: String!, $targetCollectionId: ID) {
                    addCollectionToWishlist(
                        dbot_collection_id: $dbotCollectionId
                        mode: $mode
                        new_collection_name: $newCollectionName
                        target_collection_id: $targetCollectionId
                    ) {
                        created_collection {
                            id
                            name
                            parent_collection_id
                        }
                        items_added
                    }
                }
            ',
            'variables' => [
                'dbotCollectionId' => $dbotCollectionId,
                'mode' => 'TRACK',
                'newCollectionName' => 'Jungle Set',
                'targetCollectionId' => $parentCollection->id,
            ],
        ]);

        // Then: Created collection should have parent_collection_id set
        $response->assertJson([
            'data' => [
                'addCollectionToWishlist' => [
                    'created_collection' => [
                        'name' => 'Jungle Set',
                        'parent_collection_id' => $parentCollection->id,
                    ],
                    'items_added' => 1,
                ],
            ],
        ]);

        // Verify in database
        $this->assertDatabaseHas('user_collections', [
            'user_id' => $user->id,
            'name' => 'Jungle Set',
            'parent_collection_id' => $parentCollection->id,
        ]);
    }

    public function test_track_mode_requires_new_collection_name(): void
    {
        // Given: A user
        $user = User::factory()->create();

        // Use proper UUID format
        $dbotCollectionId = '77777777-7777-7777-7777-777777777777';

        // When: Calling mutation with mode=TRACK but no new_collection_name
        $response = $this->actingAs($user, 'sanctum')->postJson('/graphql', [
            'query' => '
                mutation($dbotCollectionId: ID!, $mode: WishlistMode!) {
                    addCollectionToWishlist(
                        dbot_collection_id: $dbotCollectionId
                        mode: $mode
                    ) {
                        items_added
                    }
                }
            ',
            'variables' => [
                'dbotCollectionId' => $dbotCollectionId,
                'mode' => 'TRACK',
            ],
        ]);

        // Then: Should return GraphQL error
        $response->assertJsonStructure([
            'errors' => [
                '*' => ['message'],
            ],
        ]);

        $responseData = $response->json();
        $this->assertStringContainsString('new_collection_name is required', $responseData['errors'][0]['message']);
    }

    public function test_add_to_existing_mode_filters_items(): void
    {
        // Given: A user with existing collection containing 2 items (1 owned, 1 wishlisted)
        $user = User::factory()->create();

        $targetCollection = UserCollection::create([
            'user_id' => $user->id,
            'name' => 'My Pokemon Cards',
        ]);

        // Use proper UUID format
        $dbotCollectionId = '88888888-8888-8888-8888-888888888888';
        $ownedItemId = '99999999-9999-9999-9999-999999999999';
        $wishlistedItemId = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';
        $newItem1Id = 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb';
        $newItem2Id = 'cccccccc-cccc-cccc-cccc-cccccccccccc';
        $newItem3Id = 'dddddddd-dddd-dddd-dddd-dddddddddddd';

        // Create existing owned item
        \App\Models\UserItem::create([
            'user_id' => $user->id,
            'entity_id' => $ownedItemId,
            'parent_collection_id' => $targetCollection->id,
        ]);

        // Create existing wishlisted item
        Wishlist::create([
            'user_id' => $user->id,
            'entity_id' => $wishlistedItemId,
            'parent_collection_id' => $targetCollection->id,
        ]);

        // Mock DatabaseOfThingsService to return 5 items (including the 2 existing)
        $mockDbotService = Mockery::mock(DatabaseOfThingsService::class);
        $mockDbotService->shouldReceive('getCollection')
            ->with($dbotCollectionId)
            ->once()
            ->andReturn([
                'id' => $dbotCollectionId,
                'name' => 'Pokemon Cards',
                'type' => 'collection',
            ]);

        $mockDbotService->shouldReceive('getCollectionItems')
            ->with($dbotCollectionId)
            ->once()
            ->andReturn([
                'items' => [
                    ['entity' => ['id' => $ownedItemId], 'order' => 1],        // Already owned
                    ['entity' => ['id' => $wishlistedItemId], 'order' => 2],   // Already wishlisted
                    ['entity' => ['id' => $newItem1Id], 'order' => 3],         // New
                    ['entity' => ['id' => $newItem2Id], 'order' => 4],         // New
                    ['entity' => ['id' => $newItem3Id], 'order' => 5],         // New
                ],
                'pageInfo' => [
                    'hasNextPage' => false,
                    'endCursor' => null,
                ],
            ]);

        $this->app->instance(DatabaseOfThingsService::class, $mockDbotService);

        // When: Calling mutation with mode=ADD_TO_EXISTING
        $response = $this->actingAs($user, 'sanctum')->postJson('/graphql', [
            'query' => '
                mutation($dbotCollectionId: ID!, $mode: WishlistMode!, $targetCollectionId: ID!) {
                    addCollectionToWishlist(
                        dbot_collection_id: $dbotCollectionId
                        mode: $mode
                        target_collection_id: $targetCollectionId
                    ) {
                        created_collection {
                            id
                            name
                        }
                        items_added
                        items_already_owned
                        items_skipped
                    }
                }
            ',
            'variables' => [
                'dbotCollectionId' => $dbotCollectionId,
                'mode' => 'ADD_TO_EXISTING',
                'targetCollectionId' => $targetCollection->id,
            ],
        ]);

        // Then: Only 3 new items should be added to wishlist
        $response->assertJson([
            'data' => [
                'addCollectionToWishlist' => [
                    'created_collection' => null,
                    'items_added' => 3,
                    'items_already_owned' => 1,
                    'items_skipped' => 1,
                ],
            ],
        ]);

        // Verify 3 new items were added to wishlist
        $this->assertDatabaseHas('wishlists', [
            'user_id' => $user->id,
            'entity_id' => $newItem1Id,
            'parent_collection_id' => $targetCollection->id,
        ]);
        $this->assertDatabaseHas('wishlists', [
            'user_id' => $user->id,
            'entity_id' => $newItem2Id,
            'parent_collection_id' => $targetCollection->id,
        ]);
        $this->assertDatabaseHas('wishlists', [
            'user_id' => $user->id,
            'entity_id' => $newItem3Id,
            'parent_collection_id' => $targetCollection->id,
        ]);

        // Verify total wishlist count in collection is 4 (1 existing + 3 new)
        $totalWishlistCount = Wishlist::where('user_id', $user->id)
            ->where('parent_collection_id', $targetCollection->id)
            ->count();
        $this->assertEquals(4, $totalWishlistCount);
    }

    public function test_add_to_existing_mode_requires_target_collection_id(): void
    {
        // Given: A user
        $user = User::factory()->create();

        // Use proper UUID format
        $dbotCollectionId = 'eeeeeeee-eeee-eeee-eeee-eeeeeeeeeeee';

        // When: Calling mutation with mode=ADD_TO_EXISTING but no target_collection_id
        $response = $this->actingAs($user, 'sanctum')->postJson('/graphql', [
            'query' => '
                mutation($dbotCollectionId: ID!, $mode: WishlistMode!) {
                    addCollectionToWishlist(
                        dbot_collection_id: $dbotCollectionId
                        mode: $mode
                    ) {
                        items_added
                    }
                }
            ',
            'variables' => [
                'dbotCollectionId' => $dbotCollectionId,
                'mode' => 'ADD_TO_EXISTING',
            ],
        ]);

        // Then: Should return GraphQL error
        $response->assertJsonStructure([
            'errors' => [
                '*' => ['message'],
            ],
        ]);

        $responseData = $response->json();
        $this->assertStringContainsString('target_collection_id is required', $responseData['errors'][0]['message']);
    }

    public function test_add_to_existing_mode_validates_collection_ownership(): void
    {
        // Given: A user and another user's collection
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $otherUserCollection = UserCollection::create([
            'user_id' => $otherUser->id,
            'name' => 'Other User Collection',
        ]);

        // Use proper UUID format
        $dbotCollectionId = 'ffffffff-ffff-ffff-ffff-ffffffffffff';

        // Mock DatabaseOfThingsService to return valid collection
        $mockDbotService = Mockery::mock(DatabaseOfThingsService::class);
        $mockDbotService->shouldReceive('getCollection')
            ->with($dbotCollectionId)
            ->once()
            ->andReturn([
                'id' => $dbotCollectionId,
                'name' => 'Valid DBoT Collection',
                'type' => 'collection',
            ]);

        $this->app->instance(DatabaseOfThingsService::class, $mockDbotService);

        // When: Calling mutation with mode=ADD_TO_EXISTING and other user's collection_id
        $response = $this->actingAs($user, 'sanctum')->postJson('/graphql', [
            'query' => '
                mutation($dbotCollectionId: ID!, $mode: WishlistMode!, $targetCollectionId: ID!) {
                    addCollectionToWishlist(
                        dbot_collection_id: $dbotCollectionId
                        mode: $mode
                        target_collection_id: $targetCollectionId
                    ) {
                        items_added
                    }
                }
            ',
            'variables' => [
                'dbotCollectionId' => $dbotCollectionId,
                'mode' => 'ADD_TO_EXISTING',
                'targetCollectionId' => $otherUserCollection->id,
            ],
        ]);

        // Then: Should return GraphQL error (not authorized)
        $response->assertJsonStructure([
            'errors' => [
                '*' => ['message'],
            ],
        ]);

        $responseData = $response->json();
        $this->assertStringContainsString('Collection not found or you do not have permission', $responseData['errors'][0]['message']);
    }

    public function test_validates_dbot_collection_exists_in_add_to_existing_mode(): void
    {
        // Given: A user with a collection
        $user = User::factory()->create();

        $targetCollection = UserCollection::create([
            'user_id' => $user->id,
            'name' => 'My Pokemon Cards',
        ]);

        // Use proper UUID format for non-existent collection
        $invalidDbotCollectionId = '00000000-0000-0000-0000-000000000000';

        // Mock DatabaseOfThingsService to return null for invalid collection
        $mockDbotService = Mockery::mock(DatabaseOfThingsService::class);
        $mockDbotService->shouldReceive('getCollection')
            ->with($invalidDbotCollectionId)
            ->once()
            ->andReturn(null);

        $this->app->instance(DatabaseOfThingsService::class, $mockDbotService);

        // When: Calling mutation with invalid/non-existent dbot_collection_id
        $response = $this->actingAs($user, 'sanctum')->postJson('/graphql', [
            'query' => '
                mutation($dbotCollectionId: ID!, $mode: WishlistMode!, $targetCollectionId: ID!) {
                    addCollectionToWishlist(
                        dbot_collection_id: $dbotCollectionId
                        mode: $mode
                        target_collection_id: $targetCollectionId
                    ) {
                        items_added
                    }
                }
            ',
            'variables' => [
                'dbotCollectionId' => $invalidDbotCollectionId,
                'mode' => 'ADD_TO_EXISTING',
                'targetCollectionId' => $targetCollection->id,
            ],
        ]);

        // Then: Should return GraphQL error
        $response->assertJsonStructure([
            'errors' => [
                '*' => ['message'],
            ],
        ]);

        $responseData = $response->json();
        $this->assertStringContainsString('Database of Things collection not found', $responseData['errors'][0]['message']);
    }

    public function test_validates_dbot_collection_exists_in_track_mode(): void
    {
        // Given: A user
        $user = User::factory()->create();

        // Use proper UUID format for non-existent collection
        $invalidDbotCollectionId = '10000000-0000-0000-0000-000000000000';

        // Mock DatabaseOfThingsService to return null for invalid collection
        $mockDbotService = Mockery::mock(DatabaseOfThingsService::class);
        $mockDbotService->shouldReceive('getCollection')
            ->with($invalidDbotCollectionId)
            ->once()
            ->andReturn(null);

        $this->app->instance(DatabaseOfThingsService::class, $mockDbotService);

        // When: Calling mutation with invalid dbot_collection_id in TRACK mode
        $response = $this->actingAs($user, 'sanctum')->postJson('/graphql', [
            'query' => '
                mutation($dbotCollectionId: ID!, $mode: WishlistMode!, $newCollectionName: String!) {
                    addCollectionToWishlist(
                        dbot_collection_id: $dbotCollectionId
                        mode: $mode
                        new_collection_name: $newCollectionName
                    ) {
                        items_added
                    }
                }
            ',
            'variables' => [
                'dbotCollectionId' => $invalidDbotCollectionId,
                'mode' => 'TRACK',
                'newCollectionName' => 'My Collection',
            ],
        ]);

        // Then: Should return GraphQL error
        $response->assertJsonStructure([
            'errors' => [
                '*' => ['message'],
            ],
        ]);

        $responseData = $response->json();
        $this->assertStringContainsString('Database of Things collection not found', $responseData['errors'][0]['message']);
    }

    public function test_validates_parent_collection_exists_in_track_mode(): void
    {
        // Given: A user
        $user = User::factory()->create();

        // Use proper UUID format
        $dbotCollectionId = 'aabbccdd-aabb-aabb-aabb-aabbccddeeff';
        $invalidParentCollectionId = '20000000-0000-0000-0000-000000000000';

        // Mock DatabaseOfThingsService
        $mockDbotService = Mockery::mock(DatabaseOfThingsService::class);
        $mockDbotService->shouldReceive('getCollection')
            ->with($dbotCollectionId)
            ->once()
            ->andReturn([
                'id' => $dbotCollectionId,
                'name' => 'Valid Collection',
                'type' => 'collection',
            ]);

        $this->app->instance(DatabaseOfThingsService::class, $mockDbotService);

        // When: Calling mutation with invalid target_collection_id (as parent) in TRACK mode
        $response = $this->actingAs($user, 'sanctum')->postJson('/graphql', [
            'query' => '
                mutation($dbotCollectionId: ID!, $mode: WishlistMode!, $newCollectionName: String!, $targetCollectionId: ID) {
                    addCollectionToWishlist(
                        dbot_collection_id: $dbotCollectionId
                        mode: $mode
                        new_collection_name: $newCollectionName
                        target_collection_id: $targetCollectionId
                    ) {
                        items_added
                    }
                }
            ',
            'variables' => [
                'dbotCollectionId' => $dbotCollectionId,
                'mode' => 'TRACK',
                'newCollectionName' => 'My Collection',
                'targetCollectionId' => $invalidParentCollectionId,
            ],
        ]);

        // Then: Should return GraphQL error
        $response->assertJsonStructure([
            'errors' => [
                '*' => ['message'],
            ],
        ]);

        $responseData = $response->json();
        $this->assertStringContainsString('Parent collection not found or you do not have permission to use it', $responseData['errors'][0]['message']);
    }

    public function test_validates_parent_collection_ownership_in_track_mode(): void
    {
        // Given: A user and another user's collection
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $otherUserCollection = UserCollection::create([
            'user_id' => $otherUser->id,
            'name' => 'Other User Collection',
        ]);

        // Use proper UUID format
        $dbotCollectionId = 'bbccddee-bbcc-bbcc-bbcc-bbccddeeff00';

        // Mock DatabaseOfThingsService
        $mockDbotService = Mockery::mock(DatabaseOfThingsService::class);
        $mockDbotService->shouldReceive('getCollection')
            ->with($dbotCollectionId)
            ->once()
            ->andReturn([
                'id' => $dbotCollectionId,
                'name' => 'Valid Collection',
                'type' => 'collection',
            ]);

        $this->app->instance(DatabaseOfThingsService::class, $mockDbotService);

        // When: Calling mutation with other user's collection as parent in TRACK mode
        $response = $this->actingAs($user, 'sanctum')->postJson('/graphql', [
            'query' => '
                mutation($dbotCollectionId: ID!, $mode: WishlistMode!, $newCollectionName: String!, $targetCollectionId: ID) {
                    addCollectionToWishlist(
                        dbot_collection_id: $dbotCollectionId
                        mode: $mode
                        new_collection_name: $newCollectionName
                        target_collection_id: $targetCollectionId
                    ) {
                        items_added
                    }
                }
            ',
            'variables' => [
                'dbotCollectionId' => $dbotCollectionId,
                'mode' => 'TRACK',
                'newCollectionName' => 'My Collection',
                'targetCollectionId' => $otherUserCollection->id,
            ],
        ]);

        // Then: Should return GraphQL error
        $response->assertJsonStructure([
            'errors' => [
                '*' => ['message'],
            ],
        ]);

        $responseData = $response->json();
        $this->assertStringContainsString('Parent collection not found or you do not have permission to use it', $responseData['errors'][0]['message']);
    }

    public function test_handles_empty_dbot_collection_gracefully(): void
    {
        // Given: A DBoT collection with 0 items
        $user = User::factory()->create();

        // Use proper UUID format
        $dbotCollectionId = 'ccddeeff-ccdd-ccdd-ccdd-ccddeeff0011';

        // Mock DatabaseOfThingsService
        $mockDbotService = Mockery::mock(DatabaseOfThingsService::class);
        $mockDbotService->shouldReceive('getCollection')
            ->with($dbotCollectionId)
            ->times(2) // Called twice: once in mutation validation, once in createTrackedCollection
            ->andReturn([
                'id' => $dbotCollectionId,
                'name' => 'Empty Collection',
                'type' => 'collection',
            ]);

        $mockDbotService->shouldReceive('getCollectionItems')
            ->with($dbotCollectionId)
            ->once()
            ->andReturn([
                'items' => [], // Empty items array
                'pageInfo' => [
                    'hasNextPage' => false,
                    'endCursor' => null,
                ],
            ]);

        $this->app->instance(DatabaseOfThingsService::class, $mockDbotService);

        // When: Calling mutation with TRACK mode
        $response = $this->actingAs($user, 'sanctum')->postJson('/graphql', [
            'query' => '
                mutation($dbotCollectionId: ID!, $mode: WishlistMode!, $newCollectionName: String!) {
                    addCollectionToWishlist(
                        dbot_collection_id: $dbotCollectionId
                        mode: $mode
                        new_collection_name: $newCollectionName
                    ) {
                        created_collection {
                            id
                            name
                        }
                        items_added
                        items_already_owned
                        items_skipped
                    }
                }
            ',
            'variables' => [
                'dbotCollectionId' => $dbotCollectionId,
                'mode' => 'TRACK',
                'newCollectionName' => 'Empty Collection',
            ],
        ]);

        // Then: Should succeed with items_added = 0
        $response->assertJson([
            'data' => [
                'addCollectionToWishlist' => [
                    'created_collection' => [
                        'name' => 'Empty Collection',
                    ],
                    'items_added' => 0,
                    'items_already_owned' => 0,
                    'items_skipped' => 0,
                ],
            ],
        ]);

        // Verify collection was created
        $this->assertDatabaseHas('user_collections', [
            'user_id' => $user->id,
            'name' => 'Empty Collection',
            'linked_dbot_collection_id' => $dbotCollectionId,
        ]);
    }

    public function test_validates_new_collection_name_not_empty_string(): void
    {
        // Given: A user
        $user = User::factory()->create();

        // Use proper UUID format
        $dbotCollectionId = 'ddeeff00-ddee-ddee-ddee-ddeeff001122';

        // Note: We don't mock the service here because GraphQL schema validation
        // will reject the whitespace-only string before reaching our mutation code.
        // This test verifies that whitespace strings are rejected (either by GraphQL or our code).

        // When: Calling TRACK mode with empty string for new_collection_name
        $response = $this->actingAs($user, 'sanctum')->postJson('/graphql', [
            'query' => '
                mutation($dbotCollectionId: ID!, $mode: WishlistMode!, $newCollectionName: String!) {
                    addCollectionToWishlist(
                        dbot_collection_id: $dbotCollectionId
                        mode: $mode
                        new_collection_name: $newCollectionName
                    ) {
                        items_added
                    }
                }
            ',
            'variables' => [
                'dbotCollectionId' => $dbotCollectionId,
                'mode' => 'TRACK',
                'newCollectionName' => '   ', // Only whitespace
            ],
        ]);

        // Then: Should return GraphQL error
        $response->assertJsonStructure([
            'errors' => [
                '*' => ['message'],
            ],
        ]);

        $responseData = $response->json();
        // GraphQL may reject whitespace-only strings at schema level OR our validation catches it
        // Both are valid - the important thing is that whitespace is rejected
        $errorMessage = $responseData['errors'][0]['message'];
        $this->assertTrue(
            str_contains($errorMessage, 'new_collection_name is required and cannot be empty') ||
            str_contains($errorMessage, 'Variable "$newCollectionName"'),
            "Expected error about empty collection name, got: {$errorMessage}"
        );
    }

    public function test_integration_track_mode_complete_flow(): void
    {
        // This is a comprehensive integration test that verifies:
        // 1. User authenticates
        // 2. GraphQL mutation is called with TRACK mode
        // 3. UserCollection record is created with correct fields
        // 4. Wishlist records are created for all DBoT items
        // 5. All wishlist records link to created collection
        // 6. Response contains correct data and counts
        // 7. Database state is correct

        // Given: A user and a DBoT collection with 3 items
        $user = User::factory()->create();

        $dbotCollectionId = '11111111-1111-1111-1111-111111111111';
        $itemIds = [
            '22222222-2222-2222-2222-222222222222',
            '33333333-3333-3333-3333-333333333333',
            '44444444-4444-4444-4444-444444444444',
        ];

        // Mock DatabaseOfThingsService
        $this->mockDbotCollection($dbotCollectionId, $itemIds, 'Pokemon Base Set', 2);

        // When: Executing mutation with mode=TRACK
        $response = $this->executeAddCollectionMutation(
            $user,
            $dbotCollectionId,
            'TRACK',
            'My Pokemon Cards'
        );

        // Then: Verify all database records created correctly
        $response->assertJson([
            'data' => [
                'addCollectionToWishlist' => [
                    'created_collection' => [
                        'name' => 'My Pokemon Cards',
                        'linked_dbot_collection_id' => $dbotCollectionId,
                        'parent_collection_id' => null,
                    ],
                    'items_added' => 3,
                    'items_already_owned' => 0,
                    'items_skipped' => 0,
                ],
            ],
        ]);

        // Verify UserCollection exists
        $collection = UserCollection::where('user_id', $user->id)
            ->where('name', 'My Pokemon Cards')
            ->where('linked_dbot_collection_id', $dbotCollectionId)
            ->first();
        $this->assertNotNull($collection, 'UserCollection was not created');

        // Verify all 3 wishlist records created
        $wishlistItems = Wishlist::where('user_id', $user->id)
            ->where('parent_collection_id', $collection->id)
            ->get();
        $this->assertCount(3, $wishlistItems, 'Expected 3 wishlist items');

        // Verify each item exists and is linked to collection
        $entityIds = $wishlistItems->pluck('entity_id')->toArray();
        foreach ($itemIds as $itemId) {
            $this->assertContains($itemId, $entityIds);
        }

        // Verify all wishlist items have correct parent_collection_id
        foreach ($wishlistItems as $wishlistItem) {
            $this->assertEquals($collection->id, $wishlistItem->parent_collection_id);
        }
    }

    public function test_integration_add_to_existing_complete_flow(): void
    {
        // This is a comprehensive integration test that verifies:
        // 1. User has existing collection with 2 items (1 owned, 1 wishlisted)
        // 2. GraphQL mutation is called with ADD_TO_EXISTING mode
        // 3. Only new items are added to wishlist (not duplicates)
        // 4. All new wishlist records link to target collection
        // 5. Response contains accurate counts
        // 6. Database state reflects filtering

        // Given: User with collection containing 2 items, DBoT with 5 items (2 overlap)
        $user = User::factory()->create();

        $targetCollection = UserCollection::create([
            'user_id' => $user->id,
            'name' => 'My Pokemon Cards',
        ]);

        $dbotCollectionId = '88888888-8888-8888-8888-888888888888';
        $ownedItemId = '99999999-9999-9999-9999-999999999999';
        $wishlistedItemId = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';
        $newItem1Id = 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb';
        $newItem2Id = 'cccccccc-cccc-cccc-cccc-cccccccccccc';
        $newItem3Id = 'dddddddd-dddd-dddd-dddd-dddddddddddd';

        // Create existing owned item
        \App\Models\UserItem::create([
            'user_id' => $user->id,
            'entity_id' => $ownedItemId,
            'parent_collection_id' => $targetCollection->id,
        ]);

        // Create existing wishlisted item
        Wishlist::create([
            'user_id' => $user->id,
            'entity_id' => $wishlistedItemId,
            'parent_collection_id' => $targetCollection->id,
        ]);

        // Mock DatabaseOfThingsService
        $mockDbotService = Mockery::mock(DatabaseOfThingsService::class);
        $mockDbotService->shouldReceive('getCollection')
            ->with($dbotCollectionId)
            ->once()
            ->andReturn([
                'id' => $dbotCollectionId,
                'name' => 'Pokemon Cards',
                'type' => 'collection',
            ]);

        $mockDbotService->shouldReceive('getCollectionItems')
            ->with($dbotCollectionId)
            ->once()
            ->andReturn([
                'items' => [
                    ['entity' => ['id' => $ownedItemId], 'order' => 1],
                    ['entity' => ['id' => $wishlistedItemId], 'order' => 2],
                    ['entity' => ['id' => $newItem1Id], 'order' => 3],
                    ['entity' => ['id' => $newItem2Id], 'order' => 4],
                    ['entity' => ['id' => $newItem3Id], 'order' => 5],
                ],
                'pageInfo' => [
                    'hasNextPage' => false,
                    'endCursor' => null,
                ],
            ]);

        $this->app->instance(DatabaseOfThingsService::class, $mockDbotService);

        // When: Executing mutation with mode=ADD_TO_EXISTING
        $response = $this->actingAs($user, 'sanctum')->postJson('/graphql', [
            'query' => '
                mutation($dbotCollectionId: ID!, $mode: WishlistMode!, $targetCollectionId: ID!) {
                    addCollectionToWishlist(
                        dbot_collection_id: $dbotCollectionId
                        mode: $mode
                        target_collection_id: $targetCollectionId
                    ) {
                        created_collection {
                            id
                            name
                        }
                        items_added
                        items_already_owned
                        items_skipped
                    }
                }
            ',
            'variables' => [
                'dbotCollectionId' => $dbotCollectionId,
                'mode' => 'ADD_TO_EXISTING',
                'targetCollectionId' => $targetCollection->id,
            ],
        ]);

        // Then: Verify only 3 new wishlist records created
        $response->assertJson([
            'data' => [
                'addCollectionToWishlist' => [
                    'created_collection' => null,
                    'items_added' => 3,
                    'items_already_owned' => 1,
                    'items_skipped' => 1,
                ],
            ],
        ]);

        // Verify total wishlist count is 4 (1 existing + 3 new)
        $totalWishlistCount = Wishlist::where('user_id', $user->id)
            ->where('parent_collection_id', $targetCollection->id)
            ->count();
        $this->assertEquals(4, $totalWishlistCount, 'Expected 4 total wishlist items (1 existing + 3 new)');

        // Verify the 3 new items were added
        $this->assertDatabaseHas('wishlists', [
            'user_id' => $user->id,
            'entity_id' => $newItem1Id,
            'parent_collection_id' => $targetCollection->id,
        ]);
        $this->assertDatabaseHas('wishlists', [
            'user_id' => $user->id,
            'entity_id' => $newItem2Id,
            'parent_collection_id' => $targetCollection->id,
        ]);
        $this->assertDatabaseHas('wishlists', [
            'user_id' => $user->id,
            'entity_id' => $newItem3Id,
            'parent_collection_id' => $targetCollection->id,
        ]);

        // Verify owned item was NOT added to wishlist again
        $ownedItemWishlistCount = Wishlist::where('user_id', $user->id)
            ->where('entity_id', $ownedItemId)
            ->count();
        $this->assertEquals(0, $ownedItemWishlistCount, 'Owned item should not be in wishlist');

        // Verify existing wishlisted item was NOT duplicated
        $wishlistedItemCount = Wishlist::where('user_id', $user->id)
            ->where('entity_id', $wishlistedItemId)
            ->count();
        $this->assertEquals(1, $wishlistedItemCount, 'Wishlisted item should only exist once');
    }

    public function test_integration_nested_collection_hierarchy(): void
    {
        // Test creating nested collection structure
        // Given: User with collection A
        $user = User::factory()->create();

        $parentCollection = UserCollection::create([
            'user_id' => $user->id,
            'name' => 'Trading Cards',
        ]);

        $dbotCollectionId = '55555555-5555-5555-5555-555555555555';
        $itemIds = [
            '66666666-6666-6666-6666-666666666666',
            '77777777-7777-7777-7777-777777777777',
        ];

        // Mock DatabaseOfThingsService
        $this->mockDbotCollection($dbotCollectionId, $itemIds, 'Pokemon Jungle Set', 2);

        // When: Creating tracked collection B as child of A
        $response = $this->executeAddCollectionMutation(
            $user,
            $dbotCollectionId,
            'TRACK',
            'Jungle Set',
            $parentCollection->id
        );

        // Then: Verify parent_collection_id is set correctly
        $response->assertJson([
            'data' => [
                'addCollectionToWishlist' => [
                    'created_collection' => [
                        'name' => 'Jungle Set',
                        'parent_collection_id' => $parentCollection->id,
                        'linked_dbot_collection_id' => $dbotCollectionId,
                    ],
                    'items_added' => 2,
                ],
            ],
        ]);

        // Verify child collection exists in database
        $childCollection = UserCollection::where('user_id', $user->id)
            ->where('name', 'Jungle Set')
            ->where('parent_collection_id', $parentCollection->id)
            ->first();
        $this->assertNotNull($childCollection, 'Child collection was not created');

        // Verify items are linked to child collection B, not parent
        $wishlistItems = Wishlist::where('user_id', $user->id)
            ->where('parent_collection_id', $childCollection->id)
            ->get();
        $this->assertCount(2, $wishlistItems, 'Expected 2 wishlist items linked to child collection');

        // Verify no items are directly linked to parent collection
        $parentWishlistItems = Wishlist::where('user_id', $user->id)
            ->where('parent_collection_id', $parentCollection->id)
            ->count();
        $this->assertEquals(0, $parentWishlistItems, 'No items should be directly linked to parent collection');
    }

    public function test_integration_multiple_additions_to_same_collection(): void
    {
        // Test adding multiple DBoT collections to same target
        // Given: User with empty collection
        $user = User::factory()->create();

        $targetCollection = UserCollection::create([
            'user_id' => $user->id,
            'name' => 'All My Cards',
        ]);

        $dbotCollection1Id = 'aaaa0000-aaaa-aaaa-aaaa-aaaaaaaaaaaa';
        $dbotCollection2Id = 'bbbb0000-bbbb-bbbb-bbbb-bbbbbbbbbbbb';

        // Collection 1 items
        $item1Id = '1111aaaa-1111-1111-1111-111111111111';
        $item2Id = '2222aaaa-2222-2222-2222-222222222222';
        $item3Id = '3333aaaa-3333-3333-3333-333333333333';

        // Collection 2 items (item3Id overlaps with collection 1)
        $item4Id = '4444bbbb-4444-4444-4444-444444444444';
        $item5Id = '5555bbbb-5555-5555-5555-555555555555';

        // Mock DatabaseOfThingsService
        $mockDbotService = Mockery::mock(DatabaseOfThingsService::class);

        // First collection
        $mockDbotService->shouldReceive('getCollection')
            ->with($dbotCollection1Id)
            ->once()
            ->andReturn([
                'id' => $dbotCollection1Id,
                'name' => 'Pokemon Base Set',
                'type' => 'collection',
            ]);

        $mockDbotService->shouldReceive('getCollectionItems')
            ->with($dbotCollection1Id)
            ->once()
            ->andReturn([
                'items' => [
                    ['entity' => ['id' => $item1Id], 'order' => 1],
                    ['entity' => ['id' => $item2Id], 'order' => 2],
                    ['entity' => ['id' => $item3Id], 'order' => 3],
                ],
                'pageInfo' => [
                    'hasNextPage' => false,
                    'endCursor' => null,
                ],
            ]);

        // Second collection
        $mockDbotService->shouldReceive('getCollection')
            ->with($dbotCollection2Id)
            ->once()
            ->andReturn([
                'id' => $dbotCollection2Id,
                'name' => 'Pokemon Jungle Set',
                'type' => 'collection',
            ]);

        $mockDbotService->shouldReceive('getCollectionItems')
            ->with($dbotCollection2Id)
            ->once()
            ->andReturn([
                'items' => [
                    ['entity' => ['id' => $item3Id], 'order' => 1], // Overlap with collection 1
                    ['entity' => ['id' => $item4Id], 'order' => 2],
                    ['entity' => ['id' => $item5Id], 'order' => 3],
                ],
                'pageInfo' => [
                    'hasNextPage' => false,
                    'endCursor' => null,
                ],
            ]);

        $this->app->instance(DatabaseOfThingsService::class, $mockDbotService);

        // When: Adding DBoT collection 1 (3 items)
        $response1 = $this->actingAs($user, 'sanctum')->postJson('/graphql', [
            'query' => '
                mutation($dbotCollectionId: ID!, $mode: WishlistMode!, $targetCollectionId: ID!) {
                    addCollectionToWishlist(
                        dbot_collection_id: $dbotCollectionId
                        mode: $mode
                        target_collection_id: $targetCollectionId
                    ) {
                        items_added
                        items_already_owned
                        items_skipped
                    }
                }
            ',
            'variables' => [
                'dbotCollectionId' => $dbotCollection1Id,
                'mode' => 'ADD_TO_EXISTING',
                'targetCollectionId' => $targetCollection->id,
            ],
        ]);

        // Then: Verify 3 items added from first collection
        $response1->assertJson([
            'data' => [
                'addCollectionToWishlist' => [
                    'items_added' => 3,
                    'items_already_owned' => 0,
                    'items_skipped' => 0,
                ],
            ],
        ]);

        // When: Adding DBoT collection 2 (3 items, 1 overlap with collection 1)
        $response2 = $this->actingAs($user, 'sanctum')->postJson('/graphql', [
            'query' => '
                mutation($dbotCollectionId: ID!, $mode: WishlistMode!, $targetCollectionId: ID!) {
                    addCollectionToWishlist(
                        dbot_collection_id: $dbotCollectionId
                        mode: $mode
                        target_collection_id: $targetCollectionId
                    ) {
                        items_added
                        items_already_owned
                        items_skipped
                    }
                }
            ',
            'variables' => [
                'dbotCollectionId' => $dbotCollection2Id,
                'mode' => 'ADD_TO_EXISTING',
                'targetCollectionId' => $targetCollection->id,
            ],
        ]);

        // Then: Verify only 2 new items added (item3Id already exists)
        $response2->assertJson([
            'data' => [
                'addCollectionToWishlist' => [
                    'items_added' => 2,
                    'items_already_owned' => 0,
                    'items_skipped' => 1,
                ],
            ],
        ]);

        // Verify 5 total wishlist items (no duplicates)
        $totalWishlistCount = Wishlist::where('user_id', $user->id)
            ->where('parent_collection_id', $targetCollection->id)
            ->count();
        $this->assertEquals(5, $totalWishlistCount, 'Expected 5 unique wishlist items');

        // Verify item3Id only exists once (not duplicated)
        $item3Count = Wishlist::where('user_id', $user->id)
            ->where('entity_id', $item3Id)
            ->count();
        $this->assertEquals(1, $item3Count, 'Overlapping item should only exist once');

        // Verify all 5 unique items exist
        $entityIds = Wishlist::where('user_id', $user->id)
            ->where('parent_collection_id', $targetCollection->id)
            ->pluck('entity_id')
            ->toArray();
        $this->assertContains($item1Id, $entityIds);
        $this->assertContains($item2Id, $entityIds);
        $this->assertContains($item3Id, $entityIds);
        $this->assertContains($item4Id, $entityIds);
        $this->assertContains($item5Id, $entityIds);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
