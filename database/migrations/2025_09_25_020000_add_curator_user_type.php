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
        // Add user_type to users table
        Schema::table('users', function (Blueprint $table) {
            $table->string('user_type')->default('human')->after('id');
            $table->uuid('curator_owner_id')->nullable()->after('user_type');
            $table->json('curator_config')->nullable();
            
            $table->index('user_type');
            $table->foreign('curator_owner_id')->references('id')->on('users')->onDelete('cascade');
        });
        
        // Update collection_curators to reference curator users
        Schema::table('collection_curators', function (Blueprint $table) {
            $table->uuid('curator_user_id')->nullable()->after('collection_id');
            $table->foreign('curator_user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('collection_curators', function (Blueprint $table) {
            $table->dropForeign(['curator_user_id']);
            $table->dropColumn('curator_user_id');
        });
        
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['curator_owner_id']);
            $table->dropColumn(['user_type', 'curator_owner_id', 'curator_config']);
        });
    }
};