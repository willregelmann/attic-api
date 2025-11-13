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
        Schema::table('user_collections', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('user_items', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('wishlists', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_collections', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('user_items', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('wishlists', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
