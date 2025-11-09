<?php

namespace Tests\Unit\GraphQL\FieldResolvers;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\UserCollection;
use App\Models\UserItem;
use App\Models\Wishlist;
use App\GraphQL\FieldResolvers\UserCollectionRepresentativeImagesResolver;
use App\Services\DatabaseOfThingsService;
use Mockery;

class UserCollectionRepresentativeImagesResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_custom_image_if_set()
    {
        $user = User::factory()->create();
        $collection = UserCollection::create([
            'user_id' => $user->id,
            'name' => 'Test Collection',
            'custom_image' => 'https://example.com/custom.jpg',
        ]);

        $mockService = Mockery::mock(DatabaseOfThingsService::class);
        $resolver = new UserCollectionRepresentativeImagesResolver($mockService);

        $images = $resolver($collection);

        $this->assertCount(1, $images);
        $this->assertEquals('https://example.com/custom.jpg', $images[0]);
    }

    public function test_returns_empty_array_for_empty_collection()
    {
        $user = User::factory()->create();
        $collection = UserCollection::create([
            'user_id' => $user->id,
            'name' => 'Empty Collection',
        ]);

        $mockService = Mockery::mock(DatabaseOfThingsService::class);
        $mockService->shouldReceive('getEntitiesByIds')->andReturn([]);

        $resolver = new UserCollectionRepresentativeImagesResolver($mockService);

        $images = $resolver($collection);

        $this->assertIsArray($images);
        $this->assertCount(0, $images);
    }

    public function test_extracts_images_from_items_and_wishlists()
    {
        $user = User::factory()->create();

        // Use proper UUIDs for entity_id
        $entityId1 = '550e8400-e29b-41d4-a716-446655440001';
        $entityId2 = '550e8400-e29b-41d4-a716-446655440002';
        $entityId3 = '550e8400-e29b-41d4-a716-446655440003';

        $collection = UserCollection::create([
            'user_id' => $user->id,
            'name' => 'Test Collection',
        ]);

        // Create 2 items
        UserItem::create([
            'user_id' => $user->id,
            'entity_id' => $entityId1,
            'parent_collection_id' => $collection->id,
        ]);

        UserItem::create([
            'user_id' => $user->id,
            'entity_id' => $entityId2,
            'parent_collection_id' => $collection->id,
        ]);

        // Create 1 wishlist
        Wishlist::create([
            'user_id' => $user->id,
            'entity_id' => $entityId3,
            'parent_collection_id' => $collection->id,
        ]);

        $mockService = Mockery::mock(DatabaseOfThingsService::class);
        $mockService->shouldReceive('getEntitiesByIds')
            ->with([$entityId1, $entityId2, $entityId3])
            ->andReturn([
                $entityId1 => ['id' => $entityId1, 'image_url' => 'https://example.com/1.jpg'],
                $entityId2 => ['id' => $entityId2, 'image_url' => 'https://example.com/2.jpg'],
                $entityId3 => ['id' => $entityId3, 'image_url' => 'https://example.com/3.jpg'],
            ]);

        $resolver = new UserCollectionRepresentativeImagesResolver($mockService);
        $images = $resolver($collection);

        $this->assertCount(3, $images);
        $this->assertEquals('https://example.com/1.jpg', $images[0]);
        $this->assertEquals('https://example.com/2.jpg', $images[1]);
        $this->assertEquals('https://example.com/3.jpg', $images[2]);
    }

    public function test_returns_maximum_4_images()
    {
        $user = User::factory()->create();
        $collection = UserCollection::create([
            'user_id' => $user->id,
            'name' => 'Test Collection',
        ]);

        // Create 6 items (more than 4) with proper UUIDs
        $entityIds = [];
        for ($i = 1; $i <= 6; $i++) {
            $entityId = sprintf('550e8400-e29b-41d4-a716-44665544%04d', $i);
            $entityIds[] = $entityId;
            UserItem::create([
                'user_id' => $user->id,
                'entity_id' => $entityId,
                'parent_collection_id' => $collection->id,
            ]);
        }

        // Mock service returns entities with image URLs
        $mockEntities = [];
        foreach (array_slice($entityIds, 0, 4) as $i => $entityId) {
            $mockEntities[$entityId] = [
                'id' => $entityId,
                'image_url' => 'https://example.com/' . ($i + 1) . '.jpg',
            ];
        }

        $mockService = Mockery::mock(DatabaseOfThingsService::class);
        $mockService->shouldReceive('getEntitiesByIds')
            ->with(array_slice($entityIds, 0, 4))
            ->andReturn($mockEntities);

        $resolver = new UserCollectionRepresentativeImagesResolver($mockService);
        $images = $resolver($collection);

        // Should return exactly 4 images, not more
        $this->assertCount(4, $images);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
