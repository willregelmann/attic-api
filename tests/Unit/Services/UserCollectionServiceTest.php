<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Models\UserCollection;
use App\Services\UserCollectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserCollectionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected UserCollectionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new UserCollectionService();
    }

    public function test_get_collection_tree_returns_root_items()
    {
        $user = User::factory()->create();

        // Create root collection
        $collection = UserCollection::create([
            'user_id' => $user->id,
            'name' => 'Test Collection',
            'parent_collection_id' => null,
        ]);

        $result = $this->service->getCollectionTree($user->id, null);

        $this->assertCount(1, $result);
        $this->assertEquals('Test Collection', $result[0]->name);
    }
}
