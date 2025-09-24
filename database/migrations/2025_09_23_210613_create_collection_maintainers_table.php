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
        Schema::create('collection_maintainers', function (Blueprint $table) {
            $table->id();
            $table->uuid('collection_id');
            $table->uuid('user_id');
            $table->string('role')->default('maintainer'); // maintainer, owner, contributor
            $table->json('permissions')->nullable(); // specific permissions if needed
            $table->timestamps();

            // Indexes
            $table->foreign('collection_id')->references('id')->on('items')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['collection_id', 'user_id']);

            $table->index(['collection_id']);
            $table->index(['user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('collection_maintainers');
    }
};
