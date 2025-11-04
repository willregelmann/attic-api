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
        Schema::table('user_items', function (Blueprint $table) {
            // Drop constraints and indexes first
            $table->dropUnique(['user_id', 'item_id']);
            $table->dropForeign(['item_id']);
            $table->dropIndex(['item_id']);

            // Rename column
            $table->renameColumn('item_id', 'entity_id');

            // Add notes field
            $table->text('notes')->nullable()->after('metadata');
        });

        // Recreate constraints and indexes with new column name
        Schema::table('user_items', function (Blueprint $table) {
            $table->index('entity_id');
            $table->foreign('entity_id')->references('id')->on('items')->onDelete('cascade');
            $table->unique(['user_id', 'entity_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_items', function (Blueprint $table) {
            // Drop constraints and indexes
            $table->dropUnique(['user_id', 'entity_id']);
            $table->dropForeign(['entity_id']);
            $table->dropIndex(['entity_id']);

            // Drop notes column
            $table->dropColumn('notes');

            // Rename column back
            $table->renameColumn('entity_id', 'item_id');
        });

        // Recreate original constraints and indexes
        Schema::table('user_items', function (Blueprint $table) {
            $table->index('item_id');
            $table->foreign('item_id')->references('id')->on('items')->onDelete('cascade');
            $table->unique(['user_id', 'item_id']);
        });
    }
};
