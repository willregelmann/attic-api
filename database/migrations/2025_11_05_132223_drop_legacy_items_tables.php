<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop legacy tables that are no longer used after Supabase migration
        // Canonical data now comes from external Database of Things API

        // Use CASCADE to drop tables with all their dependencies
        \DB::statement('DROP TABLE IF EXISTS item_relationships CASCADE');
        \DB::statement('DROP TABLE IF EXISTS item_images CASCADE');
        \DB::statement('DROP TABLE IF EXISTS collection_maintainers CASCADE');
        \DB::statement('DROP TABLE IF EXISTS items CASCADE');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Note: We're not recreating these tables as they're legacy
        // If you need to recreate them, reference the original migration files:
        // - 2025_09_22_182706_create_items_table.php
        // - 2025_09_22_182741_create_item_relationships_table.php
        // - 2025_09_22_182823_create_item_images_table.php
    }
};
