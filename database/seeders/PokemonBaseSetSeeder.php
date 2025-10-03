<?php

namespace Database\Seeders;

use App\Models\Item;
use App\Models\ItemRelationship;
use App\Models\User;
use App\Models\UserItem;
use App\Models\UserCollectionFavorite;
use App\Models\ItemImage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class PokemonBaseSetSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create a test user
        $user = User::create([
            'name' => 'Ash Ketchum',
            'email' => 'ash@pokemon.com',
            'password' => Hash::make('password'),
        ]);

        // Create the Pokemon Base Set collection
        $baseSetCollection = Item::create([
            'type' => 'COLLECTION',
            'name' => 'Pokemon Base Set',
            'metadata' => [
                'set_number' => '1',
                'release_date' => '1999-01-09',
                'total_cards' => 102,
                'publisher' => 'Wizards of the Coast',
                'language' => 'English',
                'description' => 'The first Pokemon TCG set released in North America',
            ],
        ]);

        // Create Charizard card
        $charizard = Item::create([
            'type' => 'COLLECTIBLE',
            'name' => 'Charizard',
            'metadata' => [
                'card_number' => '004/102',
                'rarity' => 'Rare Holo',
                'type' => 'Fire',
                'hp' => 120,
                'stage' => 'Stage 2',
                'evolves_from' => 'Charmeleon',
                'retreat_cost' => 3,
                'weakness' => 'Water',
                'resistance' => 'Fighting',
                'artist' => 'Mitsuhiro Arita',
                'attacks' => [
                    [
                        'name' => 'Fire Spin',
                        'cost' => ['Fire', 'Fire', 'Fire', 'Fire'],
                        'damage' => '100',
                        'effect' => 'Discard 2 Energy cards attached to Charizard in order to use this attack.',
                    ],
                ],
            ],
        ]);

        // Create Charizard Shadowless variant
        $charizardShadowless = Item::create([
            'type' => 'variant',
            'name' => 'Charizard (Shadowless)',
            'metadata' => [
                'card_number' => '004/102',
                'rarity' => 'Rare Holo',
                'variant_type' => 'Shadowless',
                'type' => 'Fire',
                'hp' => 120,
                'stage' => 'Stage 2',
                'evolves_from' => 'Charmeleon',
                'retreat_cost' => 3,
                'weakness' => 'Water',
                'resistance' => 'Fighting',
                'artist' => 'Mitsuhiro Arita',
                'note' => 'Shadowless version - no drop shadow on frame',
                'premium_value' => true,
            ],
        ]);

        // Create Charizard 1st Edition variant
        $charizardFirstEdition = Item::create([
            'type' => 'variant',
            'name' => 'Charizard (1st Edition)',
            'metadata' => [
                'card_number' => '004/102',
                'rarity' => 'Rare Holo',
                'variant_type' => '1st Edition',
                'type' => 'Fire',
                'hp' => 120,
                'stage' => 'Stage 2',
                'evolves_from' => 'Charmeleon',
                'retreat_cost' => 3,
                'weakness' => 'Water',
                'resistance' => 'Fighting',
                'artist' => 'Mitsuhiro Arita',
                'note' => '1st Edition stamp present',
                'premium_value' => true,
                'extremely_rare' => true,
            ],
        ]);

        // Create Blastoise card
        $blastoise = Item::create([
            'type' => 'COLLECTIBLE',
            'name' => 'Blastoise',
            'metadata' => [
                'card_number' => '002/102',
                'rarity' => 'Rare Holo',
                'type' => 'Water',
                'hp' => 100,
                'stage' => 'Stage 2',
                'evolves_from' => 'Wartortle',
                'retreat_cost' => 3,
                'weakness' => 'Lightning',
                'artist' => 'Mitsuhiro Arita',
                'pokemon_power' => [
                    'name' => 'Rain Dance',
                    'effect' => 'As often as you like during your turn (before your attack), you may attach 1 Water Energy card to 1 of your Water Pokemon.',
                ],
                'attacks' => [
                    [
                        'name' => 'Hydro Pump',
                        'cost' => ['Water', 'Water', 'Water', 'Colorless'],
                        'damage' => '40+',
                        'effect' => 'Does 40 damage plus 10 more damage for each Water Energy attached to Blastoise but not used to pay for this attack cost.',
                    ],
                ],
            ],
        ]);

        // Create Blastoise Shadowless variant
        $blastoiseShadowless = Item::create([
            'type' => 'variant',
            'name' => 'Blastoise (Shadowless)',
            'metadata' => [
                'card_number' => '002/102',
                'rarity' => 'Rare Holo',
                'variant_type' => 'Shadowless',
                'type' => 'Water',
                'hp' => 100,
                'stage' => 'Stage 2',
                'evolves_from' => 'Wartortle',
                'retreat_cost' => 3,
                'weakness' => 'Lightning',
                'artist' => 'Mitsuhiro Arita',
                'note' => 'Shadowless version - no drop shadow on frame',
                'premium_value' => true,
            ],
        ]);

        // Create Venusaur card
        $venusaur = Item::create([
            'type' => 'COLLECTIBLE',
            'name' => 'Venusaur',
            'metadata' => [
                'card_number' => '015/102',
                'rarity' => 'Rare Holo',
                'type' => 'Grass',
                'hp' => 100,
                'stage' => 'Stage 2',
                'evolves_from' => 'Ivysaur',
                'retreat_cost' => 2,
                'weakness' => 'Fire',
                'artist' => 'Mitsuhiro Arita',
                'pokemon_power' => [
                    'name' => 'Energy Trans',
                    'effect' => 'As often as you like during your turn, you may take 1 Grass Energy card attached to 1 of your Pokemon and attach it to a different one.',
                ],
                'attacks' => [
                    [
                        'name' => 'Solarbeam',
                        'cost' => ['Grass', 'Grass', 'Grass', 'Grass'],
                        'damage' => '60',
                    ],
                ],
            ],
        ]);

        // Create Pikachu (non-holo common)
        $pikachu = Item::create([
            'type' => 'COLLECTIBLE',
            'name' => 'Pikachu',
            'metadata' => [
                'card_number' => '058/102',
                'rarity' => 'Common',
                'type' => 'Lightning',
                'hp' => 40,
                'stage' => 'Basic',
                'retreat_cost' => 1,
                'weakness' => 'Fighting',
                'artist' => 'Mitsuhiro Arita',
                'attacks' => [
                    [
                        'name' => 'Gnaw',
                        'cost' => ['Colorless'],
                        'damage' => '10',
                    ],
                    [
                        'name' => 'Thunder Jolt',
                        'cost' => ['Lightning', 'Colorless'],
                        'damage' => '30',
                        'effect' => 'Flip a coin. If tails, Pikachu does 10 damage to itself.',
                    ],
                ],
            ],
        ]);

        // Add items to Base Set collection
        $baseSetCollection->children()->attach([
            $charizard->id => [
                'relationship_type' => 'contains',
                'metadata' => ['position' => 4, 'set_number' => '004/102'],
            ],
            $blastoise->id => [
                'relationship_type' => 'contains',
                'metadata' => ['position' => 2, 'set_number' => '002/102'],
            ],
            $venusaur->id => [
                'relationship_type' => 'contains',
                'metadata' => ['position' => 15, 'set_number' => '015/102'],
            ],
            $pikachu->id => [
                'relationship_type' => 'contains',
                'metadata' => ['position' => 58, 'set_number' => '058/102'],
            ],
        ]);

        // Create variant relationships
        $charizard->children()->attach([
            $charizardShadowless->id => [
                'relationship_type' => 'variant_of',
                'metadata' => ['variant_type' => 'shadowless', 'premium' => true],
            ],
            $charizardFirstEdition->id => [
                'relationship_type' => 'variant_of',
                'metadata' => ['variant_type' => '1st_edition', 'premium' => true, 'extremely_rare' => true],
            ],
        ]);

        $blastoise->children()->attach([
            $blastoiseShadowless->id => [
                'relationship_type' => 'variant_of',
                'metadata' => ['variant_type' => 'shadowless', 'premium' => true],
            ],
        ]);

        // User owns some items
        $user->items()->attach([
            $charizard->id => [
                'metadata' => [
                    'condition' => 'Near Mint',
                    'acquisition_date' => '2024-01-15',
                    'purchase_price' => 250.00,
                    'storage_location' => 'Binder 1, Page 1',
                    'notes' => 'Purchased from local card shop',
                ],
            ],
            $blastoise->id => [
                'metadata' => [
                    'condition' => 'Excellent',
                    'acquisition_date' => '2024-02-20',
                    'purchase_price' => 150.00,
                    'storage_location' => 'Binder 1, Page 2',
                    'notes' => 'Traded with friend',
                ],
            ],
            $pikachu->id => [
                'metadata' => [
                    'condition' => 'Mint',
                    'acquisition_date' => '2024-01-10',
                    'purchase_price' => 5.00,
                    'storage_location' => 'Binder 1, Page 10',
                ],
            ],
            $charizardShadowless->id => [
                'metadata' => [
                    'condition' => 'Near Mint',
                    'acquisition_date' => '2024-03-01',
                    'purchase_price' => 1500.00,
                    'storage_location' => 'Top Loader in Safe',
                    'notes' => 'Graded PSA 9 potential',
                    'insured' => true,
                ],
            ],
        ]);

        // User favorites the Base Set collection
        UserCollectionFavorite::create([
            'user_id' => $user->id,
            'collection_id' => $baseSetCollection->id,
        ]);

        // Add some images
        ItemImage::create([
            'item_id' => $charizard->id,
            'user_id' => $user->id,
            'url' => 'https://example.com/images/charizard-base-set.jpg',
            'alt_text' => 'Charizard Base Set Card',
            'is_primary' => true,
            'metadata' => [
                'source' => 'user_upload',
                'resolution' => '600x825',
            ],
        ]);

        ItemImage::create([
            'item_id' => $blastoise->id,
            'user_id' => null,
            'url' => 'https://example.com/images/blastoise-base-set.jpg',
            'alt_text' => 'Blastoise Base Set Card',
            'is_primary' => true,
            'metadata' => [
                'source' => 'official',
                'resolution' => '600x825',
            ],
        ]);

        $this->command->info('Pokemon Base Set seeded successfully!');
        $this->command->info("Created 1 collection: {$baseSetCollection->name}");
        $this->command->info('Created 4 collectibles: Charizard, Blastoise, Venusaur, Pikachu');
        $this->command->info('Created 3 variants: Charizard Shadowless, Charizard 1st Edition, Blastoise Shadowless');
        $this->command->info("User '{$user->name}' owns 4 items and favorited 1 collection");
    }
}