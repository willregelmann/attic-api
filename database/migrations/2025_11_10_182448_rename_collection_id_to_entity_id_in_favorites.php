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
            $table->renameColumn('collection_id', 'entity_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_collection_favorites', function (Blueprint $table) {
            $table->renameColumn('entity_id', 'collection_id');
        });
    }
};
