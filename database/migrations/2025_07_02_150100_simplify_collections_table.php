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
        Schema::table('collections', function (Blueprint $table) {
            // Remove user contribution and verification fields for MVP simplification
            $table->dropForeign(['contributed_by']);
            $table->dropColumn([
                'contributed_by',
                'verified_by'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('collections', function (Blueprint $table) {
            // Restore the dropped columns
            $table->foreignId('contributed_by')->nullable()->constrained('users');
            $table->json('verified_by')->nullable();
        });
    }
};
