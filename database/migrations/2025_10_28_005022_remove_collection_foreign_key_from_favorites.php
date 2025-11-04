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
        Schema::table('user_collection_favorites', function (Blueprint $table) {
            // Drop foreign key constraint - collection_id now references external Database of Things API
            $table->dropForeign(['collection_id']);

            // Remove the index as well since it's tied to the foreign key
            $table->dropIndex(['collection_id']);

            // Re-add index without foreign key
            $table->index('collection_id');
        });
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
