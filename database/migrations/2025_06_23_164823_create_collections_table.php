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
        Schema::create('collections', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('category'); // trading-cards, action-figures, etc.
            $table->enum('type', ['official', 'community'])->default('official');
            $table->text('description')->nullable();
            $table->json('metadata'); // releaseDate, publisher, totalItems, etc.
            $table->enum('status', ['active', 'discontinued', 'upcoming'])->default('active');
            $table->string('image_url')->nullable();
            $table->foreignId('contributed_by')->nullable()->constrained('users');
            $table->json('verified_by')->nullable(); // Array of user IDs
            $table->timestamps();
            
            $table->index(['category', 'status']);
            $table->index('slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('collections');
    }
};
