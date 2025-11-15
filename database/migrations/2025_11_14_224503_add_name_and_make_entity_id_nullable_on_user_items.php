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
            // Make entity_id nullable to support custom items
            $table->uuid('entity_id')->nullable()->change();

            // Add name field for custom items
            $table->string('name')->nullable()->after('entity_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_items', function (Blueprint $table) {
            $table->dropColumn('name');

            // Restore entity_id as required (will fail if custom items exist)
            $table->uuid('entity_id')->nullable(false)->change();
        });
    }
};
