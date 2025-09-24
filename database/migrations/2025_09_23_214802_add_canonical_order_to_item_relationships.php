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
        Schema::table('item_relationships', function (Blueprint $table) {
            // Add canonical_order column for maintaining item order within collections
            $table->integer('canonical_order')->nullable()->after('relationship_type');

            // Add index for efficient sorting
            $table->index(['parent_id', 'canonical_order']);
        });
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
