<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('user_items', function (Blueprint $table) {
            // Drop the unique constraint on (user_id, entity_id) to allow duplicates
            $table->dropUnique('user_items_user_id_entity_id_unique');

            // Add images JSONB column with default empty array
            $table->jsonb('images')->default('[]')->after('notes');
        });

        // Keep indexes for query performance
        // user_id and entity_id indexes should already exist from previous migrations
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_items', function (Blueprint $table) {
            // Remove images column
            $table->dropColumn('images');

            // Recreate unique constraint
            $table->unique(['user_id', 'entity_id']);
        });
    }
};
