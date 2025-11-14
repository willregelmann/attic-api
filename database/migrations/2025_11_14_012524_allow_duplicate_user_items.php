<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Check if the unique constraint exists before trying to drop it
        $constraintExists = DB::select(
            "SELECT constraint_name FROM information_schema.table_constraints
             WHERE table_name = 'user_items'
             AND constraint_name = 'user_items_user_id_entity_id_unique'"
        );

        if (!empty($constraintExists)) {
            Schema::table('user_items', function (Blueprint $table) {
                // Drop the unique constraint on user_id and entity_id
                $table->dropUnique(['user_id', 'entity_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::table('user_items', function (Blueprint $table) {
            // Re-add the unique constraint if rolling back
            $table->unique(['user_id', 'entity_id']);
        });
    }
};
