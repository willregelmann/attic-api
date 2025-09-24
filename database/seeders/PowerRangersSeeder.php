<?php

namespace Database\Seeders;

use App\Models\Item;
use App\Models\ItemRelationship;
use Illuminate\Database\Seeder;

class PowerRangersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create Power Rangers collection
        $powerRangersCollection = Item::create([
            'type' => 'collection',
            'name' => 'Power Rangers Action Figures',
            'metadata' => [
                'franchise' => 'Power Rangers',
                'manufacturer' => 'Bandai America',
                'release_year' => '1993',
                'total_figures' => 50,
                'description' => 'Original Mighty Morphin Power Rangers action figure series',
            ],
        ]);

        // Create Red Ranger figure with misleading number data
        $redRanger = Item::create([
            'type' => 'collectible',
            'name' => 'Red Ranger Action Figure',
            'metadata' => [
                'figure_number' => '001', // This could be confused with card_number
                'series_number' => 1,
                'ranger_color' => 'Red',
                'character_name' => 'Jason Lee Scott',
                'height' => '8 inches',
                'articulation_points' => 5,
                'accessories' => ['Power Sword', 'Blaster'],
                'zord' => 'Tyrannosaurus Dinozord',
                'release_year' => 1993,
                'sku' => 'PR-001-RED',
            ],
        ]);

        // Create Blue Ranger figure
        $blueRanger = Item::create([
            'type' => 'collectible',
            'name' => 'Blue Ranger Action Figure',
            'metadata' => [
                'figure_number' => '002',
                'series_number' => 1,
                'ranger_color' => 'Blue',
                'character_name' => 'Billy Cranston',
                'height' => '8 inches',
                'articulation_points' => 5,
                'accessories' => ['Power Lance', 'Blaster'],
                'zord' => 'Triceratops Dinozord',
                'release_year' => 1993,
                'sku' => 'PR-002-BLUE',
            ],
        ]);

        // Create Yellow Ranger figure
        $yellowRanger = Item::create([
            'type' => 'collectible',
            'name' => 'Yellow Ranger Action Figure',
            'metadata' => [
                'figure_number' => '003',
                'series_number' => 1,
                'ranger_color' => 'Yellow',
                'character_name' => 'Trini Kwan',
                'height' => '8 inches',
                'articulation_points' => 5,
                'accessories' => ['Power Daggers', 'Blaster'],
                'zord' => 'Saber-Toothed Tiger Dinozord',
                'release_year' => 1993,
                'sku' => 'PR-003-YELLOW',
            ],
        ]);

        // Create Pink Ranger figure with incorrect card_number field
        $pinkRanger = Item::create([
            'type' => 'collectible',
            'name' => 'Pink Ranger Action Figure',
            'metadata' => [
                'card_number' => '004/050', // This is wrong - figures don't have card numbers!
                'figure_number' => '004',
                'series_number' => 1,
                'ranger_color' => 'Pink',
                'character_name' => 'Kimberly Hart',
                'height' => '8 inches',
                'articulation_points' => 5,
                'accessories' => ['Power Bow', 'Blaster'],
                'zord' => 'Pterodactyl Dinozord',
                'release_year' => 1993,
                'sku' => 'PR-004-PINK',
            ],
        ]);

        // Create Black Ranger figure with both figure_number and card_number (confusing!)
        $blackRanger = Item::create([
            'type' => 'collectible',
            'name' => 'Black Ranger Action Figure',
            'metadata' => [
                'card_number' => '005/050', // Wrong field for figures
                'figure_number' => '005',
                'series_number' => 1,
                'ranger_color' => 'Black',
                'character_name' => 'Zack Taylor',
                'height' => '8 inches',
                'articulation_points' => 5,
                'accessories' => ['Power Axe', 'Blaster'],
                'zord' => 'Mastodon Dinozord',
                'release_year' => 1993,
                'sku' => 'PR-005-BLACK',
            ],
        ]);

        // Create Green Ranger with number field as string when it should be integer
        $greenRanger = Item::create([
            'type' => 'collectible',
            'name' => 'Green Ranger Action Figure',
            'metadata' => [
                'figure_number' => 'six', // This should be '006' or 6
                'series_number' => 'first', // This should be 1
                'ranger_color' => 'Green',
                'character_name' => 'Tommy Oliver',
                'height' => '8 inches',
                'articulation_points' => 5,
                'accessories' => ['Dragon Dagger', 'Blaster'],
                'zord' => 'Dragonzord',
                'release_year' => 1993,
                'sku' => 'PR-006-GREEN',
                'special_edition' => true,
            ],
        ]);

        // Add figures to collection with position metadata
        $powerRangersCollection->children()->attach([
            $redRanger->id => [
                'relationship_type' => 'contains',
                'metadata' => ['position' => 1, 'figure_number' => '001'],
            ],
            $blueRanger->id => [
                'relationship_type' => 'contains',
                'metadata' => ['position' => 2, 'figure_number' => '002'],
            ],
            $yellowRanger->id => [
                'relationship_type' => 'contains',
                'metadata' => ['position' => 3, 'figure_number' => '003'],
            ],
            $pinkRanger->id => [
                'relationship_type' => 'contains',
                'metadata' => ['position' => 4, 'figure_number' => '004'],
            ],
            $blackRanger->id => [
                'relationship_type' => 'contains',
                'metadata' => ['position' => 5, 'figure_number' => '005'],
            ],
            $greenRanger->id => [
                'relationship_type' => 'contains',
                'metadata' => ['position' => 6, 'figure_number' => '006'],
            ],
        ]);

        $this->command->info('Power Rangers figures seeded successfully!');
        $this->command->info("Created 1 collection: {$powerRangersCollection->name}");
        $this->command->info('Created 6 action figures with various metadata issues');
    }
}