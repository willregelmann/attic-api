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
        Schema::dropIfExists('user_collection_favorites');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('user_collection_favorites', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->onDelete('cascade');
            $table->uuid('entity_id'); // References external DBoT collection
            $table->timestamps();

            $table->unique(['user_id', 'entity_id']);
        });
    }
};
