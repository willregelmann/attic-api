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
        // Check if foreign key exists before attempting to drop
        $foreignKeys = \DB::select("
            SELECT constraint_name
            FROM information_schema.table_constraints
            WHERE table_name = 'user_collection_favorites'
            AND constraint_type = 'FOREIGN KEY'
            AND constraint_name = 'user_collection_favorites_collection_id_foreign'
        ");

        if (!empty($foreignKeys)) {
            Schema::table('user_collection_favorites', function (Blueprint $table) {
                // Drop foreign key constraint - collection_id now references external Database of Things API
                $table->dropForeign(['collection_id']);
            });
        }

        // Check if old index exists and drop it
        $indexes = \DB::select("
            SELECT indexname
            FROM pg_indexes
            WHERE tablename = 'user_collection_favorites'
            AND indexname = 'user_collection_favorites_collection_id_foreign'
        ");

        if (!empty($indexes)) {
            Schema::table('user_collection_favorites', function (Blueprint $table) {
                $table->dropIndex(['collection_id']);
            });
        }

        // Re-add index without foreign key (if it doesn't exist)
        $hasIndex = \DB::select("
            SELECT indexname
            FROM pg_indexes
            WHERE tablename = 'user_collection_favorites'
            AND indexname = 'user_collection_favorites_collection_id_index'
        ");

        if (empty($hasIndex)) {
            Schema::table('user_collection_favorites', function (Blueprint $table) {
                $table->index('collection_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_collection_favorites', function (Blueprint $table) {
            // Restore foreign key constraint (only works if items table still exists)
            $table->foreign('collection_id')->references('id')->on('items')->onDelete('cascade');
        });
    }
};
