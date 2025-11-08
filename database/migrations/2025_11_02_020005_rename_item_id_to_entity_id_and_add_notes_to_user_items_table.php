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
        // Check if the old column exists before attempting rename
        if (Schema::hasColumn('user_items', 'item_id')) {
            Schema::table('user_items', function (Blueprint $table) {
                // Drop constraints and indexes first (if they exist)
                try {
                    $table->dropUnique(['user_id', 'item_id']);
                } catch (\Exception $e) {
                    // Constraint doesn't exist, continue
                }
                try {
                    $table->dropForeign(['item_id']);
                } catch (\Exception $e) {
                    // Foreign key doesn't exist, continue
                }
                try {
                    $table->dropIndex(['item_id']);
                } catch (\Exception $e) {
                    // Index doesn't exist, continue
                }

                // Rename column
                $table->renameColumn('item_id', 'entity_id');
            });
        }

        // Add notes field if it doesn't exist
        if (!Schema::hasColumn('user_items', 'notes')) {
            Schema::table('user_items', function (Blueprint $table) {
                $table->text('notes')->nullable()->after('metadata');
            });
        }

        // Recreate constraints and indexes with new column name
        // Note: No foreign key to items table since we reference external Supabase entities
        Schema::table('user_items', function (Blueprint $table) {
            // Add index if it doesn't exist
            $indexName = 'user_items_entity_id_index';
            $indexes = \DB::select("SELECT indexname FROM pg_indexes WHERE tablename = 'user_items'");
            $indexExists = collect($indexes)->pluck('indexname')->contains($indexName);

            if (!$indexExists) {
                $table->index('entity_id');
            }

            // Add unique constraint if it doesn't exist
            $uniqueName = 'user_items_user_id_entity_id_unique';
            $constraints = \DB::select("SELECT conname FROM pg_constraint WHERE conname = ?", [$uniqueName]);

            if (empty($constraints)) {
                $table->unique(['user_id', 'entity_id']);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_items', function (Blueprint $table) {
            // Drop constraints and indexes
            $table->dropUnique(['user_id', 'entity_id']);
            $table->dropForeign(['entity_id']);
            $table->dropIndex(['entity_id']);

            // Drop notes column
            $table->dropColumn('notes');

            // Rename column back
            $table->renameColumn('entity_id', 'item_id');
        });

        // Recreate original constraints and indexes
        Schema::table('user_items', function (Blueprint $table) {
            $table->index('item_id');
            $table->foreign('item_id')->references('id')->on('items')->onDelete('cascade');
            $table->unique(['user_id', 'item_id']);
        });
    }
};
