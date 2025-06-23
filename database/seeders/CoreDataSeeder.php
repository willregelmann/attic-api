<?php

namespace Database\Seeders;

use App\Models\Collection;
use App\Models\Collectible;
use App\Models\Item;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CoreDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create test users
        $user1 = User::create([
            'username' => 'collector_one',
            'email' => 'collector1@example.com',
            'google_id' => 'google_id_1',
            'google_avatar' => 'https://example.com/avatar1.jpg',
            'email_verified_at' => now(),
            'profile' => [
                'displayName' => 'Collector One',
                'bio' => 'Pokemon card enthusiast',
                'location' => 'San Francisco, CA'
            ],
            'preferences' => [
                'defaultVisibility' => 'public',
                'notifications' => true
            ],
            'trade_rating' => [
                'score' => 4.8,
                'totalTrades' => 25,
                'completedTrades' => 24
            ],
            'subscription' => [
                'tier' => 'premium',
                'expiresAt' => now()->addYear()
            ],
            'last_active_at' => now()
        ]);

        $user2 = User::create([
            'username' => 'action_figure_fan',
            'email' => 'actionfan@example.com',
            'google_id' => 'google_id_2',
            'google_avatar' => 'https://example.com/avatar2.jpg',
            'email_verified_at' => now(),
            'profile' => [
                'displayName' => 'Action Figure Fan',
                'bio' => 'Collecting since 1995',
                'location' => 'New York, NY'
            ],
            'preferences' => [
                'defaultVisibility' => 'private',
                'notifications' => false
            ],
            'trade_rating' => [
                'score' => 4.2,
                'totalTrades' => 12,
                'completedTrades' => 11
            ],
            'subscription' => [
                'tier' => 'free',
                'expiresAt' => null
            ],
            'last_active_at' => now()->subDays(3)
        ]);

        // Create collections
        $pokemonCollection = Collection::create([
            'name' => 'Pokemon Base Set',
            'slug' => 'pokemon-base-set',
            'category' => 'trading-cards',
            'type' => 'official',
            'description' => 'The original Pokemon trading card game base set released in 1996.',
            'metadata' => [
                'releaseDate' => '1996-10-20',
                'publisher' => 'Wizards of the Coast',
                'totalItems' => 102
            ],
            'status' => 'discontinued',
            'image_url' => 'https://example.com/pokemon-base-set.jpg',
            'contributed_by' => $user1->id,
            'verified_by' => [$user1->id]
        ]);

        $starWarsCollection = Collection::create([
            'name' => 'Star Wars Black Series',
            'slug' => 'star-wars-black-series',
            'category' => 'action-figures',
            'type' => 'official',
            'description' => 'Premium 6-inch action figures from the Star Wars universe.',
            'metadata' => [
                'releaseDate' => '2013-08-01',
                'publisher' => 'Hasbro',
                'totalItems' => 150
            ],
            'status' => 'active',
            'image_url' => 'https://example.com/star-wars-black-series.jpg',
            'contributed_by' => $user2->id,
            'verified_by' => [$user2->id]
        ]);

        // Create collectibles
        $charizard = Collectible::create([
            'name' => 'Charizard',
            'slug' => 'charizard-base-set',
            'category' => 'trading-cards',
            'base_attributes' => [
                'type' => 'Fire',
                'hp' => 120,
                'rarity' => 'Rare Holo',
                'cardNumber' => '4/102'
            ],
            'components' => null,
            'variants' => [
                [
                    'id' => 'unlimited',
                    'name' => 'Unlimited Edition',
                    'estimatedValue' => 350.00,
                    'rarity' => 'common_variant'
                ],
                [
                    'id' => 'first_edition',
                    'name' => 'First Edition',
                    'estimatedValue' => 6000.00,
                    'rarity' => 'rare_variant'
                ]
            ],
            'digital_metadata' => null,
            'image_urls' => [
                'primary' => 'https://example.com/charizard-primary.jpg',
                'variants' => [
                    'unlimited' => 'https://example.com/charizard-unlimited.jpg',
                    'first_edition' => 'https://example.com/charizard-first-edition.jpg'
                ]
            ],
            'contributed_by' => $user1->id,
            'verified_by' => [$user1->id]
        ]);

        $darthVader = Collectible::create([
            'name' => 'Darth Vader',
            'slug' => 'darth-vader-black-series',
            'category' => 'action-figures',
            'base_attributes' => [
                'height' => '6 inches',
                'articulation' => 'Premium',
                'franchise' => 'Star Wars',
                'series' => 'Black Series'
            ],
            'components' => [
                'figure',
                'lightsaber',
                'cape',
                'base'
            ],
            'variants' => [
                [
                    'id' => 'standard',
                    'name' => 'Standard Release',
                    'estimatedValue' => 24.99,
                    'rarity' => 'common_variant'
                ],
                [
                    'id' => 'carbonized',
                    'name' => 'Carbonized',
                    'estimatedValue' => 45.00,
                    'rarity' => 'rare_variant'
                ]
            ],
            'digital_metadata' => null,
            'image_urls' => [
                'primary' => 'https://example.com/darth-vader-primary.jpg',
                'variants' => [
                    'standard' => 'https://example.com/darth-vader-standard.jpg',
                    'carbonized' => 'https://example.com/darth-vader-carbonized.jpg'
                ]
            ],
            'contributed_by' => $user2->id,
            'verified_by' => [$user2->id]
        ]);

        // Associate collectibles with collections
        $pokemonCollection->collectibles()->attach($charizard->id);
        $starWarsCollection->collectibles()->attach($darthVader->id);

        // Create items (user's owned collectibles)
        Item::create([
            'user_id' => $user1->id,
            'collectible_id' => $charizard->id,
            'variant_id' => 'unlimited',
            'quantity' => 1,
            'condition' => 'Near Mint',
            'personal_notes' => 'Purchased from local card shop. Slight edge wear.',
            'component_status' => null,
            'completeness' => 'complete',
            'acquisition_info' => [
                'date' => '2023-05-15',
                'method' => 'purchase',
                'price' => 320.00,
                'source' => 'Local Card Shop'
            ],
            'storage' => [
                'location' => 'Binder #1, Page 5',
                'protection' => 'Penny sleeve + toploader'
            ],
            'digital_ownership' => null,
            'availability' => [
                'forSale' => [
                    'isListed' => false,
                    'price' => null
                ],
                'forTrade' => [
                    'isAvailable' => true,
                    'preferences' => 'Looking for Blastoise'
                ]
            ],
            'showcase_history' => null,
            'user_images' => [
                'https://example.com/user-charizard-1.jpg',
                'https://example.com/user-charizard-2.jpg'
            ]
        ]);

        Item::create([
            'user_id' => $user2->id,
            'collectible_id' => $darthVader->id,
            'variant_id' => 'carbonized',
            'quantity' => 1,
            'condition' => 'Mint',
            'personal_notes' => 'Target exclusive find!',
            'component_status' => [
                'figure' => 'excellent',
                'lightsaber' => 'excellent',
                'cape' => 'good',
                'base' => 'excellent'
            ],
            'completeness' => 'complete',
            'acquisition_info' => [
                'date' => '2024-01-20',
                'method' => 'retail',
                'price' => 24.99,
                'source' => 'Target'
            ],
            'storage' => [
                'location' => 'Display case, shelf 2',
                'protection' => 'Original packaging'
            ],
            'digital_ownership' => null,
            'availability' => [
                'forSale' => [
                    'isListed' => true,
                    'price' => 45.00
                ],
                'forTrade' => [
                    'isAvailable' => false,
                    'preferences' => null
                ]
            ],
            'showcase_history' => null,
            'user_images' => [
                'https://example.com/user-vader-display.jpg'
            ]
        ]);
    }
}
