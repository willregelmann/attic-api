<?php

namespace App\Console\Commands;

use App\Models\Item;
use App\Models\ItemRelationship;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class UpdatePokemonTCG extends Command
{
    protected $signature = 'pokemon:update {--sets-only : Only update sets} {--set= : Update specific set by ID}';
    protected $description = 'Update Pokemon TCG collections and cards from the official API';

    private const API_BASE = 'https://api.pokemontcg.io/v2';
    private const PAGE_SIZE = 250;

    public function handle()
    {
        $this->info('Starting Pokemon TCG data update...');

        // Create or get the main Pokemon TCG collection
        $mainCollection = $this->getOrCreateMainCollection();

        if ($this->option('set')) {
            // Update specific set
            $this->updateSet($this->option('set'), $mainCollection);
        } else {
            // Update all sets
            $this->updateAllSets($mainCollection);
        }

        if (!$this->option('sets-only') && !$this->option('set')) {
            $this->info('Updating cards for all sets...');
            $this->updateAllCards();
        }

        $this->info('Pokemon TCG update completed!');
    }

    private function getOrCreateMainCollection(): Item
    {
        return Item::firstOrCreate(
            [
                'name' => 'Pokemon Trading Card Game',
                'type' => 'collection',
            ],
            [
                'metadata' => [
                    'source' => 'pokemontcg.io',
                    'category' => 'Trading Cards',
                    'publisher' => 'The Pokemon Company',
                    'description' => 'Complete collection of all Pokemon Trading Card Game sets and cards',
                    'last_updated' => now()->toIso8601String(),
                ]
            ]
        );
    }

    private function updateAllSets(Item $mainCollection): void
    {
        $this->info('Fetching all English Pokemon TCG sets...');

        $page = 1;
        $totalSets = 0;

        do {
            $response = Http::timeout(60)->get(self::API_BASE . '/sets', [
                'page' => $page,
                'pageSize' => self::PAGE_SIZE,
                'q' => 'printedTotal:[1 TO *]', // Only sets with actual cards
                'orderBy' => '-releaseDate'
            ]);

            if (!$response->successful()) {
                $this->error('Failed to fetch sets from API: ' . $response->status());
                return;
            }

            $data = $response->json();
            $sets = $data['data'] ?? [];

            foreach ($sets as $setData) {
                // Only process English sets or international sets
                if ($this->isEnglishSet($setData)) {
                    $this->processSet($setData, $mainCollection);
                    $totalSets++;
                }
            }

            $page++;
            $hasMore = isset($data['page']) && isset($data['totalCount']) &&
                      ($data['page'] * $data['pageSize']) < $data['totalCount'];

        } while ($hasMore);

        $this->info("Updated {$totalSets} English Pokemon TCG sets");
    }

    private function updateSet(string $setId, Item $mainCollection): void
    {
        $this->info("Fetching set: {$setId}");

        $response = Http::timeout(60)->get(self::API_BASE . "/sets/{$setId}");

        if (!$response->successful()) {
            $this->error('Failed to fetch set from API: ' . $response->status());
            return;
        }

        $setData = $response->json()['data'] ?? null;

        if ($setData && $this->isEnglishSet($setData)) {
            $set = $this->processSet($setData, $mainCollection);
            $this->updateCardsForSet($set, $setData['id']);
        }
    }

    private function isEnglishSet(array $setData): bool
    {
        // Check if it's an English set based on various indicators
        // Most sets without language specified are English
        // Japanese sets usually have specific markers
        $name = strtolower($setData['name'] ?? '');

        // Exclude known non-English patterns
        $nonEnglishPatterns = [
            'japan',
            'korean',
            'chinese',
            'french',
            'german',
            'italian',
            'portuguese',
            'spanish',
        ];

        foreach ($nonEnglishPatterns as $pattern) {
            if (str_contains($name, $pattern)) {
                return false;
            }
        }

        return true;
    }

    private function processSet(array $setData, Item $mainCollection): Item
    {
        $this->info("Processing set: {$setData['name']}");

        // Create or update the set collection
        $set = Item::updateOrCreate(
            [
                'name' => $setData['name'],
                'type' => 'collection',
            ],
            [
                'metadata' => [
                    'pokemon_set_id' => $setData['id'],
                    'series' => $setData['series'] ?? null,
                    'printed_total' => $setData['printedTotal'] ?? 0,
                    'total' => $setData['total'] ?? 0,
                    'ptcgo_code' => $setData['ptcgoCode'] ?? null,
                    'release_date' => $setData['releaseDate'] ?? null,
                    'updated_at' => $setData['updatedAt'] ?? null,
                    'legalities' => $setData['legalities'] ?? [],
                    'images' => [
                        'symbol' => $setData['images']['symbol'] ?? null,
                        'logo' => $setData['images']['logo'] ?? null,
                    ],
                    'last_api_update' => now()->toIso8601String(),
                ]
            ]
        );

        // Create relationship to main collection
        ItemRelationship::firstOrCreate(
            [
                'parent_id' => $mainCollection->id,
                'child_id' => $set->id,
                'relationship_type' => 'contains',
            ],
            [
                'metadata' => [
                    'order' => $setData['releaseDate'] ?? null,
                    'series' => $setData['series'] ?? null,
                ]
            ]
        );

        return $set;
    }

    private function updateAllCards(): void
    {
        // Get all Pokemon sets from our database
        $pokemonCollection = Item::where('name', 'Pokemon Trading Card Game')
            ->where('type', 'collection')
            ->first();

        if (!$pokemonCollection) {
            $this->error('Pokemon collection not found');
            return;
        }

        $sets = $pokemonCollection->children()
            ->where('type', 'collection')
            ->get();

        foreach ($sets as $set) {
            $setId = $set->metadata['pokemon_set_id'] ?? null;
            if ($setId) {
                $this->updateCardsForSet($set, $setId);
            }
        }
    }

    private function updateCardsForSet(Item $set, string $setId): void
    {
        $this->info("Fetching cards for set: {$set->name}");

        $page = 1;
        $totalCards = 0;

        do {
            $response = Http::timeout(60)->get(self::API_BASE . '/cards', [
                'q' => "set.id:{$setId}",
                'page' => $page,
                'pageSize' => self::PAGE_SIZE,
                'orderBy' => 'number'
            ]);

            if (!$response->successful()) {
                $this->error("Failed to fetch cards for set {$setId}: " . $response->status());
                return;
            }

            $data = $response->json();
            $cards = $data['data'] ?? [];

            foreach ($cards as $cardData) {
                $this->processCard($cardData, $set);
                $totalCards++;
            }

            $page++;
            $hasMore = isset($data['page']) && isset($data['totalCount']) &&
                      ($data['page'] * $data['pageSize']) < $data['totalCount'];

        } while ($hasMore);

        $this->info("Added {$totalCards} cards to {$set->name}");
    }

    private function processCard(array $cardData, Item $set): void
    {
        // Determine card name with variant info if applicable
        $cardName = $this->buildCardName($cardData);

        // Create or update the card
        $card = Item::updateOrCreate(
            [
                'name' => $cardName,
                'type' => 'collectible',
            ],
            [
                'metadata' => [
                    'pokemon_card_id' => $cardData['id'],
                    'number' => $cardData['number'] ?? null,
                    'printed_number' => $cardData['number'] ?? null,
                    'name_base' => $cardData['name'],
                    'supertype' => $cardData['supertype'] ?? null,
                    'subtypes' => $cardData['subtypes'] ?? [],
                    'types' => $cardData['types'] ?? [],
                    'hp' => $cardData['hp'] ?? null,
                    'rarity' => $cardData['rarity'] ?? null,
                    'artist' => $cardData['artist'] ?? null,
                    'flavor_text' => $cardData['flavorText'] ?? null,
                    'national_pokedex_numbers' => $cardData['nationalPokedexNumbers'] ?? [],
                    'legalities' => $cardData['legalities'] ?? [],
                    'regulation_mark' => $cardData['regulationMark'] ?? null,
                    'images' => [
                        'small' => $cardData['images']['small'] ?? null,
                        'large' => $cardData['images']['large'] ?? null,
                    ],
                    'tcgplayer' => $cardData['tcgplayer'] ?? null,
                    'cardmarket' => $cardData['cardmarket'] ?? null,
                    'attacks' => $cardData['attacks'] ?? [],
                    'weaknesses' => $cardData['weaknesses'] ?? [],
                    'resistances' => $cardData['resistances'] ?? [],
                    'retreat_cost' => $cardData['retreatCost'] ?? [],
                    'abilities' => $cardData['abilities'] ?? [],
                    'rules' => $cardData['rules'] ?? [],
                    'last_api_update' => now()->toIso8601String(),
                ]
            ]
        );

        // Create relationship to set
        ItemRelationship::firstOrCreate(
            [
                'parent_id' => $set->id,
                'child_id' => $card->id,
                'relationship_type' => 'contains',
            ],
            [
                'metadata' => [
                    'card_number' => $cardData['number'] ?? null,
                    'rarity' => $cardData['rarity'] ?? null,
                    'variant_type' => $this->determineVariantType($cardData),
                ]
            ]
        );

        // Handle variants (reverse holos, alternate arts, etc.)
        $this->processVariants($cardData, $card, $set);
    }

    private function buildCardName(array $cardData): string
    {
        $name = $cardData['name'];
        $number = $cardData['number'] ?? '';

        // Add special designations to name
        $suffixes = [];

        // Check for special rarities that indicate variants
        $rarity = strtolower($cardData['rarity'] ?? '');
        if (str_contains($rarity, 'secret')) {
            $suffixes[] = 'Secret Rare';
        } elseif (str_contains($rarity, 'rainbow')) {
            $suffixes[] = 'Rainbow Rare';
        } elseif (str_contains($rarity, 'gold')) {
            $suffixes[] = 'Gold Secret';
        }

        // Check for alternate arts based on number
        if (preg_match('/[a-zA-Z]$/', $number)) {
            $suffixes[] = 'Alternate Art';
        }

        // Check for promo cards
        if (isset($cardData['set']['id']) && str_contains(strtolower($cardData['set']['id']), 'promo')) {
            $suffixes[] = 'Promo';
        }

        // Build final name
        if (!empty($suffixes)) {
            $name .= ' (' . implode(', ', $suffixes) . ')';
        }

        // Add card number for uniqueness
        $name .= " #{$number}";

        return $name;
    }

    private function determineVariantType(array $cardData): ?string
    {
        $rarity = strtolower($cardData['rarity'] ?? '');

        if (str_contains($rarity, 'secret')) {
            return 'secret_rare';
        } elseif (str_contains($rarity, 'rainbow')) {
            return 'rainbow_rare';
        } elseif (str_contains($rarity, 'gold')) {
            return 'gold_secret';
        } elseif (str_contains($rarity, 'shiny')) {
            return 'shiny';
        } elseif (preg_match('/[a-zA-Z]$/', $cardData['number'] ?? '')) {
            return 'alternate_art';
        }

        return null;
    }

    private function processVariants(array $cardData, Item $card, Item $set): void
    {
        // For cards that are explicitly variants of a base card
        // This would require more complex logic to match base cards with their variants
        // For now, we're treating each card as its own entity with variant info in metadata

        // If this is a reverse holo version (API doesn't explicitly mark these)
        // We would need additional data sources to properly link variants
    }
}