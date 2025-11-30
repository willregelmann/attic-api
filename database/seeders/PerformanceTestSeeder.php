<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserCollection;
use App\Models\UserItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class PerformanceTestSeeder extends Seeder
{
    /**
     * Seed performance test user with predictable data.
     */
    public function run(): void
    {
        // Create or update test user
        $user = User::updateOrCreate(
            ['email' => 'perf-test@attic.local'],
            [
                'name' => 'Performance Test User',
                'password' => Hash::make('perf-test-password'),
                'email_verified_at' => now(),
            ]
        );

        $this->command->info("Created/updated user: {$user->email}");

        // Clean existing data for this user
        UserItem::where('user_id', $user->id)->forceDelete();
        UserCollection::where('user_id', $user->id)->forceDelete();

        // Create collection hierarchy
        $this->createCollections($user);

        $this->command->info('Performance test data seeded successfully!');
    }

    private function createCollections(User $user): void
    {
        // 1. Empty Collection (edge case)
        UserCollection::create([
            'user_id' => $user->id,
            'name' => 'Empty Collection',
            'parent_collection_id' => null,
        ]);
        $this->command->info('  - Created: Empty Collection');

        // 2. Small Collection (10 items, no pagination)
        $smallCollection = UserCollection::create([
            'user_id' => $user->id,
            'name' => 'Small Collection',
            'parent_collection_id' => null,
        ]);
        $this->createCustomItems($user, $smallCollection->id, 10, 'Small Item');
        $this->command->info('  - Created: Small Collection (10 items)');

        // 3. Large Collection (100 items, triggers pagination)
        $largeCollection = UserCollection::create([
            'user_id' => $user->id,
            'name' => 'Large Collection',
            'parent_collection_id' => null,
        ]);
        $this->createCustomItems($user, $largeCollection->id, 100, 'Large Item');
        $this->command->info('  - Created: Large Collection (100 items)');

        // 4. Nested Hierarchy (3 levels deep)
        $level1 = UserCollection::create([
            'user_id' => $user->id,
            'name' => 'Nested Hierarchy',
            'parent_collection_id' => null,
        ]);
        $level2 = UserCollection::create([
            'user_id' => $user->id,
            'name' => 'Level 2',
            'parent_collection_id' => $level1->id,
        ]);
        $level3 = UserCollection::create([
            'user_id' => $user->id,
            'name' => 'Level 3',
            'parent_collection_id' => $level2->id,
        ]);
        $this->createCustomItems($user, $level3->id, 5, 'Nested Item');
        $this->command->info('  - Created: Nested Hierarchy (3 levels)');

        // 5. Mixed Collection (varied items)
        // Note: Originally planned to include wishlisted items, but the wishlists table
        // only supports entity_id (DBoT references) and has no 'name' field.
        // Custom wishlist items cannot be created without real DBoT entity UUIDs.
        $mixedCollection = UserCollection::create([
            'user_id' => $user->id,
            'name' => 'Mixed Collection',
            'parent_collection_id' => null,
        ]);
        $this->createCustomItems($user, $mixedCollection->id, 35, 'Mixed Item');
        $this->command->info('  - Created: Mixed Collection (35 items)');

        // 6. Search Test Collection
        $searchCollection = UserCollection::create([
            'user_id' => $user->id,
            'name' => 'Search Test Collection',
            'parent_collection_id' => null,
        ]);
        $searchTerms = ['Alpha', 'Beta', 'Gamma', 'Delta', 'Pokemon', 'Magic', 'Vintage', 'Rare', 'Common', 'Foil'];
        foreach ($searchTerms as $i => $term) {
            UserItem::create([
                'user_id' => $user->id,
                'parent_collection_id' => $searchCollection->id,
                'name' => "{$term} Test Card #{$i}",
                'entity_id' => null,
            ]);
        }
        $this->command->info('  - Created: Search Test Collection (10 searchable items)');
    }

    private function createCustomItems(User $user, string $collectionId, int $count, string $prefix): void
    {
        for ($i = 1; $i <= $count; $i++) {
            UserItem::create([
                'user_id' => $user->id,
                'parent_collection_id' => $collectionId,
                'name' => "{$prefix} #{$i}",
                'entity_id' => null, // Custom item, no DBoT entity
            ]);
        }
    }

}
