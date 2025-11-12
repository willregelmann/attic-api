<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Fixes production database where wishlists table still has item_id instead of entity_id
     * This is a standalone migration to fix the incomplete 2025_10_22_000000 migration
     */
    public function up(): void
    {
        // Check if wishlists already has entity_id (migration already applied)
        if (Schema::hasColumn('wishlists', 'entity_id')) {
            // Already migrated, skip
            return;
        }

        // Check if item_id column exists (needs migration)
        if (!Schema::hasColumn('wishlists', 'item_id')) {
            // Neither column exists - unexpected state, skip
            return;
        }

        // Drop existing index if it exists
        $indexExists = !empty(DB::select("
            SELECT indexname
            FROM pg_indexes
            WHERE tablename = 'wishlists'
            AND indexname = 'wishlists_item_id_index'
        "));

        if ($indexExists) {
            Schema::table('wishlists', function (Blueprint $table) {
                $table->dropIndex(['item_id']);
            });
        }

        // Rename column from item_id to entity_id
        Schema::table('wishlists', function (Blueprint $table) {
            $table->renameColumn('item_id', 'entity_id');
        });

        // Add new index on entity_id
        Schema::table('wishlists', function (Blueprint $table) {
            $table->index('entity_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Only reverse if entity_id exists
        if (!Schema::hasColumn('wishlists', 'entity_id')) {
            return;
        }

        // Drop entity_id index
        Schema::table('wishlists', function (Blueprint $table) {
            $table->dropIndex(['entity_id']);
        });

        // Rename back to item_id
        Schema::table('wishlists', function (Blueprint $table) {
            $table->renameColumn('entity_id', 'item_id');
        });

        // Restore item_id index
        Schema::table('wishlists', function (Blueprint $table) {
            $table->index('item_id');
        });
    }
};
