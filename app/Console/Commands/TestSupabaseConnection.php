<?php

namespace App\Console\Commands;

use App\Services\SupabaseGraphQLService;
use Illuminate\Console\Command;

class TestSupabaseConnection extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'supabase:test {--search= : Search for entities by name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test connection to Supabase Database of Things API';

    /**
     * Execute the console command.
     */
    public function handle(SupabaseGraphQLService $supabase): int
    {
        $this->info('Testing Supabase Database of Things connection...');
        $this->newLine();

        try {
            // Test 1: List collections
            $this->info('📚 Fetching collections...');
            $collectionsResult = $supabase->listCollections(5);
            $collections = $collectionsResult['collections'];

            if (empty($collections)) {
                $this->warn('No collections found');
                return self::FAILURE;
            }

            $this->info("Found {$this->count($collections)} collections:");
            foreach ($collections as $collection) {
                $attributes = json_decode($collection['attributes'], true);
                $total = $attributes['total'] ?? '?';
                $this->line("  • {$collection['name']} ({$collection['year']}) - {$total} items");
            }
            $this->newLine();

            // Test 2: Get items from first collection
            $firstCollection = $collections[0];
            $this->info("🎴 Fetching items from '{$firstCollection['name']}'...");
            $itemsResult = $supabase->getCollectionItems($firstCollection['id'], 10);
            $items = $itemsResult['items'];

            if (empty($items)) {
                $this->warn('No items found in collection');
            } else {
                $this->info("Found {$this->count($items)} items:");
                foreach ($items as $item) {
                    $entity = $item['entity'];
                    $order = $item['order'];
                    $this->line("  #{$order}: {$entity['name']}");
                }
            }
            $this->newLine();

            // Test 3: Search functionality (if --search provided)
            if ($searchTerm = $this->option('search')) {
                $this->info("🔍 Searching for '{$searchTerm}'...");
                $searchResult = $supabase->searchEntities($searchTerm, null, 10);
                $results = $searchResult['items'];

                if (empty($results)) {
                    $this->warn("No results found for '{$searchTerm}'");
                } else {
                    $this->info("Found {$this->count($results)} results:");
                    foreach ($results as $entity) {
                        $this->line("  • [{$entity['type']}] {$entity['name']} ({$entity['year']})");
                    }
                }
                $this->newLine();
            }

            // Test 4: Get single entity
            if (!empty($items)) {
                $firstItem = $items[0]['entity'];
                $this->info("📦 Fetching entity details for '{$firstItem['name']}'...");
                $entity = $supabase->getEntity($firstItem['id']);

                if ($entity) {
                    $this->line("  ID: {$entity['id']}");
                    $this->line("  Name: {$entity['name']}");
                    $this->line("  Type: {$entity['type']}");
                    $this->line("  Year: {$entity['year']}");

                    $attributes = json_decode($entity['attributes'], true);
                    if (!empty($attributes)) {
                        $this->line("  Attributes:");
                        foreach ($attributes as $key => $value) {
                            $this->line("    - {$key}: {$value}");
                        }
                    }
                }
                $this->newLine();
            }

            $this->info('✅ All tests passed! Supabase connection is working.');
            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Supabase connection failed!');
            $this->error($e->getMessage());
            $this->newLine();

            // Show configuration hints
            $this->warn('Please check your configuration:');
            $this->line('  DATABASE_OF_THINGS_API_URL=' . config('services.supabase.url'));
            $this->line('  DATABASE_OF_THINGS_API_KEY=' . (config('services.supabase.api_key') ? '***' : '(not set)'));

            return self::FAILURE;
        }
    }

    private function count(array $items): int
    {
        return count($items);
    }
}
