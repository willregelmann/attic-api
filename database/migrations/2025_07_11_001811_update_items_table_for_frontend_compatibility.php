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
        Schema::table('items', function (Blueprint $table) {
            // Make collectible_id nullable for custom items
            $table->foreignId('collectible_id')->nullable()->change();
            
            // Add name field for custom items and display names
            $table->string('name')->nullable()->after('collectible_id');
            
            // Add is_favorite field
            $table->boolean('is_favorite')->default(false)->after('user_images');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->foreignId('collectible_id')->nullable(false)->change();
            $table->dropColumn(['name', 'is_favorite']);
        });
    }
};
