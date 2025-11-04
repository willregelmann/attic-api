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
            // Drop foreign key constraint - entity_id references external Database of Things, not local items table
            $table->dropForeign(['entity_id']);

            // Also drop the index created by the foreign key
            $table->dropIndex(['entity_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_items', function (Blueprint $table) {
            // Recreate foreign key constraint (though it shouldn't be used)
            $table->foreign('entity_id')->references('id')->on('items')->onDelete('cascade');
        });
    }
};
