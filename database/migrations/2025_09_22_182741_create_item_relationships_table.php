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
        Schema::create('item_relationships', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('parent_id');
            $table->uuid('child_id');
            $table->enum('relationship_type', ['contains', 'variant_of', 'component_of', 'part_of']);
            $table->jsonb('metadata')->default('{}');
            $table->timestamps();

            $table->foreign('parent_id')->references('id')->on('items')->onDelete('cascade');
            $table->foreign('child_id')->references('id')->on('items')->onDelete('cascade');

            $table->unique(['parent_id', 'child_id', 'relationship_type']);
            $table->index('parent_id');
            $table->index('child_id');
            $table->index('relationship_type');
        });

        // Add check constraint
        \DB::statement('ALTER TABLE item_relationships ADD CONSTRAINT no_self_reference CHECK (parent_id != child_id)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_relationships');
    }
};
