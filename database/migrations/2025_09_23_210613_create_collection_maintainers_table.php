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
        // Legacy table - no longer needed after Supabase migration
        // Table will be dropped by 2025_11_05_132223_drop_legacy_items_tables migration
        // Skipping creation to avoid foreign key issues during testing
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('collection_maintainers');
    }
};
