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
        // Legacy table - item_relationships no longer exists after Supabase migration
        // Skipping modification
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('item_relationships', function (Blueprint $table) {
            $table->dropIndex(['parent_id', 'canonical_order']);
            $table->dropColumn('canonical_order');
        });
    }
};
