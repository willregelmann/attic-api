<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\UserCollection;
use App\Models\UserItem;
use App\Models\Wishlist;
use App\Services\UserCollectionService;

class UserCollectionServiceProgressTest extends TestCase
{
    use RefreshDatabase;

    protected UserCollectionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new UserCollectionService();
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
}
