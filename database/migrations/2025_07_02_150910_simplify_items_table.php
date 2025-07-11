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
            // Remove unnecessary fields for MVP simplification
            $table->dropColumn([
                'quantity',
                'condition',
                'component_status',
                'completeness',
                'acquisition_info',
                'storage',
                'digital_ownership',
                'showcase_history'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            // Restore the dropped columns
            $table->integer('quantity')->default(1);
            $table->string('condition');
            $table->json('component_status')->nullable();
            $table->enum('completeness', ['complete', 'incomplete', 'parts-only'])->default('complete');
            $table->json('acquisition_info');
            $table->json('storage')->nullable();
            $table->json('digital_ownership')->nullable();
            $table->json('showcase_history')->nullable();
        });
    }
};
