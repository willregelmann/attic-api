<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\UserCollection;
use App\Models\UserItem;
use App\Models\Wishlist;
use App\Services\DatabaseOfThingsService;
use App\Services\UserCollectionService;

class UserCollectionServiceProgressTest extends TestCase
{
    use RefreshDatabase;

    protected UserCollectionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        // Mock the DatabaseOfThingsService since we don't need it for these tests
        $dbotService = $this->createMock(DatabaseOfThingsService::class);
        $this->service = new UserCollectionService($dbotService);
    }

    public function test_calculate_simple_progress_with_no_items()
    {
        $user = User::factory()->create();
        $collection = UserCollection::create([
            'user_id' => $user->id,
            'name' => 'Empty Collection',
        ]);

        $progress = $this->service->calculateSimpleProgress($collection->id);

        $this->assertEquals(0, $progress['owned_count']);
        $this->assertEquals(0, $progress['wishlist_count']);
        $this->assertEquals(0, $progress['total_count']);
        $this->assertEquals(0, $progress['percentage']);
    }

    public function test_calculate_simple_progress_with_only_owned_items()
    {
        $user = User::factory()->create();
        $collection = UserCollection::create([
            'user_id' => $user->id,
            'name' => 'Test Collection',
        ]);

        // Add 3 owned items (entity_id must be valid UUID)
        for ($i = 0; $i < 3; $i++) {
            UserItem::create([
                'user_id' => $user->id,
                'entity_id' => \Illuminate\Support\Str::uuid()->toString(),
                'parent_collection_id' => $collection->id,
            ]);
        }

        $progress = $this->service->calculateSimpleProgress($collection->id);

        $this->assertEquals(3, $progress['owned_count']);
        $this->assertEquals(0, $progress['wishlist_count']);
        $this->assertEquals(3, $progress['total_count']);
        $this->assertEquals(100, $progress['percentage']);
    }

    public function test_calculate_simple_progress_with_only_wishlist_items()
    {
        $user = User::factory()->create();
        $collection = UserCollection::create([
            'user_id' => $user->id,
            'name' => 'Test Collection',
        ]);

        // Add 2 wishlist items (entity_id must be valid UUID)
        for ($i = 0; $i < 2; $i++) {
            Wishlist::create([
                'user_id' => $user->id,
                'entity_id' => \Illuminate\Support\Str::uuid()->toString(),
                'parent_collection_id' => $collection->id,
            ]);
        }

        $progress = $this->service->calculateSimpleProgress($collection->id);

        $this->assertEquals(0, $progress['owned_count']);
        $this->assertEquals(2, $progress['wishlist_count']);
        $this->assertEquals(2, $progress['total_count']);
        $this->assertEquals(0, $progress['percentage']);
    }

    public function test_calculate_simple_progress_with_mixed_items()
    {
        $user = User::factory()->create();
        $collection = UserCollection::create([
            'user_id' => $user->id,
            'name' => 'Test Collection',
        ]);

        // Add 3 owned items (entity_id must be valid UUID)
        for ($i = 0; $i < 3; $i++) {
            UserItem::create([
                'user_id' => $user->id,
                'entity_id' => \Illuminate\Support\Str::uuid()->toString(),
                'parent_collection_id' => $collection->id,
            ]);
        }

        // Add 7 wishlist items (entity_id must be valid UUID)
        for ($i = 0; $i < 7; $i++) {
            Wishlist::create([
                'user_id' => $user->id,
                'entity_id' => \Illuminate\Support\Str::uuid()->toString(),
                'parent_collection_id' => $collection->id,
            ]);
        }

        $progress = $this->service->calculateSimpleProgress($collection->id);

        $this->assertEquals(3, $progress['owned_count']);
        $this->assertEquals(7, $progress['wishlist_count']);
        $this->assertEquals(10, $progress['total_count']);
        $this->assertEquals(30, $progress['percentage']); // 3/10 = 30%
    }

    public function test_calculate_simple_progress_only_counts_direct_children()
    {
        $user = User::factory()->create();

        // Create parent and child collections
        $parentCollection = UserCollection::create([
            'user_id' => $user->id,
            'name' => 'Parent Collection',
        ]);

        $childCollection = UserCollection::create([
            'user_id' => $user->id,
            'name' => 'Child Collection',
            'parent_collection_id' => $parentCollection->id,
        ]);

        // Add items to parent (entity_id must be valid UUID)
        UserItem::create([
            'user_id' => $user->id,
            'entity_id' => \Illuminate\Support\Str::uuid()->toString(),
            'parent_collection_id' => $parentCollection->id,
        ]);

        // Add items to child (should NOT be counted in simple progress)
        UserItem::create([
            'user_id' => $user->id,
            'entity_id' => \Illuminate\Support\Str::uuid()->toString(),
            'parent_collection_id' => $childCollection->id,
        ]);

        $progress = $this->service->calculateSimpleProgress($parentCollection->id);

        // Should only count the parent's direct item, not the child's item
        $this->assertEquals(1, $progress['owned_count']);
        $this->assertEquals(0, $progress['wishlist_count']);
        $this->assertEquals(1, $progress['total_count']);
        $this->assertEquals(100, $progress['percentage']);
    }

    public function test_calculate_progress_aggregates_nested_collections()
    {
        $user = User::factory()->create();

        // Create hierarchy: Parent > Child > Grandchild
        $parent = UserCollection::create([
            'user_id' => $user->id,
            'name' => 'Parent Collection',
        ]);

        $child = UserCollection::create([
            'user_id' => $user->id,
            'name' => 'Child Collection',
            'parent_collection_id' => $parent->id,
        ]);

        $grandchild = UserCollection::create([
            'user_id' => $user->id,
            'name' => 'Grandchild Collection',
            'parent_collection_id' => $child->id,
        ]);

        // Add items at each level
        // Parent: 1 owned
        UserItem::create([
            'user_id' => $user->id,
            'entity_id' => \Illuminate\Support\Str::uuid()->toString(),
            'parent_collection_id' => $parent->id,
        ]);

        // Child: 2 owned, 1 wishlist
        for ($i = 0; $i < 2; $i++) {
            UserItem::create([
                'user_id' => $user->id,
                'entity_id' => \Illuminate\Support\Str::uuid()->toString(),
                'parent_collection_id' => $child->id,
            ]);
        }
        Wishlist::create([
            'user_id' => $user->id,
            'entity_id' => \Illuminate\Support\Str::uuid()->toString(),
            'parent_collection_id' => $child->id,
        ]);

        // Grandchild: 1 wishlist
        Wishlist::create([
            'user_id' => $user->id,
            'entity_id' => \Illuminate\Support\Str::uuid()->toString(),
            'parent_collection_id' => $grandchild->id,
        ]);

        // Test parent aggregates all descendants
        // Total: 3 owned, 2 wishlist = 5 total, 60%
        $progress = $this->service->calculateProgress($parent->id);

        $this->assertEquals(3, $progress['owned_count']);
        $this->assertEquals(2, $progress['wishlist_count']);
        $this->assertEquals(5, $progress['total_count']);
        $this->assertEquals(60, $progress['percentage']);
    }

    public function test_calculate_progress_handles_deep_nesting()
    {
        $user = User::factory()->create();

        // Create 5 levels deep
        $collections = [];
        $collections[0] = UserCollection::create([
            'user_id' => $user->id,
            'name' => 'Level 0',
        ]);

        for ($i = 1; $i <= 4; $i++) {
            $collections[$i] = UserCollection::create([
                'user_id' => $user->id,
                'name' => 'Level ' . $i,
                'parent_collection_id' => $collections[$i - 1]->id,
            ]);
        }

        // Add 1 item at each level (5 total)
        for ($i = 0; $i <= 4; $i++) {
            UserItem::create([
                'user_id' => $user->id,
                'entity_id' => \Illuminate\Support\Str::uuid()->toString(),
                'parent_collection_id' => $collections[$i]->id,
            ]);
        }

        // Root should aggregate all 5 items
        $progress = $this->service->calculateProgress($collections[0]->id);

        $this->assertEquals(5, $progress['owned_count']);
        $this->assertEquals(0, $progress['wishlist_count']);
        $this->assertEquals(5, $progress['total_count']);
        $this->assertEquals(100, $progress['percentage']);
    }

    public function test_calculate_progress_with_multiple_branches()
    {
        $user = User::factory()->create();

        // Create tree structure:
        //        Root
        //       /    \
        //    Branch1  Branch2
        //      |        |
        //    Leaf1    Leaf2

        $root = UserCollection::create([
            'user_id' => $user->id,
            'name' => 'Root',
        ]);

        $branch1 = UserCollection::create([
            'user_id' => $user->id,
            'name' => 'Branch 1',
            'parent_collection_id' => $root->id,
        ]);

        $branch2 = UserCollection::create([
            'user_id' => $user->id,
            'name' => 'Branch 2',
            'parent_collection_id' => $root->id,
        ]);

        $leaf1 = UserCollection::create([
            'user_id' => $user->id,
            'name' => 'Leaf 1',
            'parent_collection_id' => $branch1->id,
        ]);

        $leaf2 = UserCollection::create([
            'user_id' => $user->id,
            'name' => 'Leaf 2',
            'parent_collection_id' => $branch2->id,
        ]);

        // Add items to each leaf
        UserItem::create([
            'user_id' => $user->id,
            'entity_id' => \Illuminate\Support\Str::uuid()->toString(),
            'parent_collection_id' => $leaf1->id,
        ]);

        UserItem::create([
            'user_id' => $user->id,
            'entity_id' => \Illuminate\Support\Str::uuid()->toString(),
            'parent_collection_id' => $leaf2->id,
        ]);

        Wishlist::create([
            'user_id' => $user->id,
            'entity_id' => \Illuminate\Support\Str::uuid()->toString(),
            'parent_collection_id' => $leaf2->id,
        ]);

        // Root should aggregate both branches
        $progress = $this->service->calculateProgress($root->id);

        $this->assertEquals(2, $progress['owned_count']);
        $this->assertEquals(1, $progress['wishlist_count']);
        $this->assertEquals(3, $progress['total_count']);
        $this->assertEquals(66.67, $progress['percentage']); // 2/3 = 66.67%
    }

    public function test_calculate_progress_empty_nested_collections()
    {
        $user = User::factory()->create();

        $parent = UserCollection::create([
            'user_id' => $user->id,
            'name' => 'Parent',
        ]);

        // Create empty child
        UserCollection::create([
            'user_id' => $user->id,
            'name' => 'Empty Child',
            'parent_collection_id' => $parent->id,
        ]);

        // Parent should show 0/0 even with empty child collection
        $progress = $this->service->calculateProgress($parent->id);

        $this->assertEquals(0, $progress['owned_count']);
        $this->assertEquals(0, $progress['wishlist_count']);
        $this->assertEquals(0, $progress['total_count']);
        $this->assertEquals(0, $progress['percentage']);
    }
}
