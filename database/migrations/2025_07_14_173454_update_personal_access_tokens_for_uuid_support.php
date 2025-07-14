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
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            // Drop the existing morphs columns
            $table->dropMorphs('tokenable');
            
            // Add UUID-compatible morphs columns
            $table->uuidMorphs('tokenable');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            // Drop UUID morphs columns
            $table->dropMorphs('tokenable');
            
            // Restore original bigint morphs columns  
            $table->morphs('tokenable');
        });
    }
};
