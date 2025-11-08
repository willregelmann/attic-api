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
        // Check and drop foreign key if it exists
        $foreignKeys = \DB::select("
            SELECT constraint_name
            FROM information_schema.table_constraints
            WHERE table_name = 'user_items'
            AND constraint_type = 'FOREIGN KEY'
            AND constraint_name = 'user_items_entity_id_foreign'
        ");

        if (!empty($foreignKeys)) {
            Schema::table('user_items', function (Blueprint $table) {
                $table->dropForeign(['entity_id']);
            });
        }

        // Check and drop index if it exists (and isn't the unique constraint)
        $indexes = \DB::select("
            SELECT indexname
            FROM pg_indexes
            WHERE tablename = 'user_items'
            AND indexname = 'user_items_entity_id_index'
        ");

        if (!empty($indexes)) {
            Schema::table('user_items', function (Blueprint $table) {
                $table->dropIndex(['entity_id']);
            });
        }
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
