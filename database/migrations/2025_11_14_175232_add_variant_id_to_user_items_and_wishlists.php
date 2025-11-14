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
        // Add variant_id to user_items table
        Schema::table('user_items', function (Blueprint $table) {
            $table->uuid('variant_id')->nullable()->after('entity_id');
        });

        // Add variant_id to wishlists table
        Schema::table('wishlists', function (Blueprint $table) {
            $table->uuid('variant_id')->nullable()->after('entity_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove variant_id from user_items table
        Schema::table('user_items', function (Blueprint $table) {
            $table->dropColumn('variant_id');
        });

        // Remove variant_id from wishlists table
        Schema::table('wishlists', function (Blueprint $table) {
            $table->dropColumn('variant_id');
        });
    }
};
