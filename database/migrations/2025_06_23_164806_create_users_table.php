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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('username')->unique();
            $table->string('email')->unique();
            $table->string('google_id')->unique();
            $table->string('google_avatar')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->json('profile'); // displayName, bio, location
            $table->json('preferences'); // defaultVisibility, notifications, etc.
            $table->json('trade_rating'); // score, totalTrades, completedTrades
            $table->json('subscription'); // tier, expiresAt
            $table->timestamp('last_active_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
