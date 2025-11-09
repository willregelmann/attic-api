<?php

namespace Tests\Unit\GraphQL\FieldResolvers;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\UserCollection;
use App\Models\UserItem;
use App\Models\Wishlist;
use App\GraphQL\FieldResolvers\UserCollectionProgressResolver;
use App\Services\UserCollectionService;

class UserCollectionProgressResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_progress_resolver_returns_correct_structure()
    {
        $user = User::factory()->create();
        $collection = UserCollection::create([
            'user_id' => $user->id,
            'name' => 'Test Collection',
        ]);

        // Add 3 owned, 2 wishlist
        for ($i = 0; $i < 3; $i++) {
            UserItem::create([
                'user_id' => $user->id,
                'entity_id' => \Illuminate\Support\Str::uuid()->toString(),
                'parent_collection_id' => $collection->id,
            ]);
        }

        for ($i = 0; $i < 2; $i++) {
            Wishlist::create([
                'user_id' => $user->id,
                'entity_id' => \Illuminate\Support\Str::uuid()->toString(),
                'parent_collection_id' => $collection->id,
            ]);
        }

        $service = new UserCollectionService();
        $resolver = new UserCollectionProgressResolver($service);
        $progress = $resolver($collection);

        $this->assertIsArray($progress);
        $this->assertArrayHasKey('owned_count', $progress);
        $this->assertArrayHasKey('wishlist_count', $progress);
        $this->assertArrayHasKey('total_count', $progress);
        $this->assertArrayHasKey('percentage', $progress);

        $this->assertEquals(3, $progress['owned_count']);
        $this->assertEquals(2, $progress['wishlist_count']);
        $this->assertEquals(5, $progress['total_count']);
        $this->assertEquals(60, $progress['percentage']); // 3/5 = 60%
    }

    public function test_progress_resolver_includes_nested_collections()
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

        // Parent: 1 item
        UserItem::create([
            'user_id' => $user->id,
            'entity_id' => \Illuminate\Support\Str::uuid()->toString(),
            'parent_collection_id' => $parent->id,
        ]);

        // Child: 2 items
        for ($i = 0; $i < 2; $i++) {
            UserItem::create([
                'user_id' => $user->id,
                'entity_id' => \Illuminate\Support\Str::uuid()->toString(),
                'parent_collection_id' => $child->id,
            ]);
        }

        $service = new UserCollectionService();
        $resolver = new UserCollectionProgressResolver($service);
        $progress = $resolver($parent);

        // Should aggregate parent + child = 3 items
        $this->assertEquals(3, $progress['owned_count']);
    }
}
