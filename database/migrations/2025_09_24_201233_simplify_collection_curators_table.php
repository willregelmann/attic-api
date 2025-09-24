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
            // Add new prompt column
            $table->text('prompt')->nullable()->after('collection_id');
            
            // Remove old columns that are no longer needed
            $table->dropColumn(['name', 'description', 'curator_config']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('collection_curators', function (Blueprint $table) {
            // Add back old columns
            $table->string('name')->after('collection_id');
            $table->text('description')->nullable()->after('name');
            $table->jsonb('curator_config')->after('description');
            
            // Remove new prompt column
            $table->dropColumn('prompt');
        });
    }
};
