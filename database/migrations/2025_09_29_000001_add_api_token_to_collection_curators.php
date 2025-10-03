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
        Schema::table('collection_curators', function (Blueprint $table) {
            // Store the encrypted API token for the curator's user account
            $table->text('api_token_encrypted')->nullable()->after('curator_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('collection_curators', function (Blueprint $table) {
            $table->dropColumn('api_token_encrypted');
        });
    }
};