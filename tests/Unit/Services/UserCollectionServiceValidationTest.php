<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\UserCollection;
use App\Services\DatabaseOfThingsService;
use App\Services\UserCollectionService;

class UserCollectionServiceValidationTest extends TestCase
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

    public function test_validate_move_prevents_moving_collection_to_itself()
    {
        $user = User::factory()->create();
        $collection = UserCollection::create([
            'user_id' => $user->id,
            'name' => 'Collection A',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot move collection into itself');

        $this->service->validateMove($collection->id, $collection->id);
    }

    public function test_validate_move_prevents_moving_collection_into_descendant()
    {
        $user = User::factory()->create();

        // Create hierarchy: A > B > C
        $collectionA = UserCollection::create([
            'user_id' => $user->id,
            'name' => 'Collection A',
        ]);

        $collectionB = UserCollection::create([
            'user_id' => $user->id,
            'name' => 'Collection B',
            'parent_collection_id' => $collectionA->id,
        ]);

        $collectionC = UserCollection::create([
            'user_id' => $user->id,
            'name' => 'Collection C',
            'parent_collection_id' => $collectionB->id,
        ]);

        // Try to move A into C (its grandchild)
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot move collection into its own children');

        $this->service->validateMove($collectionA->id, $collectionC->id);
    }

    public function test_validate_move_allows_valid_moves()
    {
        $user = User::factory()->create();

        $collectionA = UserCollection::create([
            'user_id' => $user->id,
            'name' => 'Collection A',
        ]);

        $collectionB = UserCollection::create([
            'user_id' => $user->id,
            'name' => 'Collection B',
        ]);

        // Moving B into A should be valid
        $this->service->validateMove($collectionB->id, $collectionA->id);

        // If no exception, test passes
        $this->assertTrue(true);
    }

    public function test_validate_move_allows_moving_to_root()
    {
        $user = User::factory()->create();

        $collectionA = UserCollection::create([
            'user_id' => $user->id,
            'name' => 'Collection A',
        ]);

        $collectionB = UserCollection::create([
            'user_id' => $user->id,
            'name' => 'Collection B',
            'parent_collection_id' => $collectionA->id,
        ]);

        // Moving B to root (null parent) should be valid
        $this->service->validateMove($collectionB->id, null);

        $this->assertTrue(true);
    }
}
