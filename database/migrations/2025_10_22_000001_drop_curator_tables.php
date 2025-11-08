<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Drop all curator-related tables as canonical data is now managed in Supabase.
     * The curator system was designed to manage local collections/items, which no
     * longer exist as they've been migrated to Supabase's Database of Things.
     */
    public function up(): void
    {
        // Drop in reverse order of dependencies
        Schema::dropIfExists('curator_run_logs');
        Schema::dropIfExists('curator_suggestions');
        Schema::dropIfExists('collection_curators');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Curator tables are legacy and won't be restored
        // They referenced the items table which no longer exists
        // If you need to restore these tables, you'll need to manually recreate them
    }
};
