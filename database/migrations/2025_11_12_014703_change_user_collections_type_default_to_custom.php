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
        Schema::table('user_collections', function (Blueprint $table) {
            // Change default value from 'custom_collection' to 'custom'
            $table->string('type')->default('custom')->change();
        });

        // Update any existing records that still have 'custom_collection'
        \DB::table('user_collections')
            ->where('type', 'custom_collection')
            ->whereNull('linked_dbot_collection_id')
            ->update(['type' => 'custom']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_collections', function (Blueprint $table) {
            // Revert to original default
            $table->string('type')->default('custom_collection')->change();
        });
    }
};
