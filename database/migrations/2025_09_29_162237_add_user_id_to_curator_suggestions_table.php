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
        Schema::table('curator_suggestions', function (Blueprint $table) {
            // Add user_id to track which user made the suggestion
            // This makes suggestions user-agnostic (works for curators and humans)
            $table->uuid('user_id')->nullable()->after('curator_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->index('user_id');

            // Make curator_id nullable since suggestions can come from any user
            $table->uuid('curator_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('curator_suggestions', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropIndex(['user_id']);
            $table->dropColumn('user_id');

            // Restore curator_id as required
            $table->uuid('curator_id')->nullable(false)->change();
        });
    }
};