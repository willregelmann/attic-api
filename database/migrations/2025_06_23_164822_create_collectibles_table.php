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
        Schema::create('collectibles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('category');
            $table->json('base_attributes'); // Flexible attributes per category
            $table->json('components')->nullable(); // For complex items like toys
            $table->json('variants'); // Array of variant objects with pricing
            $table->json('digital_metadata')->nullable(); // blockchain, contract, etc.
            $table->json('image_urls'); // primary and variant images
            $table->foreignId('contributed_by')->nullable()->constrained('users');
            $table->json('verified_by')->nullable();
            $table->timestamps();
            
            $table->index('category');
            $table->index('slug');
            $table->index('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('collectibles');
    }
};
