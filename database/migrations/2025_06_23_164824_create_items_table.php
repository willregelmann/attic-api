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
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('collectible_id')->constrained('collectibles');
            $table->string('variant_id')->nullable(); // References variant within collectible
            $table->integer('quantity')->default(1);
            $table->string('condition');
            $table->text('personal_notes')->nullable();
            $table->json('component_status')->nullable(); // For tracking parts
            $table->enum('completeness', ['complete', 'incomplete', 'parts-only'])->default('complete');
            $table->json('acquisition_info'); // date, method, price, source, etc.
            $table->json('storage')->nullable(); // location, protection
            $table->json('digital_ownership')->nullable(); // wallet, blockchain verification
            $table->json('availability'); // forSale, forTrade settings
            $table->json('showcase_history')->nullable(); // Track showcase membership
            $table->json('user_images')->nullable(); // User-uploaded photos
            $table->timestamps();
            
            $table->index(['user_id', 'collectible_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
